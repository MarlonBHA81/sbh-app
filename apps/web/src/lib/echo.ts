"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { useCallback, useEffect, useRef } from "react";

import { API_URL, getActiveProfileId } from "@/lib/api/client";

/**
 * Realtime is enhancement-only: the app is fully functional without it. Every
 * failure mode here (missing env, SSR, connection loss) degrades silently.
 */

type ReverbEcho = Echo<"reverb">;

const REVERB_KEY = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
const REVERB_HOST = process.env.NEXT_PUBLIC_REVERB_HOST;
const REVERB_PORT = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? "");
const REVERB_SCHEME = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https";

let echoInstance: ReverbEcho | null = null;

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const prefix = `${name}=`;
  const entry = document.cookie
    .split("; ")
    .find((row) => row.startsWith(prefix));
  return entry ? decodeURIComponent(entry.slice(prefix.length)) : null;
}

/** Shape returned by Laravel's broadcasting/auth for a private channel. */
interface ChannelAuthData {
  auth: string;
  channel_data?: string;
  shared_secret?: string;
}

/**
 * Custom authorizer for private channels: POSTs to the Laravel broadcasting
 * auth endpoint with the Sanctum session cookie, CSRF token and the active
 * profile header so the channel is authorized for the right profile.
 */
function reverbAuthorizer(channel: { name: string }) {
  return {
    authorize(
      socketId: string,
      callback: (error: Error | null, data: ChannelAuthData | null) => void,
    ) {
      const headers: Record<string, string> = {
        Accept: "application/json",
        "Content-Type": "application/json",
      };
      const token = readCookie("XSRF-TOKEN");
      if (token) headers["X-XSRF-TOKEN"] = token;
      const profileId = getActiveProfileId();
      if (profileId) headers["X-Profile-Id"] = profileId;

      fetch(`${API_URL}/broadcasting/auth`, {
        method: "POST",
        credentials: "include",
        headers,
        body: JSON.stringify({
          socket_id: socketId,
          channel_name: channel.name,
        }),
      })
        .then(async (res) => {
          if (!res.ok) throw new Error(`Auth failed (${res.status})`);
          callback(null, (await res.json()) as ChannelAuthData);
        })
        .catch((error: unknown) => {
          callback(
            error instanceof Error ? error : new Error("Auth failed"),
            null,
          );
        });
    },
  };
}

/**
 * Lazy singleton Echo client for Laravel Reverb. Returns null on the server or
 * when Reverb env vars are absent (realtime disabled). Safe to call anywhere.
 */
export function createEcho(): ReverbEcho | null {
  if (typeof window === "undefined") return null;
  if (!REVERB_KEY || !REVERB_HOST || !REVERB_PORT) return null;
  if (echoInstance) return echoInstance;

  const forceTLS = REVERB_SCHEME === "https";

  try {
    echoInstance = new Echo<"reverb">({
      broadcaster: "reverb",
      Pusher,
      key: REVERB_KEY,
      wsHost: REVERB_HOST,
      wsPort: REVERB_PORT,
      wssPort: REVERB_PORT,
      forceTLS,
      enabledTransports: ["ws", "wss"],
      authEndpoint: `${API_URL}/broadcasting/auth`,
      withCredentials: true,
      // Custom authorizer carries the Sanctum cookie + CSRF + profile header.
      authorizer: reverbAuthorizer,
    });
  } catch {
    echoInstance = null;
  }
  return echoInstance;
}

/**
 * Whether Reverb realtime is configured. When false, no live events ever
 * arrive, so views should fall back to periodic polling (usePollingFallback).
 */
export function isRealtimeConfigured(): boolean {
  return Boolean(REVERB_KEY && REVERB_HOST && REVERB_PORT);
}

/**
 * Poll `callback` on an interval, but ONLY when realtime is not configured — so
 * messages / notifications still refresh on a Reverb-less deployment instead of
 * being silently frozen after the first load. Pauses while the tab is hidden.
 * A no-op when Reverb is on (Echo drives updates) or when `enabled` is false.
 */
export function usePollingFallback(
  callback: () => void,
  {
    intervalMs = 20000,
    enabled = true,
  }: { intervalMs?: number; enabled?: boolean } = {},
): void {
  const cb = useRef(callback);
  useEffect(() => {
    cb.current = callback;
  });

  useEffect(() => {
    if (!enabled || isRealtimeConfigured()) return;
    if (typeof window === "undefined") return;

    const tick = () => {
      if (document.visibilityState === "visible") cb.current();
    };
    const timer = setInterval(tick, intervalMs);
    return () => clearInterval(timer);
  }, [enabled, intervalMs]);
}

/** Tear down the singleton (e.g. on logout). */
export function destroyEcho(): void {
  try {
    echoInstance?.disconnect();
  } catch {
    // ignore
  }
  echoInstance = null;
}

type ChannelHandlers = Record<string, (payload: unknown) => void>;

/**
 * Subscribe to a public channel for the lifetime of the component. `handlers`
 * maps event name → callback. Passing `channel = null` is a no-op (e.g. before
 * the target ulid is known). Handlers are read via a ref so callers can pass
 * inline closures without re-subscribing every render.
 */
export function useEchoChannel(
  channel: string | null,
  handlers: ChannelHandlers,
): void {
  const handlersRef = useRef(handlers);
  // Keep the latest handlers without re-subscribing every render.
  useEffect(() => {
    handlersRef.current = handlers;
  });

  useEffect(() => {
    if (!channel) return;
    const echo = createEcho();
    if (!echo) return;

    const events = Object.keys(handlersRef.current);
    const bound = events.map((event) => {
      const fn = (payload: unknown) => handlersRef.current[event]?.(payload);
      return { event, fn };
    });

    try {
      const ch = echo.channel(channel);
      for (const { event, fn } of bound) ch.listen(event, fn);
    } catch {
      return;
    }

    return () => {
      try {
        const ch = echo.channel(channel);
        for (const { event, fn } of bound) ch.stopListening(event, fn);
        echo.leaveChannel(channel);
      } catch {
        // ignore teardown failures
      }
    };
  }, [channel]);
}

/**
 * Subscribe to a private channel's Laravel notification broadcasts
 * (`.notification()`). `onNotification` is read via a ref. No-op until a
 * channel name is provided (i.e. once authed with an active profile).
 */
export function useEchoPrivate(
  channel: string | null,
  onNotification: (payload: unknown) => void,
): void {
  const cbRef = useRef(onNotification);
  // Keep the latest callback without re-subscribing every render.
  useEffect(() => {
    cbRef.current = onNotification;
  });

  useEffect(() => {
    if (!channel) return;
    const echo = createEcho();
    if (!echo) return;

    const handler = (payload: unknown) => cbRef.current(payload);

    try {
      echo.private(channel).notification(handler);
    } catch {
      return;
    }

    return () => {
      try {
        echo.private(channel).stopListeningForNotification(handler);
        echo.leave(channel);
      } catch {
        // ignore teardown failures
      }
    };
  }, [channel]);
}

/**
 * Subscribe to named broadcast events on a *private* channel (as opposed to
 * {@link useEchoPrivate}, which listens for Laravel notifications). `handlers`
 * maps event name (e.g. ".ConversationBumped") to callback.
 */
export function useEchoPrivateEvents(
  channel: string | null,
  handlers: ChannelHandlers,
): void {
  const handlersRef = useRef(handlers);
  useEffect(() => {
    handlersRef.current = handlers;
  });

  useEffect(() => {
    if (!channel) return;
    const echo = createEcho();
    if (!echo) return;

    const events = Object.keys(handlersRef.current);
    const bound = events.map((event) => ({
      event,
      fn: (payload: unknown) => handlersRef.current[event]?.(payload),
    }));

    try {
      const ch = echo.private(channel);
      for (const { event, fn } of bound) ch.listen(event, fn);
    } catch {
      return;
    }

    return () => {
      try {
        const ch = echo.private(channel);
        for (const { event, fn } of bound) ch.stopListening(event, fn);
      } catch {
        // ignore teardown failures
      }
    };
  }, [channel]);
}

type PresenceChannelRef = ReturnType<ReverbEcho["join"]>;

/** Presence roster + client-whisper callbacks for {@link useEchoPresence}. */
export interface PresenceCallbacks {
  /** Current members when the subscription succeeds. */
  onHere?: (members: unknown[]) => void;
  /** A member joined the channel. */
  onJoining?: (member: unknown) => void;
  /** A member left the channel. */
  onLeaving?: (member: unknown) => void;
  /** Client-event (whisper) listeners keyed by event name (e.g. "typing"). */
  whispers?: Record<string, (data: unknown) => void>;
}

/**
 * Subscribe to a Reverb presence channel. `handlers` maps server broadcast
 * event names (e.g. ".MessageSent") to callbacks; `callbacks` receives the
 * presence roster and client whispers. Returns a `whisper` helper for sending
 * client events (e.g. typing) — a no-op when realtime is unavailable.
 *
 * Like the other hooks here, everything degrades silently without Echo, and
 * handlers/callbacks are read via refs so callers can pass inline closures.
 */
export function useEchoPresence(
  channel: string | null,
  handlers: ChannelHandlers,
  callbacks?: PresenceCallbacks,
): { whisper: (event: string, data: Record<string, unknown>) => void } {
  const handlersRef = useRef(handlers);
  const callbacksRef = useRef(callbacks);
  const channelRef = useRef<PresenceChannelRef | null>(null);

  // Keep the latest handlers/callbacks without re-subscribing every render.
  useEffect(() => {
    handlersRef.current = handlers;
  });
  useEffect(() => {
    callbacksRef.current = callbacks;
  });

  useEffect(() => {
    if (!channel) return;
    const echo = createEcho();
    if (!echo) return;

    let ch: PresenceChannelRef;
    try {
      ch = echo.join(channel);
      channelRef.current = ch;
      ch.here((members: unknown[]) => callbacksRef.current?.onHere?.(members));
      ch.joining((member: unknown) =>
        callbacksRef.current?.onJoining?.(member),
      );
      ch.leaving((member: unknown) =>
        callbacksRef.current?.onLeaving?.(member),
      );
      for (const event of Object.keys(handlersRef.current)) {
        ch.listen(event, (payload: unknown) =>
          handlersRef.current[event]?.(payload),
        );
      }
      for (const event of Object.keys(callbacksRef.current?.whispers ?? {})) {
        ch.listenForWhisper(event, (data: unknown) =>
          callbacksRef.current?.whispers?.[event]?.(data),
        );
      }
    } catch {
      channelRef.current = null;
      return;
    }

    return () => {
      channelRef.current = null;
      try {
        echo.leave(channel);
      } catch {
        // ignore teardown failures
      }
    };
  }, [channel]);

  const whisper = useCallback(
    (event: string, data: Record<string, unknown>) => {
      try {
        channelRef.current?.whisper(event, data);
      } catch {
        // realtime unavailable — whispers are best-effort
      }
    },
    [],
  );

  return { whisper };
}

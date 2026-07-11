"use client";

import Echo from "laravel-echo";
import Pusher from "pusher-js";
import { useEffect, useRef } from "react";

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

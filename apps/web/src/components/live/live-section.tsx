"use client";

import { Copy, Radio, Video } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";

import { HlsPlayer } from "@/components/live/hls-player";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { LiveState } from "@/lib/api/types";

/**
 * Live streaming for a masterclass room (ask #4). Members watch the HLS stream;
 * the host (admin) goes live and gets the RTMP ingest credentials. Polls so a
 * "waiting" room flips to the player the moment the host starts streaming.
 */
export function LiveSection({ ulid }: { ulid: string }) {
  const [state, setState] = useState<LiveState | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    try {
      const res = await api.get<{ data: LiveState }>(
        `/api/v1/masterclasses/${ulid}/live`,
      );
      setState(res.data);
    } catch {
      /* leave prior state */
    }
  }, [ulid]);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: LiveState }>(`/api/v1/masterclasses/${ulid}/live`)
      .then((res) => {
        if (!cancelled) setState(res.data);
      })
      .catch(() => {});
    // Poll for status changes (host goes live / ends).
    const timer = setInterval(() => void reload(), 10000);
    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [ulid, reload]);

  async function goLive() {
    setBusy(true);
    try {
      await api.post(`/api/v1/masterclasses/${ulid}/live`);
      await reload();
      toast.success("Stream created — start your encoder");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't start the stream",
      );
    } finally {
      setBusy(false);
    }
  }

  async function endLive() {
    setBusy(true);
    try {
      await api.del(`/api/v1/masterclasses/${ulid}/live`);
      await reload();
      toast.success("Stream ended");
    } catch {
      toast.error("Couldn't end the stream");
    } finally {
      setBusy(false);
    }
  }

  if (!state) return <Skeleton className="h-40 w-full rounded-xl" />;

  // Nothing to show unless a stream exists or the viewer can host.
  if (!state.enabled && !state.session) return null;

  const session = state.session;

  return (
    <section className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-2">
        <Radio
          className={`size-4 ${session?.is_live ? "text-red-500" : "text-text-secondary"}`}
          aria-hidden
        />
        <h2 className="flex-1 font-heading text-[15px] font-semibold text-text-primary">
          Live session
        </h2>
        {session?.is_live ? (
          <span className="rounded-full bg-red-500/12 px-2 py-0.5 text-[10px] font-semibold text-red-600 uppercase">
            ● Live
          </span>
        ) : null}
      </div>

      {/* Player / waiting state for those who can watch. */}
      {session && state.can_watch && session.playback_url && session.is_live ? (
        <HlsPlayer src={session.playback_url} />
      ) : session ? (
        <div className="flex aspect-video w-full flex-col items-center justify-center gap-1 rounded-lg bg-sage/10 text-center text-sm text-text-secondary">
          <Video className="size-6" aria-hidden />
          {state.can_watch
            ? "The host hasn't started streaming yet — hang tight."
            : "Enrol in this room to watch the live session."}
        </div>
      ) : state.is_host ? (
        <p className="text-[13px] text-text-secondary">
          No live session yet. Create one to get your streaming details.
        </p>
      ) : null}

      {/* Host controls. */}
      {state.is_host ? (
        <div className="flex flex-col gap-2">
          {!session ? (
            <Button type="button" className="h-11 gap-1.5" onClick={goLive} disabled={busy}>
              <Radio className="size-4" aria-hidden />
              {busy ? "Creating…" : "Go live"}
            </Button>
          ) : (
            <>
              <HostCreds
                label="RTMP server"
                value={session.ingest_url ?? ""}
              />
              <HostCreds label="Stream key" value={session.stream_key ?? ""} secret />
              <p className="text-[11px] text-text-secondary">
                Paste these into OBS (or any RTMP encoder) and start streaming.
                Members see the video automatically once you go live.
              </p>
              <Button
                type="button"
                variant="outline"
                className="h-10"
                onClick={endLive}
                disabled={busy}
              >
                End stream
              </Button>
            </>
          )}
        </div>
      ) : null}
    </section>
  );
}

function HostCreds({
  label,
  value,
  secret = false,
}: {
  label: string;
  value: string;
  secret?: boolean;
}) {
  const [revealed, setRevealed] = useState(!secret);

  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
      toast.success(`${label} copied`);
    } catch {
      toast.error("Couldn't copy");
    }
  }

  return (
    <div className="flex items-center gap-2 rounded-lg border p-2">
      <div className="flex min-w-0 flex-1 flex-col">
        <span className="text-[10px] font-medium tracking-wide text-text-secondary uppercase">
          {label}
        </span>
        <span className="truncate font-mono text-[12px] text-text-primary">
          {revealed ? value || "—" : "•".repeat(16)}
        </span>
      </div>
      {secret ? (
        <button
          type="button"
          onClick={() => setRevealed((v) => !v)}
          className="shrink-0 text-[11px] font-medium text-teal-text hover:underline"
        >
          {revealed ? "Hide" : "Show"}
        </button>
      ) : null}
      <button
        type="button"
        aria-label={`Copy ${label}`}
        onClick={copy}
        className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-teal-text"
      >
        <Copy className="size-4" aria-hidden />
      </button>
    </div>
  );
}

"use client";

import { Loader2, Pause, Play } from "lucide-react";
import { useRef, useState } from "react";

import type { AudioPayload, Post } from "@/lib/api/types";
import { useSettingsStore } from "@/lib/stores/settings-store";
import { formatDuration } from "@/lib/time";

import { PostBody } from "./post-body";

/**
 * Compact audio player card. A single <audio> element per card (held by ref)
 * drives a custom play/pause button and seek bar. Low-data mode skips
 * preloading so nothing is fetched until the viewer hits play.
 */
export function AudioPost({ post }: { post: Post }) {
  const lowData = useSettingsStore((s) => s.lowData);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const [playing, setPlaying] = useState(false);
  const [current, setCurrent] = useState(0);
  const [duration, setDuration] = useState(0);
  const [waiting, setWaiting] = useState(false);

  const media = post.media[0];
  const payload = (post.payload ?? {}) as unknown as AudioPayload;
  const title = payload.title || post.body || "Audio";

  if (!media) return post.body ? <PostBody text={post.body} /> : null;

  const total = duration || media.duration_seconds || 0;

  function toggle() {
    const el = audioRef.current;
    if (!el) return;
    if (playing) {
      el.pause();
    } else {
      void el.play().catch(() => setPlaying(false));
    }
  }

  function seek(value: number) {
    const el = audioRef.current;
    if (!el) return;
    el.currentTime = value;
    setCurrent(value);
  }

  return (
    <div
      className="flex flex-col gap-3 rounded-xl border bg-card p-3"
      data-no-nav
    >
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation();
            toggle();
          }}
          aria-label={playing ? "Pause" : "Play"}
          className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-transform hover:scale-105"
        >
          {waiting ? (
            <Loader2 className="size-5 animate-spin" aria-hidden />
          ) : playing ? (
            <Pause className="size-5 fill-current" aria-hidden />
          ) : (
            <Play className="size-5 translate-x-0.5 fill-current" aria-hidden />
          )}
        </button>
        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
          <span className="truncate text-sm font-medium">{title}</span>
          <div className="flex items-center gap-2">
            <input
              type="range"
              min={0}
              max={total || 0}
              step={0.1}
              value={Math.min(current, total || 0)}
              onChange={(event) => seek(Number(event.target.value))}
              onClick={(event) => event.stopPropagation()}
              aria-label="Seek"
              className="h-1.5 flex-1 cursor-pointer appearance-none rounded-full bg-muted accent-primary"
            />
            <span className="shrink-0 text-[11px] text-muted-foreground tabular-nums">
              {formatDuration(current)} / {formatDuration(total)}
            </span>
          </div>
        </div>
      </div>

      <audio
        ref={audioRef}
        src={media.url}
        preload={lowData ? "none" : "metadata"}
        onLoadedMetadata={(e) => setDuration(e.currentTarget.duration || 0)}
        onTimeUpdate={(e) => setCurrent(e.currentTarget.currentTime)}
        onPlaying={() => {
          setPlaying(true);
          setWaiting(false);
        }}
        onWaiting={() => setWaiting(true)}
        onPause={() => setPlaying(false)}
        onEnded={() => {
          setPlaying(false);
          setCurrent(0);
        }}
        className="hidden"
      />
    </div>
  );
}

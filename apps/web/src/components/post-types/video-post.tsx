"use client";

import { Loader2, Play, Video as VideoIcon } from "lucide-react";
import { useState } from "react";

import type { Post } from "@/lib/api/types";
import { useSettingsStore } from "@/lib/stores/settings-store";
import { formatDuration } from "@/lib/time";

import { PostBody } from "./post-body";

/**
 * Video card. Never autoplays: a poster (or dark placeholder) is shown until
 * the viewer taps, which mounts a `preload="none"` <video>. Low-data mode adds
 * a further guard by refusing to autoplay even after the tap.
 */
export function VideoPost({ post }: { post: Post }) {
  const lowData = useSettingsStore((s) => s.lowData);
  const [active, setActive] = useState(false);
  const media = post.media[0];

  if (!media) return post.body ? <PostBody text={post.body} /> : null;

  const processing = media.status === "processing";
  const failed = media.status === "failed";
  const duration = formatDuration(media.duration_seconds);

  return (
    <div className="flex flex-col gap-2">
      {post.body ? <PostBody text={post.body} /> : null}

      <div
        className="relative overflow-hidden rounded-xl border bg-black"
        style={{ aspectRatio: "16 / 9" }}
        data-no-nav
      >
        {active && !processing && !failed ? (
          <video
            src={media.url}
            poster={media.thumb_url || undefined}
            controls
            playsInline
            preload="none"
            autoPlay={!lowData}
            className="size-full bg-black object-contain"
          />
        ) : (
          <button
            type="button"
            disabled={processing || failed}
            onClick={(event) => {
              event.stopPropagation();
              setActive(true);
            }}
            className="group absolute inset-0 flex items-center justify-center"
            aria-label={processing ? "Video processing" : "Play video"}
          >
            {media.thumb_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={media.thumb_url}
                alt=""
                loading="lazy"
                className="absolute inset-0 size-full object-cover"
              />
            ) : (
              <span className="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-neutral-800 to-neutral-950">
                <VideoIcon className="size-10 text-white/30" aria-hidden />
              </span>
            )}

            {processing ? (
              <span className="relative flex flex-col items-center gap-2 rounded-lg bg-black/60 px-4 py-3 text-white">
                <Loader2 className="size-6 animate-spin" aria-hidden />
                <span className="text-xs font-medium">Processing…</span>
              </span>
            ) : failed ? (
              <span className="relative rounded-lg bg-black/60 px-4 py-2 text-xs font-medium text-white">
                Video unavailable
              </span>
            ) : (
              <span className="relative flex size-14 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur transition-transform group-hover:scale-105">
                <Play className="size-6 translate-x-0.5 fill-current" aria-hidden />
              </span>
            )}
          </button>
        )}

        {!active && !processing && !failed && media.duration_seconds ? (
          <span className="pointer-events-none absolute right-2 bottom-2 rounded bg-black/70 px-1.5 py-0.5 text-[11px] font-medium text-white tabular-nums">
            {duration}
          </span>
        ) : null}
      </div>
    </div>
  );
}

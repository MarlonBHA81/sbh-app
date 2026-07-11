"use client";

import { Search } from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { MagnifierPayload, Post } from "@/lib/api/types";
import { cn } from "@/lib/utils";

import { PostBody } from "./post-body";

const LENS_RADIUS = 56;

/**
 * Blurred text (and optional image) with a press-and-drag circular lens that
 * reveals what's underneath. A "Reveal all" button calls POST /reveal.
 */
export function MagnifierPost({ post }: { post: Post }) {
  const payload = (post.payload ?? {}) as unknown as MagnifierPayload;
  const containerRef = useRef<HTMLDivElement | null>(null);
  const [lens, setLens] = useState<{ x: number; y: number } | null>(null);
  const [revealed, setRevealed] = useState(false);
  const [revealedText, setRevealedText] = useState<string | null>(null);
  const [revealing, setRevealing] = useState(false);

  const text = revealedText ?? payload.text ?? "";
  const image = payload.image_media_id
    ? (post.media.find((m) => m.ulid === payload.image_media_id) ??
      post.media[0] ??
      null)
    : null;

  function updateLens(event: React.PointerEvent<HTMLDivElement>) {
    const rect = containerRef.current?.getBoundingClientRect();
    if (!rect) return;
    setLens({ x: event.clientX - rect.left, y: event.clientY - rect.top });
  }

  async function revealAll() {
    if (revealing || revealed) return;
    setRevealing(true);
    try {
      const res = await api.post<{ data: { payload: MagnifierPayload } }>(
        `/api/v1/posts/${post.ulid}/reveal`,
      );
      setRevealedText(res.data.payload.text ?? payload.text ?? "");
      setRevealed(true);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't reveal this post",
      );
    } finally {
      setRevealing(false);
    }
  }

  const content = (blurred: boolean) => (
    <div
      aria-hidden={blurred || undefined}
      className={cn(
        "flex flex-col gap-2 p-3",
        blurred && "opacity-90 blur-sm select-none",
      )}
    >
      {image ? (
        <div
          className="overflow-hidden rounded-lg bg-muted"
          style={{ aspectRatio: `${image.width} / ${image.height}` }}
        >
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={image.thumb_url}
            alt=""
            loading="lazy"
            width={image.width}
            height={image.height}
            className="size-full object-cover"
          />
        </div>
      ) : null}
      {text ? <PostBody text={text} /> : null}
    </div>
  );

  if (revealed) {
    return <div className="-m-3">{content(false)}</div>;
  }

  return (
    <div className="flex flex-col gap-2">
      <div
        ref={containerRef}
        className="relative cursor-crosshair touch-none overflow-hidden rounded-xl border"
        onPointerDown={(event) => {
          event.currentTarget.setPointerCapture(event.pointerId);
          updateLens(event);
        }}
        onPointerMove={(event) => {
          if (lens) updateLens(event);
        }}
        onPointerUp={() => setLens(null)}
        onPointerCancel={() => setLens(null)}
      >
        {/* Blurred base layer (defines layout). */}
        {content(true)}
        {/* Sharp layer, clipped to the lens circle. */}
        {lens ? (
          <>
            <div
              className="pointer-events-none absolute inset-0 bg-background"
              style={{
                clipPath: `circle(${LENS_RADIUS}px at ${lens.x}px ${lens.y}px)`,
              }}
            >
              {content(false)}
            </div>
            <div
              className="pointer-events-none absolute rounded-full border-2 border-primary/60 shadow-lg"
              style={{
                width: LENS_RADIUS * 2,
                height: LENS_RADIUS * 2,
                left: lens.x - LENS_RADIUS,
                top: lens.y - LENS_RADIUS,
              }}
              aria-hidden
            />
          </>
        ) : null}
      </div>
      <div className="flex items-center justify-between gap-2">
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Search className="size-3.5" aria-hidden />
          Press and drag to peek
        </p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-9"
          disabled={revealing}
          onClick={(event) => {
            event.stopPropagation();
            void revealAll();
          }}
        >
          {revealing ? "Revealing…" : "Reveal all"}
        </Button>
      </div>
    </div>
  );
}

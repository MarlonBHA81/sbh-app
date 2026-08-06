"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from "@/components/ui/dialog";
import { MediaImage } from "@/components/ui/media-image";
import type { Media, Post } from "@/lib/api/types";
import { useSettingsStore } from "@/lib/stores/settings-store";
import { cn } from "@/lib/utils";

import { PostBody } from "./post-body";

function GridImage({
  media,
  className,
  sizes,
  onClick,
}: {
  media: Media;
  className?: string;
  sizes: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={(event) => {
        event.stopPropagation();
        onClick();
      }}
      className={cn(
        "relative block overflow-hidden bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
        className,
      )}
      aria-label="View image"
    >
      <MediaImage
        src={media.thumb_url}
        alt=""
        fill
        sizes={sizes}
        className="object-cover"
      />
    </button>
  );
}

export function ImagePost({ post }: { post: Post }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const lowData = useSettingsStore((s) => s.lowData);
  // In low data mode, full-res loads only after an explicit tap (per image).
  const [fullRequested, setFullRequested] = useState<ReadonlySet<string>>(
    () => new Set<string>(),
  );
  const media = post.media;
  if (media.length === 0) return post.body ? <PostBody text={post.body} /> : null;

  const single = media.length === 1;
  const openItem = openIndex !== null ? (media[openIndex] ?? null) : null;
  const showFull =
    openItem !== null && (!lowData || fullRequested.has(openItem.ulid));

  return (
    <div className="flex flex-col gap-2">
      {post.body ? <PostBody text={post.body} /> : null}
      <div
        className={cn(
          "grid gap-0.5 overflow-hidden rounded-xl border",
          single ? "grid-cols-1" : "grid-cols-2",
          media.length > 2 && "grid-rows-2",
        )}
        // Reserve vertical space so lazy images never shift layout.
        style={
          single
            ? { aspectRatio: `${media[0].width} / ${media[0].height}` }
            : { aspectRatio: "16 / 9" }
        }
      >
        {single ? (
          <GridImage
            media={media[0]}
            sizes="(max-width: 640px) 100vw, 600px"
            onClick={() => setOpenIndex(0)}
          />
        ) : (
          media.map((item, index) => (
            <GridImage
              key={item.ulid}
              media={item}
              sizes="(max-width: 640px) 50vw, 300px"
              onClick={() => setOpenIndex(index)}
              className={cn(
                // 3 images: first spans full height on the left.
                media.length === 3 && index === 0 && "row-span-2",
              )}
            />
          ))
        )}
      </div>

      <Dialog
        open={openIndex !== null}
        onOpenChange={(open) => {
          if (!open) setOpenIndex(null);
        }}
      >
        <DialogContent
          className="max-w-[calc(100%-1rem)] border-none bg-transparent p-0 shadow-none sm:max-w-3xl"
          onClick={(event) => event.stopPropagation()}
        >
          <DialogTitle className="sr-only">Image</DialogTitle>
          {openItem ? (
            <div className="flex flex-col items-center gap-3">
              <MediaImage
                // Keying on the chosen source forces a fresh element (and error
                // state) when low-data mode swaps the thumb for the full image.
                key={showFull ? "full" : "thumb"}
                src={showFull ? openItem.url : openItem.thumb_url}
                alt=""
                width={openItem.width}
                height={openItem.height}
                sizes="(max-width: 768px) 100vw, 768px"
                className="h-auto max-h-[85dvh] w-full rounded-lg object-contain"
              />
              {!showFull ? (
                <Button
                  type="button"
                  variant="secondary"
                  className="h-11"
                  onClick={() =>
                    setFullRequested((prev) =>
                      new Set(prev).add(openItem.ulid),
                    )
                  }
                >
                  Load full image
                </Button>
              ) : null}
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  );
}

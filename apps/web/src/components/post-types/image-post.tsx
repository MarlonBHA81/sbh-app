"use client";

import { useState } from "react";

import {
  Dialog,
  DialogContent,
  DialogTitle,
} from "@/components/ui/dialog";
import type { Media, Post } from "@/lib/api/types";
import { cn } from "@/lib/utils";

import { PostBody } from "./post-body";

function GridImage({
  media,
  className,
  onClick,
}: {
  media: Media;
  className?: string;
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
        "block overflow-hidden bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
        className,
      )}
      aria-label="View image"
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={media.thumb_url}
        alt=""
        loading="lazy"
        width={media.width}
        height={media.height}
        className="size-full object-cover"
      />
    </button>
  );
}

export function ImagePost({ post }: { post: Post }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const media = post.media;
  if (media.length === 0) return post.body ? <PostBody text={post.body} /> : null;

  const single = media.length === 1;

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
          <GridImage media={media[0]} onClick={() => setOpenIndex(0)} />
        ) : (
          media.map((item, index) => (
            <GridImage
              key={item.ulid}
              media={item}
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
          {openIndex !== null && media[openIndex] ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={media[openIndex].url}
              alt=""
              width={media[openIndex].width}
              height={media[openIndex].height}
              className="max-h-[85dvh] w-full rounded-lg object-contain"
            />
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  );
}

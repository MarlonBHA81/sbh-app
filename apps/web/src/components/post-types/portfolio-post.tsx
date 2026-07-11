"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import type { PortfolioPayload, Post } from "@/lib/api/types";
import { useSettingsStore } from "@/lib/stores/settings-store";

export function PortfolioPost({ post }: { post: Post }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const lowData = useSettingsStore((s) => s.lowData);
  const [fullRequested, setFullRequested] = useState<ReadonlySet<string>>(
    () => new Set<string>(),
  );

  const payload = (post.payload ?? {}) as unknown as PortfolioPayload;
  const media = post.media;

  const openItem = openIndex !== null ? (media[openIndex] ?? null) : null;
  const showFull =
    openItem !== null && (!lowData || fullRequested.has(openItem.ulid));

  return (
    <div className="flex flex-col gap-2">
      {payload.title ? (
        <h3 className="text-lg font-semibold">{payload.title}</h3>
      ) : null}
      {payload.description ? (
        <p className="text-sm text-muted-foreground">{payload.description}</p>
      ) : null}

      <div className="columns-2 gap-2 [&>*]:mb-2">
        {media.map((item, index) => (
          <button
            key={item.ulid}
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              setOpenIndex(index);
            }}
            className="block w-full overflow-hidden rounded-lg border bg-muted break-inside-avoid focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label="View image"
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={item.thumb_url}
              alt=""
              loading="lazy"
              width={item.width}
              height={item.height}
              className="w-full object-cover"
            />
          </button>
        ))}
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
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={showFull ? openItem.url : openItem.thumb_url}
                alt=""
                width={openItem.width}
                height={openItem.height}
                className="max-h-[85dvh] w-full rounded-lg object-contain"
              />
              {!showFull ? (
                <Button
                  type="button"
                  variant="secondary"
                  className="h-11"
                  onClick={() =>
                    setFullRequested((prev) => new Set(prev).add(openItem.ulid))
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

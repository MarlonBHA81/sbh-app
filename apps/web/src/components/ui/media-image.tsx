"use client";

import { ImageOff } from "lucide-react";
import Image, { type ImageProps } from "next/image";
import { useState } from "react";

import { cn } from "@/lib/utils";

type MediaImageProps = Omit<ImageProps, "onError" | "alt"> & {
  alt?: string;
  /** Extra classes applied to the broken-image placeholder box. */
  fallbackClassName?: string;
};

/**
 * User-uploaded media rendered through next/image (automatic responsive
 * srcset + lazy loading via the `sizes` prop), with a graceful placeholder
 * when the source 404s or fails to decode — media can be pruned or still be
 * processing, and a raw broken <img> icon is jarring in the feed. Avatars keep
 * using Radix's built-in fallback.
 *
 * Always pass `sizes` so the optimizer picks an appropriately sized source.
 */
export function MediaImage({
  className,
  fallbackClassName,
  alt = "",
  fill,
  ...props
}: MediaImageProps) {
  const [errored, setErrored] = useState(false);

  if (errored) {
    return (
      <div
        role="img"
        aria-label={alt || undefined}
        className={cn(
          "flex items-center justify-center bg-muted text-muted-foreground",
          fill ? "absolute inset-0 size-full" : "size-full",
          className,
          fallbackClassName,
        )}
      >
        <ImageOff className="size-6" aria-hidden />
      </div>
    );
  }

  return (
    <Image
      alt={alt}
      className={className}
      fill={fill}
      onError={() => setErrored(true)}
      {...props}
    />
  );
}

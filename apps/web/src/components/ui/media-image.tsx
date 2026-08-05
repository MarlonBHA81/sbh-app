"use client";

import { ImageOff } from "lucide-react";
import { useState, type ImgHTMLAttributes } from "react";

import { cn } from "@/lib/utils";

/**
 * A plain <img> that swaps to a muted placeholder when the source fails to load
 * (broken or expired media URL) instead of rendering the browser's default
 * broken-image glyph. Drop-in for content media (`className` carries the sizing,
 * e.g. `size-full object-cover`). Avatars keep using Radix's built-in fallback.
 */
export function MediaImage({
  className,
  alt = "",
  onError,
  ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
  const [failed, setFailed] = useState(false);

  if (failed) {
    return (
      <div
        className={cn(
          "flex items-center justify-center bg-muted text-muted-foreground",
          className,
        )}
        role="img"
        aria-label={alt || "Image unavailable"}
      >
        <ImageOff className="size-5 opacity-60" aria-hidden />
      </div>
    );
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      {...props}
      alt={alt}
      className={className}
      onError={(event) => {
        setFailed(true);
        onError?.(event);
      }}
    />
  );
}

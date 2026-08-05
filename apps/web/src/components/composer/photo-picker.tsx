"use client";

import { ImagePlus, X } from "lucide-react";
import dynamic from "next/dynamic";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { Progress } from "@/components/ui/progress";
import * as api from "@/lib/api/client";
import type { Media } from "@/lib/api/types";

// The cropper (react-easy-crop) only loads once the user picks a photo.
const ImageCropper = dynamic(() => import("./image-cropper"), { ssr: false });

/**
 * Pick up to `max` photos. Each photo goes through the cropper, is encoded
 * to webp and uploaded to POST /media sequentially with progress.
 */
export function PhotoPicker({
  images,
  onChange,
  max = 4,
  disabled = false,
  onUploadingChange,
}: {
  images: Media[];
  onChange: (images: Media[]) => void;
  max?: number;
  disabled?: boolean;
  onUploadingChange?: (uploading: boolean) => void;
}) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  // Files awaiting their turn in the cropper. Never read during render, so
  // a ref keeps the crop→upload→next-crop sequencing simple.
  const queueRef = useRef<File[]>([]);
  const [progress, setProgress] = useState<number | null>(null);
  const [cropSrc, setCropSrc] = useState<string | null>(null);

  const uploading = progress !== null;
  const busy = uploading || cropSrc !== null;

  useEffect(() => {
    onUploadingChange?.(busy);
    return () => onUploadingChange?.(false);
  }, [busy, onUploadingChange]);

  /** Open the cropper for the next queued file, if any. */
  function pump() {
    const next = queueRef.current.shift();
    setCropSrc(next ? URL.createObjectURL(next) : null);
  }

  function closeCropper() {
    if (cropSrc) URL.revokeObjectURL(cropSrc);
  }

  function handleCancel() {
    closeCropper();
    pump();
  }

  async function handleCropped(blob: Blob) {
    closeCropper();
    setCropSrc(null);
    setProgress(0);
    try {
      const form = new FormData();
      form.append("file", blob, "photo.webp");
      const res = await api.postMultipart<{ data: Media }>(
        "/api/v1/media",
        form,
        setProgress,
      );
      onChange([...images, res.data]);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't upload the photo",
      );
    } finally {
      setProgress(null);
      pump();
    }
  }

  function handleFiles(files: FileList | null) {
    if (!files) return;
    const remaining =
      max - images.length - queueRef.current.length - (busy ? 1 : 0);
    const accepted = Array.from(files)
      .filter((file) => file.type.startsWith("image/"))
      .slice(0, Math.max(0, remaining));
    if (accepted.length === 0) return;
    queueRef.current.push(...accepted);
    if (!busy) pump();
  }

  const canAdd = !disabled && !busy && images.length < max;

  return (
    <div className="flex flex-col gap-2">
      <div className="grid grid-cols-4 gap-2">
        {images.map((media) => (
          <div
            key={media.ulid}
            className="relative aspect-square overflow-hidden rounded-lg border bg-muted"
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={media.thumb_url}
              alt=""
              width={media.width}
              height={media.height}
              className="size-full object-cover"
            />
            <button
              type="button"
              aria-label="Remove photo"
              onClick={() =>
                onChange(images.filter((m) => m.ulid !== media.ulid))
              }
              className="absolute top-1 right-1 flex size-7 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/80"
            >
              <X className="size-4" aria-hidden />
            </button>
          </div>
        ))}
        {canAdd ? (
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            className="flex aspect-square min-h-11 flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-muted-foreground transition-colors hover:border-primary/50 hover:text-foreground"
            aria-label="Add photos"
          >
            <ImagePlus className="size-5" aria-hidden />
            <span className="text-[10px]">
              {images.length}/{max}
            </span>
          </button>
        ) : null}
      </div>

      {uploading ? (
        <div className="flex items-center gap-2">
          <Progress
            value={Math.round((progress ?? 0) * 100)}
            className="h-2 flex-1"
          />
          <span className="text-xs text-muted-foreground tabular-nums">
            {Math.round((progress ?? 0) * 100)}%
          </span>
        </div>
      ) : null}

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        multiple={max > 1}
        className="hidden"
        onChange={(event) => {
          handleFiles(event.target.files);
          event.target.value = "";
        }}
      />

      {cropSrc ? (
        <ImageCropper
          src={cropSrc}
          onCancel={handleCancel}
          onCropped={(blob) => void handleCropped(blob)}
        />
      ) : null}
    </div>
  );
}

"use client";
import {
  AudioLines,
  Film,
  Loader2,
  Play,
  UploadCloud,
  X,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { Progress } from "@/components/ui/progress";
import * as api from "@/lib/api/client";
import type { Media } from "@/lib/api/types";
import { formatDuration } from "@/lib/time";
import { cn } from "@/lib/utils";

import { isAbortError, pollMedia, uploadInChunks } from "./chunked-upload";

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Chunked upload card for a single video or audio file. Owns the upload +
 * transcode-poll lifecycle; the resolved Media (and its status) is lifted to
 * the composer via `onChange`. `onUploadingChange` mirrors PhotoPicker so the
 * composer can block submit while bytes are in flight.
 */
export function MediaUpload({
  media,
  onChange,
  type,
  onUploadingChange,
}: {
  media: Media | null;
  onChange: (media: Media | null) => void;
  type: "video" | "audio";
  onUploadingChange?: (uploading: boolean) => void;
}) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const onChangeRef = useRef(onChange);
  useEffect(() => {
    onChangeRef.current = onChange;
  });

  const [progress, setProgress] = useState<number | null>(null);
  const [fileMeta, setFileMeta] = useState<{ name: string; size: number } | null>(
    null,
  );

  const uploading = progress !== null;
  useEffect(() => {
    onUploadingChange?.(uploading);
    return () => onUploadingChange?.(false);
  }, [uploading, onUploadingChange]);

  // Poll for transcode completion whenever we hold a processing Media.
  useEffect(() => {
    if (!media || media.status !== "processing") return;
    const controller = new AbortController();
    pollMedia(media.ulid, {
      signal: controller.signal,
      onTick: (m) => onChangeRef.current(m),
    })
      .then((m) => onChangeRef.current(m))
      .catch(() => {
        // Aborted (unmount / replaced) — ignore.
      });
    return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [media?.ulid, media?.status]);

  async function handleFile(file: File) {
    const controller = new AbortController();
    abortRef.current = controller;
    setFileMeta({ name: file.name, size: file.size });
    setProgress(0);
    try {
      const uploaded = await uploadInChunks(file, type, {
        signal: controller.signal,
        onProgress: setProgress,
      });
      onChange(uploaded);
    } catch (error) {
      if (!isAbortError(error)) {
        toast.error(
          error instanceof api.ApiError
            ? error.message
            : `Couldn't upload the ${type}`,
        );
      }
      onChange(null);
      setFileMeta(null);
    } finally {
      abortRef.current = null;
      setProgress(null);
    }
  }

  function cancelUpload() {
    abortRef.current?.abort();
  }

  function remove() {
    if (media) void api.del(`/api/v1/uploads/${media.ulid}`).catch(() => {});
    onChange(null);
    setFileMeta(null);
  }

  const accept = type === "video" ? "video/*" : "audio/*";
  const FileIcon = type === "video" ? Film : AudioLines;
  const noun = type === "video" ? "video" : "audio";

  // 1) Actively uploading bytes.
  if (uploading) {
    const pct = Math.round((progress ?? 0) * 100);
    return (
      <div className="flex flex-col gap-2 rounded-xl border p-3">
        <div className="flex items-center gap-2">
          <FileIcon className="size-4 shrink-0 text-muted-foreground" aria-hidden />
          <span className="min-w-0 flex-1 truncate text-sm font-medium">
            {fileMeta?.name}
          </span>
          <span className="text-xs text-muted-foreground tabular-nums">
            {fileMeta ? formatBytes(fileMeta.size) : null}
          </span>
          <button
            type="button"
            aria-label="Cancel upload"
            onClick={cancelUpload}
            className="flex size-7 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
          >
            <X className="size-4" aria-hidden />
          </button>
        </div>
        <div className="flex items-center gap-2">
          <Progress value={pct} className="h-2 flex-1" />
          <span className="text-xs text-muted-foreground tabular-nums">
            {pct}%
          </span>
        </div>
      </div>
    );
  }

  // 2) Uploaded — processing / ready / failed.
  if (media) {
    const processing = media.status === "processing";
    const failed = media.status === "failed";
    return (
      <div className="flex flex-col gap-2 rounded-xl border p-3">
        <div className="flex items-center gap-3">
          {type === "video" && media.thumb_url ? (
            <div className="relative aspect-video w-24 shrink-0 overflow-hidden rounded-md bg-black">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={media.thumb_url}
                alt=""
                className="size-full object-cover"
              />
              <Play
                className="absolute inset-0 m-auto size-6 text-white/90"
                aria-hidden
              />
            </div>
          ) : (
            <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
              <FileIcon className="size-5 text-muted-foreground" aria-hidden />
            </span>
          )}
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <span className="truncate text-sm font-medium">
              {fileMeta?.name ?? `Your ${noun}`}
            </span>
            {processing ? (
              <span className="flex items-center gap-1.5 text-xs text-gold-text">
                <Loader2 className="size-3.5 animate-spin" aria-hidden />
                Processing…
              </span>
            ) : failed ? (
              <span className="text-xs font-medium text-destructive">
                Processing failed — try another file.
              </span>
            ) : (
              <span className="text-xs text-muted-foreground tabular-nums">
                Ready · {formatDuration(media.duration_seconds)}
              </span>
            )}
          </div>
          <button
            type="button"
            aria-label={`Remove ${noun}`}
            onClick={remove}
            className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
          >
            <X className="size-4" aria-hidden />
          </button>
        </div>
      </div>
    );
  }

  // 3) Empty — pick a file.
  return (
    <>
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        className={cn(
          "flex min-h-28 w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-6 text-muted-foreground transition-colors",
          "hover:border-primary/50 hover:text-foreground",
        )}
      >
        <UploadCloud className="size-6" aria-hidden />
        <span className="text-sm font-medium">Choose a {noun} file</span>
        <span className="text-xs">Uploaded in the background</span>
      </button>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        className="hidden"
        onChange={(event) => {
          const file = event.target.files?.[0];
          event.target.value = "";
          if (file) void handleFile(file);
        }}
      />
    </>
  );
}

import { API_URL, getActiveProfileId } from "@/lib/api/client";
import * as api from "@/lib/api/client";
import type { Media } from "@/lib/api/types";

/**
 * Resumable-ish chunked upload for large media (video/audio).
 *
 * Flow: POST /uploads (init) → PUT each 1MB slice sequentially with progress →
 * POST /uploads/{ulid}/complete → Media (status "processing"). The caller then
 * polls `pollMedia` until the backend transcodes it to "ready"/"failed".
 *
 * Aborting mid-upload issues a best-effort DELETE /uploads/{ulid}.
 */

interface InitResponse {
  data: { ulid: string; chunk_size: number; total_chunks: number };
}

export interface UploadOptions {
  /** Overall progress as a 0..1 fraction. */
  onProgress?: (fraction: number) => void;
  signal?: AbortSignal;
}

/** How long to keep polling a processing upload before giving up (5 min). */
export const MAX_POLL_MS = 5 * 60 * 1000;
export const POLL_INTERVAL_MS = 2500;

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const prefix = `${name}=`;
  const entry = document.cookie
    .split("; ")
    .find((row) => row.startsWith(prefix));
  return entry ? decodeURIComponent(entry.slice(prefix.length)) : null;
}

/** PUT a single chunk as multipart (`chunk` field) with progress + abort. */
function putChunk(
  uploadUlid: string,
  index: number,
  blob: Blob,
  onChunkProgress: (fraction: number) => void,
  signal?: AbortSignal,
): Promise<void> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("PUT", `${API_URL}/api/v1/uploads/${uploadUlid}/chunks/${index}`);
    xhr.withCredentials = true;
    xhr.setRequestHeader("Accept", "application/json");
    const profileId = getActiveProfileId();
    if (profileId) xhr.setRequestHeader("X-Profile-Id", profileId);
    const token = readCookie("XSRF-TOKEN");
    if (token) xhr.setRequestHeader("X-XSRF-TOKEN", token);

    const onAbort = () => xhr.abort();
    if (signal) {
      if (signal.aborted) {
        reject(new DOMException("Aborted", "AbortError"));
        return;
      }
      signal.addEventListener("abort", onAbort);
    }
    const cleanup = () => signal?.removeEventListener("abort", onAbort);

    xhr.upload.addEventListener("progress", (event) => {
      if (event.lengthComputable) onChunkProgress(event.loaded / event.total);
    });
    xhr.addEventListener("load", () => {
      cleanup();
      if (xhr.status >= 200 && xhr.status < 300) resolve();
      else reject(new Error(`Chunk ${index} failed (${xhr.status})`));
    });
    xhr.addEventListener("error", () => {
      cleanup();
      reject(new Error("Network error"));
    });
    xhr.addEventListener("abort", () => {
      cleanup();
      reject(new DOMException("Aborted", "AbortError"));
    });

    const form = new FormData();
    form.append("chunk", blob);
    xhr.send(form);
  });
}

export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

/**
 * Upload `file` in chunks and complete it. Resolves with the created Media
 * (status "processing"). Throws an AbortError if cancelled.
 */
export async function uploadInChunks(
  file: File,
  type: "video" | "audio",
  { onProgress, signal }: UploadOptions = {},
): Promise<Media> {
  const init = await api.post<InitResponse>("/api/v1/uploads", {
    filename: file.name,
    mime: file.type,
    size_bytes: file.size,
    type,
  });
  const { ulid, chunk_size, total_chunks } = init.data;

  try {
    for (let i = 0; i < total_chunks; i++) {
      if (signal?.aborted) throw new DOMException("Aborted", "AbortError");
      const start = i * chunk_size;
      const slice = file.slice(start, start + chunk_size);
      await putChunk(
        ulid,
        i,
        slice,
        (chunkFraction) => onProgress?.((i + chunkFraction) / total_chunks),
        signal,
      );
      onProgress?.((i + 1) / total_chunks);
    }

    const complete = await api.post<{ data: Media }>(
      `/api/v1/uploads/${ulid}/complete`,
    );
    return complete.data;
  } catch (error) {
    // Cancelled or failed mid-flight: tidy up the partial upload.
    void api.del(`/api/v1/uploads/${ulid}`).catch(() => {});
    throw error;
  }
}

/**
 * Poll GET /media/{ulid} every 2.5s until it is ready/failed or the deadline
 * passes. `onTick` receives each fetched Media so the UI can reflect status.
 * Returns the terminal Media, or a synthesised `failed` Media on timeout.
 */
export async function pollMedia(
  ulid: string,
  {
    onTick,
    signal,
  }: { onTick?: (media: Media) => void; signal?: AbortSignal } = {},
): Promise<Media> {
  const started = Date.now();
  for (;;) {
    if (signal?.aborted) throw new DOMException("Aborted", "AbortError");
    await new Promise((r) => setTimeout(r, POLL_INTERVAL_MS));
    if (signal?.aborted) throw new DOMException("Aborted", "AbortError");

    let media: Media | null = null;
    try {
      const res = await api.get<{ data: Media }>(`/api/v1/media/${ulid}`);
      media = res.data;
    } catch {
      // Transient error — keep polling until the deadline.
    }

    if (media) {
      onTick?.(media);
      if (media.status && media.status !== "processing") return media;
    }

    if (Date.now() - started > MAX_POLL_MS) {
      return { ...(media as Media), ulid, status: "failed" };
    }
  }
}

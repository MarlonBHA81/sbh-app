import * as Sentry from "@sentry/nextjs";

import type { ApiValidationErrors } from "./types";

export const API_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  status: number;
  errors?: ApiValidationErrors;
  /** Raw JSON error payload (e.g. contains `ban_reason` on a 403 ban). */
  data: Record<string, unknown>;

  constructor(
    status: number,
    message: string,
    errors?: ApiValidationErrors,
    data: Record<string, unknown> = {},
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
    this.data = data;
  }
}

/**
 * Active profile ulid sent as `X-Profile-Id` on every request.
 * Set by the auth store when the user switches profiles.
 */
let activeProfileId: string | null = null;

export function setActiveProfileId(ulid: string | null): void {
  activeProfileId = ulid;
}

export function getActiveProfileId(): string | null {
  return activeProfileId;
}

function readCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const prefix = `${name}=`;
  const entry = document.cookie
    .split("; ")
    .find((row) => row.startsWith(prefix));
  return entry ? decodeURIComponent(entry.slice(prefix.length)) : null;
}

let csrfBootstrap: Promise<void> | null = null;

/** Fetch the Sanctum CSRF cookie (deduped across concurrent callers). */
function fetchCsrfCookie(): Promise<void> {
  csrfBootstrap ??= fetch(`${API_URL}/sanctum/csrf-cookie`, {
    method: "GET",
    credentials: "include",
    headers: { Accept: "application/json" },
  })
    .then(() => undefined)
    .finally(() => {
      csrfBootstrap = null;
    });
  return csrfBootstrap;
}

async function ensureCsrf(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;
  await fetchCsrfCookie();
}

type Method = "GET" | "POST" | "PATCH" | "DELETE";

async function request<T>(
  method: Method,
  path: string,
  body?: unknown,
  retried = false,
): Promise<T> {
  const mutating = method !== "GET";
  const headers: Record<string, string> = { Accept: "application/json" };

  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (activeProfileId) headers["X-Profile-Id"] = activeProfileId;

  if (mutating) {
    await ensureCsrf();
    const token = readCookie("XSRF-TOKEN");
    if (token) headers["X-XSRF-TOKEN"] = token;
  }

  const res = await fetch(`${API_URL}${path}`, {
    method,
    credentials: "include",
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  // Stale CSRF token: refresh the cookie and retry once.
  if (res.status === 419 && mutating && !retried) {
    await fetchCsrfCookie();
    return request<T>(method, path, body, true);
  }

  let json: unknown = null;
  if (res.status !== 204) {
    const text = await res.text();
    if (text) {
      try {
        json = JSON.parse(text);
      } catch {
        // Non-JSON body (e.g. HTML error page); leave json null.
      }
    }
  }

  if (!res.ok) {
    const payload = (json ?? {}) as {
      message?: string;
      errors?: ApiValidationErrors;
    } & Record<string, unknown>;
    const error = new ApiError(
      res.status,
      payload.message || res.statusText || "Request failed",
      payload.errors,
      payload,
    );

    // Trail every failed call as a breadcrumb (method/path/status only — no
    // body), and report server errors (5xx) to Sentry. Expected 4xx (auth,
    // validation) are left as breadcrumbs so they don't drown the error stream.
    Sentry.addBreadcrumb({
      category: "api",
      level: res.status >= 500 ? "error" : "warning",
      message: `${method} ${path} → ${res.status}`,
    });
    if (res.status >= 500) {
      Sentry.captureException(error);
    }

    throw error;
  }

  return json as T;
}

/**
 * POST multipart form data (file uploads) with upload progress.
 * Uses XMLHttpRequest because fetch has no upload progress events.
 */
export async function postMultipart<T>(
  path: string,
  form: FormData,
  onProgress?: (fraction: number) => void,
  retried = false,
): Promise<T> {
  await ensureCsrf();

  const result = await new Promise<{ status: number; text: string }>(
    (resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open("POST", `${API_URL}${path}`);
      xhr.withCredentials = true;
      xhr.setRequestHeader("Accept", "application/json");
      if (activeProfileId) xhr.setRequestHeader("X-Profile-Id", activeProfileId);
      const token = readCookie("XSRF-TOKEN");
      if (token) xhr.setRequestHeader("X-XSRF-TOKEN", token);
      xhr.upload.addEventListener("progress", (event) => {
        if (event.lengthComputable && onProgress) {
          onProgress(event.loaded / event.total);
        }
      });
      xhr.addEventListener("load", () =>
        resolve({ status: xhr.status, text: xhr.responseText }),
      );
      xhr.addEventListener("error", () => reject(new Error("Network error")));
      xhr.addEventListener("abort", () => reject(new Error("Upload aborted")));
      xhr.send(form);
    },
  );

  // Stale CSRF token: refresh the cookie and retry once.
  if (result.status === 419 && !retried) {
    await fetchCsrfCookie();
    return postMultipart<T>(path, form, onProgress, true);
  }

  let json: unknown = null;
  if (result.text) {
    try {
      json = JSON.parse(result.text);
    } catch {
      // Non-JSON body; leave json null.
    }
  }

  if (result.status < 200 || result.status >= 300) {
    const payload = (json ?? {}) as {
      message?: string;
      errors?: ApiValidationErrors;
    } & Record<string, unknown>;
    throw new ApiError(
      result.status,
      payload.message || "Upload failed",
      payload.errors,
      payload,
    );
  }

  return json as T;
}

export function get<T>(path: string): Promise<T> {
  return request<T>("GET", path);
}

export function post<T>(path: string, body?: unknown): Promise<T> {
  return request<T>("POST", path, body);
}

export function patch<T>(path: string, body?: unknown): Promise<T> {
  return request<T>("PATCH", path, body);
}

export function del<T>(path: string, body?: unknown): Promise<T> {
  return request<T>("DELETE", path, body);
}

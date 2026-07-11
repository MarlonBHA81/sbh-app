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
    throw new ApiError(
      res.status,
      payload.message || res.statusText || "Request failed",
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

export function del<T>(path: string): Promise<T> {
  return request<T>("DELETE", path);
}

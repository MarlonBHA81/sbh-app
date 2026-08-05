// Server-side helpers for SEO metadata. These hit the public (unauthenticated)
// API endpoints so profile/post pages can render rich <meta> tags for crawlers
// and link unfurlers, even though the interactive page itself is client-rendered.

/**
 * API base for server-side fetches. `INTERNAL_API_URL` lets containers reach
 * the API over the internal docker network; falls back to the public URL.
 */
export function serverApiBase(): string {
  return (
    process.env.INTERNAL_API_URL ??
    process.env.NEXT_PUBLIC_API_URL ??
    "http://localhost:8000"
  );
}

export interface PublicProfile {
  handle: string;
  name: string;
  bio: string | null;
  avatar_url: string | null;
  cover_url: string | null;
  kind: string;
  is_verified: boolean;
  followers_count: number;
  posts_count: number;
  business_category?: string | null;
}

export interface PublicPostMedia {
  thumb_url?: string | null;
  url?: string | null;
}

export interface PublicPost {
  ulid: string;
  type: string;
  body: string | null;
  profile: {
    handle: string;
    name: string;
    avatar_url: string | null;
    is_verified: boolean;
  };
  media: PublicPostMedia[];
  likes_count: number;
  comments_count: number;
  reposts_count: number;
  published_at: string | null;
  topics: string[];
  sensitive: boolean;
}

async function fetchPublic<T>(path: string): Promise<T | null> {
  try {
    const res = await fetch(`${serverApiBase()}${path}`, {
      headers: { Accept: "application/json" },
      // Cache for 5 minutes; crawlers don't need real-time freshness.
      next: { revalidate: 300 },
    });
    if (!res.ok) return null;
    const json: unknown = await res.json();
    // Support both bare objects and `{ data: … }` envelopes.
    if (json && typeof json === "object" && "data" in json) {
      return (json as { data: T }).data;
    }
    return json as T;
  } catch {
    // Network failure / API down — fall back to client rendering.
    return null;
  }
}

export function fetchPublicProfile(handle: string): Promise<PublicProfile | null> {
  return fetchPublic<PublicProfile>(
    `/api/v1/public/profiles/${encodeURIComponent(handle)}`,
  );
}

export function fetchPublicPost(ulid: string): Promise<PublicPost | null> {
  return fetchPublic<PublicPost>(
    `/api/v1/public/posts/${encodeURIComponent(ulid)}`,
  );
}

/** Truncate to `max` characters on a word boundary, adding an ellipsis. */
export function truncate(text: string, max: number): string {
  const clean = text.replace(/\s+/g, " ").trim();
  if (clean.length <= max) return clean;
  return `${clean.slice(0, max - 1).trimEnd()}…`;
}

import * as api from "@/lib/api/client";
import type { PublicPost } from "@/lib/api/public-types";

/**
 * Reciprocity (UX pattern 3): let logged-out visitors browse real content
 * before we ask them to join. These read the unauthenticated public surface.
 */

export interface PublicFeedPage {
  data: PublicPost[];
  nextCursor: string | null;
}

/**
 * Guest-viewable public feed.
 *
 * BACKEND-TODO: no `GET /api/v1/public/feed` endpoint exists yet — only
 * single-profile and single-post public reads are exposed. Until it lands this
 * resolves to an empty page (never throws), so Explore degrades gracefully
 * instead of erroring. Wire the real endpoint here and guests get a live feed.
 */
export async function fetchPublicFeed(
  cursor?: string | null,
): Promise<PublicFeedPage> {
  try {
    const q = cursor ? `?cursor=${encodeURIComponent(cursor)}` : "";
    const res = await api.get<{
      data: PublicPost[];
      meta: { next_cursor: string | null };
    }>(`/api/v1/public/feed${q}`);
    return { data: res.data, nextCursor: res.meta.next_cursor };
  } catch {
    return { data: [], nextCursor: null };
  }
}

export interface PublicBusiness {
  handle: string;
  name: string;
  avatar_url: string | null;
  category: string | null;
  location: string | null;
  is_verified: boolean;
}

/**
 * Guest-viewable business directory listing. Guests see full listings; only
 * *contacting* a business is gated (on the profile page).
 *
 * BACKEND-TODO: `business/directory` is auth-only. Resolves to an empty list
 * until `GET /api/v1/public/business/directory` is added.
 */
export async function fetchPublicBusinesses(): Promise<PublicBusiness[]> {
  try {
    const res = await api.get<{ data: PublicBusiness[] }>(
      "/api/v1/public/business/directory",
    );
    return res.data;
  } catch {
    return [];
  }
}

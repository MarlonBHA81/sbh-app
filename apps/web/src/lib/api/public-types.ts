/** Shapes returned by the unauthenticated /api/v1/public endpoints. */

export interface PublicPostMedia {
  type: string;
  url: string | null;
  thumb_url: string | null;
  width: number | null;
  height: number | null;
}

export interface PublicPost {
  ulid: string;
  type: string;
  body: string | null;
  sensitive: boolean;
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
  views_count: number;
  published_at: string | null;
  topics: { slug: string; name: string }[];
}

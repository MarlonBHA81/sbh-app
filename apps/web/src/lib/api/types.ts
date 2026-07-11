export interface User {
  id: number;
  name: string;
  email: string;
  locale: string | null;
  timezone: string | null;
  settings: Record<string, unknown> | null;
}

export type ProfileKind = "personal" | "business";
export type Relationship = "none" | "following" | "pending" | "self";

export interface Profile {
  ulid: string;
  kind: ProfileKind;
  handle: string;
  name: string;
  bio: string | null;
  avatar_url: string | null;
  cover_url: string | null;
  category: string | null;
  website: string | null;
  location: string | null;
  is_private: boolean;
  is_verified: boolean;
  followers_count: number;
  following_count: number;
  posts_count: number;
  badges: string[];
  relationship: Relationship;
}

export interface Paginated<T> {
  data: T[];
  meta: {
    next_cursor: string | null;
  };
}

export type PostType =
  | "text"
  | "link"
  | "image"
  | "quote"
  | "repost"
  | "typewriter"
  | "magnifier"
  | "secret"
  | "checkin";

export type PostVisibility = "public" | "followers";
export type PostStatus = "draft" | "scheduled" | "published";

export interface Media {
  ulid: string;
  url: string;
  thumb_url: string;
  width: number;
  height: number;
  type: "image";
}

export interface LinkPayload {
  url: string;
  title?: string;
  description?: string;
}

export type TypewriterSpeed = "slow" | "normal" | "fast";

export interface TypewriterPayload {
  text: string;
  speed?: TypewriterSpeed;
}

export interface MagnifierPayload {
  text: string;
  image_media_id?: string;
}

/** Secret posts return `{revealed: false}` until POST /reveal. */
export interface SecretPayload {
  revealed?: boolean;
  secret_text?: string;
}

export interface CheckinPayload {
  place_name: string;
  city?: string;
  country_code?: string;
}

export interface Post {
  ulid: string;
  type: PostType;
  body: string | null;
  payload: Record<string, unknown> | null;
  visibility: PostVisibility;
  status: PostStatus;
  sensitive: boolean;
  likes_count: number;
  comments_count: number;
  reposts_count: number;
  views_count: number;
  published_at: string | null;
  scheduled_at: string | null;
  profile: Profile;
  media: Media[];
  parent: Post | null;
  created_at: string;
}

export interface MeResponse {
  data: {
    user: User;
    profiles: Profile[];
    active_profile: Profile;
  };
}

export type ApiValidationErrors = Record<string, string[]>;

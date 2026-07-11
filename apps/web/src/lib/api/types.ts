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

/** Minimal topic shape attached to posts and used in pickers/chips. */
export interface PostTopic {
  id: number;
  slug: string;
  name: string;
  icon: string | null;
}

export interface Topic extends PostTopic {
  description?: string | null;
  followers_count: number;
  is_following?: boolean;
  children: Topic[];
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

/** Reddit-style vote: 1 up, -1 down, 0 none. */
export type Vote = 1 | -1 | 0;

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
  upvotes_count: number;
  downvotes_count: number;
  /** Viewer state: has the active profile liked this post. */
  liked: boolean;
  /** Viewer state: the active profile's vote on this post. */
  my_vote: Vote;
  published_at: string | null;
  scheduled_at: string | null;
  profile: Profile;
  media: Media[];
  parent: Post | null;
  topics?: PostTopic[];
  created_at: string;
}

export interface Comment {
  ulid: string;
  /** "[deleted]" for tombstoned comments. */
  body: string;
  /** Nesting depth 0-3 (replies past depth 3 are disallowed). */
  depth: number;
  likes_count: number;
  upvotes_count: number;
  downvotes_count: number;
  replies_count: number;
  liked: boolean;
  my_vote: Vote;
  profile: Profile;
  parent_comment_ulid: string | null;
  created_at: string;
  /** First 2 replies preloaded on top-level comments. */
  replies?: Comment[];
}

export type NotificationType =
  | "new_follower"
  | "follow_requested"
  | "follow_accepted"
  | "post_liked"
  | "post_commented"
  | "comment_replied"
  | "comment_liked"
  | "mentioned"
  | "post_reposted"
  | "post_quoted";

export interface NotificationActor {
  ulid: string;
  handle: string;
  name: string;
  avatar_url: string | null;
}

export interface AppNotification {
  id: string;
  type: NotificationType;
  data: {
    actor: NotificationActor;
    post_ulid?: string;
    comment_ulid?: string;
    preview?: string;
  };
  read_at: string | null;
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

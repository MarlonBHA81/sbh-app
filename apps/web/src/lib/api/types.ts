export interface User {
  id: number;
  name: string;
  email: string;
  locale: string | null;
  timezone: string | null;
  settings: Record<string, unknown> | null;
}

export type ProfileKind = "personal" | "business";
export type Relationship =
  | "none"
  | "following"
  | "pending"
  | "self"
  | "blocked";

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
  /** Viewer has muted this profile (optional; absent on older payloads). */
  is_muted?: boolean;
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
  | "checkin"
  | "video"
  | "audio"
  | "blog"
  | "poll"
  | "quiz"
  | "event"
  | "job"
  | "portfolio";

export type PostVisibility = "public" | "followers";
export type PostStatus = "draft" | "scheduled" | "published";

/** Processing lifecycle for chunk-uploaded media (video/audio). */
export type MediaStatus = "processing" | "ready" | "failed";
export type MediaType = "image" | "video" | "audio";

export interface Media {
  ulid: string;
  url: string;
  /** Poster for video / cover for audio; may be null while processing. */
  thumb_url: string;
  width: number;
  height: number;
  type: MediaType;
  /** Present on chunk-uploaded media; absent (ready) for images. */
  status?: MediaStatus;
  duration_seconds?: number | null;
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

/** Audio posts carry an optional title in the payload. */
export interface AudioPayload {
  title?: string;
}

/** Portfolio posts: a title, optional description and 1-10 image media. */
export interface PortfolioPayload {
  title: string;
  description?: string;
}

/** Minimal Tiptap document JSON (whitelisted node/mark tree). */
export interface TiptapMark {
  type: string;
  attrs?: Record<string, unknown> | null;
}

export interface TiptapNode {
  type: string;
  attrs?: Record<string, unknown> | null;
  content?: TiptapNode[];
  marks?: TiptapMark[];
  text?: string;
}

export interface TiptapDoc {
  type: "doc";
  content?: TiptapNode[];
}

export interface BlogPayload {
  title: string;
  doc: TiptapDoc;
  excerpt?: string;
}

/** Poll satellite attached to poll posts. */
export interface PollOption {
  id: number | string;
  label: string;
  votes_count: number;
  percent: number;
}

export interface Poll {
  question?: string | null;
  ends_at: string | null;
  votes_count: number;
  viewer_option_id: number | string | null;
  options: PollOption[];
}

/** Quiz satellite. `correct_index` is only present once the viewer attempts. */
export interface QuizQuestion {
  id?: number | string;
  question: string;
  options: string[];
  correct_index?: number;
}

export interface QuizAttempt {
  score_pct: number;
  answers: number[];
}

export interface Quiz {
  attempts_count: number;
  viewer_attempt: QuizAttempt | null;
  questions: QuizQuestion[];
}

export type EventRsvp = "going" | "interested";

/** Event satellite. Named `PostEvent` to avoid clashing with the DOM `Event`. */
export interface PostEvent {
  title: string;
  starts_at: string;
  ends_at: string | null;
  venue: string | null;
  going_count: number;
  interested_count: number;
  viewer_rsvp: EventRsvp | null;
}

export type EmploymentType =
  | "full_time"
  | "part_time"
  | "contract"
  | "freelance"
  | "internship";

/** Job satellite. */
export interface PostJob {
  title: string;
  company: string;
  location: string;
  employment_type: EmploymentType;
  salary_min: number | null;
  salary_max: number | null;
  currency: string;
  apply_url: string;
  expires_at: string | null;
  is_expired: boolean;
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
  /** Satellite data present on the corresponding post type. */
  poll?: Poll | null;
  quiz?: Quiz | null;
  event?: PostEvent | null;
  job?: PostJob | null;
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

export interface AppStatus {
  maintenance_message: string | null;
  registration_open: boolean;
}

export type ApiValidationErrors = Record<string, string[]>;

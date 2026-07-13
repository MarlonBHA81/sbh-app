export interface User {
  id: number;
  name: string;
  email: string;
  locale: string | null;
  timezone: string | null;
  is_admin?: boolean;
  is_super_admin?: boolean;
  settings: Record<string, unknown> | null;
}

/**
 * A badge attached to a profile (ProfileResource). Rank badges use
 * `kind: "rank"`; other kinds (e.g. verification, achievements) may appear too.
 */
export interface ProfileBadge {
  kind: string;
  key: string;
  name: string;
  icon: string | null;
}

export type ProfileKind = "personal" | "business";
export type Relationship =
  | "none"
  | "following"
  | "pending"
  | "self"
  | "blocked";

/** Who may start a direct message with this profile. */
export type DmPrivacy = "everyone" | "followers" | "no_one";

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
  social_links?: {
    linkedin?: string | null;
    facebook?: string | null;
    instagram?: string | null;
    whatsapp?: string | null;
  } | null;
  location: string | null;
  is_private: boolean;
  is_verified: boolean;
  followers_count: number;
  following_count: number;
  posts_count: number;
  /** Lifetime experience points (gamification, Milestone 8). */
  xp_total: number;
  /** Profile badges; the rank surfaces here with `kind: "rank"`. */
  badges: ProfileBadge[];
  relationship: Relationship;
  /** Viewer has muted this profile (optional; absent on older payloads). */
  is_muted?: boolean;
  /** Who may DM this profile (own-profile payloads / settings). */
  dm_privacy?: DmPrivacy;
  /**
   * Whether this profile shares its (approximate) location on the map.
   * Present on own-profile payloads only (Milestone 9).
   */
  share_location?: boolean;
  /**
   * The structured business category (Milestone 10). Present on business
   * profiles; null when unset. Absent on payloads that don't include it.
   */
  business_category?: BusinessCategory | null;
}

/** Trimmed profile shape embedded in messaging payloads. */
export interface ProfileLite {
  ulid: string;
  handle: string;
  name: string;
  avatar_url: string | null;
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
  /** Injected into feeds when the post is a paid promotion (Milestone 11). */
  promoted?: boolean;
  /** The campaign backing a promoted feed post (Milestone 11). */
  campaign_ulid?: string | null;
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
  | "post_quoted"
  | "rank_unlocked";

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
    /** Absent on actor-less notifications such as `rank_unlocked`. */
    actor?: NotificationActor;
    post_ulid?: string;
    comment_ulid?: string;
    preview?: string;
    /** Present on `rank_unlocked` notifications. */
    rank?: RankSummary;
  };
  read_at: string | null;
  created_at: string;
}

/** GET /api/v1/me returns this shape unwrapped (no `data` envelope). */
export interface MeResponse {
  user: User;
  profiles: Profile[];
  active_profile: Profile | null;
}

export interface AppStatus {
  maintenance_message: string | null;
  registration_open: boolean;
}

export type ApiValidationErrors = Record<string, string[]>;

// --- Messaging (Milestone 7) ---------------------------------------------

export type ConversationKind = "dm" | "group";
export type ConversationRole = "owner" | "admin" | "member";

export interface ConversationParticipant {
  profile: ProfileLite;
  role: ConversationRole;
}

/** Compact last-message summary shown in the conversation list. */
export interface ConversationLastMessage {
  ulid: string;
  preview: string;
  sender_handle: string;
  created_at: string;
  deleted: boolean;
}

export interface Conversation {
  ulid: string;
  kind: ConversationKind;
  /** null for DMs (title is derived from the other participant). */
  title: string | null;
  avatar_path: string | null;
  rules: string | null;
  participants: ConversationParticipant[];
  last_message: ConversationLastMessage | null;
  unread_count: number;
  my_role: ConversationRole;
  created_at: string;
}

export interface MessageReaction {
  emoji: string;
  count: number;
  reacted_by_me: boolean;
}

/** Quoted preview of the message being replied to. */
export interface MessageReplyTo {
  ulid: string;
  /** Preview body; null when the original is hidden/deleted. */
  body: string | null;
  sender: ProfileLite;
}

export interface Message {
  ulid: string;
  /** null when hidden or deleted. */
  body: string | null;
  hidden?: boolean;
  deleted?: boolean;
  reply_to: MessageReplyTo | null;
  reactions: MessageReaction[];
  media: Media[];
  profile: ProfileLite;
  created_at: string;
}

// --- Gamification (Milestone 8) ------------------------------------------

/** A rank tier. `min_xp` is the XP threshold to reach it. */
export interface Rank {
  key: string;
  name: string;
  icon: string;
  min_xp: number;
}

/** Compact rank shape used in `rank_unlocked` notification payloads. */
export interface RankSummary {
  key: string;
  name: string;
  icon: string | null;
}

/** The next rank plus the viewer's progress toward it (0-100). */
export interface NextRank extends Rank {
  progress_pct: number;
}

/** One earnable XP action with the viewer's daily progress against its cap. */
export interface XpAction {
  action_key: string;
  label: string;
  /** XP earned from this action so far today. */
  earned_today: number;
  /** Times this action has been counted today. */
  times_today: number;
  /** Maximum times this action counts per day. */
  daily_cap: number;
  /** XP awarded per occurrence. */
  points: number;
}

/** GET /api/v1/me/xp — the viewer's XP standing and daily earning surface. */
export interface XpSummary {
  xp_total: number;
  rank: Rank;
  next_rank: NextRank | null;
  today: XpAction[];
}

/** One row of the leaderboard. */
export interface LeaderboardRow {
  position: number;
  profile: Profile;
  xp: number;
}

/** The viewer's own standing (may be outside the returned page). */
export interface LeaderboardViewer {
  position: number;
  xp: number;
}

/** GET /api/v1/gamification/leaderboard */
export interface LeaderboardResponse {
  period: LeaderboardPeriod;
  entries: LeaderboardRow[];
  /** The viewer's own row ("me"), possibly outside the returned page. */
  me: LeaderboardViewer | null;
}

export type LeaderboardPeriod = "weekly" | "all";

/** Broadcast payload for the `.XpAwarded` private-channel event. */
export interface XpAwardedEvent {
  points: number;
  action_key: string;
  xp_total: number;
  label: string;
}

// --- Search, geo & local feeds (Milestone 9) -----------------------------

/** GET /api/v1/search — combined people + topics for the global palette. */
export interface SearchResults {
  profiles: Profile[];
  topics: Topic[];
}

/**
 * A nearby, opted-in profile with its privacy-rounded coordinates and the
 * distance from the viewer. Returned by GET /api/v1/geo/nearby-users.
 */
export interface NearbyUser extends Profile {
  lat: number;
  lng: number;
  distance_km: number;
}

/** GET /api/v1/geo/reverse — reverse geocode of a coordinate (or null). */
export interface ReverseGeocode {
  country_code: string | null;
  country: string | null;
  city: string | null;
  display_name: string | null;
}

/** Which local feed the Nearby tab is showing. */
export type LocalScope = "radius" | "city" | "country";

// --- Business hub (Milestone 10) -----------------------------------------

/** A business category from GET /api/v1/business/categories. */
export interface BusinessCategory {
  id: number;
  slug: string;
  name: string;
  icon: string | null;
}

/** A business need is either something offered or something sought. */
export type BusinessNeedKind = "offering" | "seeking";

/** One of the active profile's business needs (max 10 active). */
export interface BusinessNeed {
  ulid: string;
  kind: BusinessNeedKind;
  description: string;
  active: boolean;
  category: BusinessCategory;
}

/** A single reason a match was made: one of my needs meets one of theirs. */
export interface BusinessMatchReason {
  my_need: {
    ulid: string;
    kind: BusinessNeedKind;
    category: BusinessCategory;
  };
  their_need: {
    ulid: string;
    kind: BusinessNeedKind;
    description: string;
    category: BusinessCategory;
  };
}

/** A matched business profile with its score and the reasons behind it. */
export interface BusinessMatch {
  profile: Profile;
  score: number;
  matches: BusinessMatchReason[];
}

// --- Advertising & insights (Milestone 11) -------------------------------

/** Lifecycle of an ad campaign. */
export type CampaignStatus = "active" | "paused" | "completed";

/** Trimmed post shape embedded in a campaign (its promoted post). */
export interface CampaignPost {
  ulid: string;
  type: PostType;
  body: string | null;
  likes_count: number;
  comments_count: number;
  reposts_count: number;
  views_count: number;
}

/**
 * A promotion of one post. Campaigns are metrics-first: budget fields are
 * null for metrics-only campaigns. Counts are lifetime totals. Returned by
 * the /ads/campaigns endpoints.
 */
export interface Campaign {
  ulid: string;
  status: CampaignStatus;
  budget_cents: number | null;
  spent_cents: number;
  remaining_cents: number | null;
  impressions: number;
  /** Post opens (click-throughs to the post detail). */
  clicks: number;
  /** Outbound clicks on the post's link. */
  link_clicks: number;
  ctr_pct: number;
  link_ctr_pct: number;
  starts_at: string;
  ends_at: string;
  post: CampaignPost;
  /** Daily event series; present only on the detail endpoint (`?series=1`). */
  series?: CampaignSeriesPoint[];
  /** Unique signed-in viewers reached; present only on the detail endpoint. */
  reach?: number;
}

/** One day of a campaign's events, for the detail chart. */
export interface CampaignSeriesPoint {
  date: string;
  impressions: number;
  clicks: number;
  link_clicks: number;
}

/**
 * A sponsor placement returned by GET /ads/slots/{placement}. The endpoint
 * returns 204 (no slot to show) — surfaces treat that as "render nothing".
 */
export interface AdSlot {
  key: string;
  name: string;
  sponsor_name: string;
  sponsor_url: string;
  image_url: string;
  body: string;
}

/** One day of the analytics engagement series. */
export interface AnalyticsSeriesPoint {
  date: string;
  views: number;
  likes: number;
  comments: number;
  reposts: number;
}

/** Aggregate totals for the selected analytics window. */
export interface AnalyticsTotals {
  views: number;
  likes: number;
  comments: number;
  reposts: number;
  votes: number;
  followers_gained: number;
  posts_published: number;
}

/** GET /api/v1/analytics/overview — headline totals + daily series. */
export interface AnalyticsOverview {
  totals: AnalyticsTotals;
  series: AnalyticsSeriesPoint[];
}

/** One own-post row with its per-post metrics (GET /analytics/posts). */
export interface AnalyticsPostRow {
  ulid: string;
  type: PostType;
  body: string | null;
  published_at: string | null;
  created_at: string;
  views: number;
  likes: number;
  comments: number;
  reposts: number;
  engagement_rate_pct: number;
}

export interface User {
  id: number;
  name: string;
  email: string;
  locale: string | null;
  timezone: string | null;
  is_admin?: boolean;
  is_super_admin?: boolean;
  /** Whether the account has a password (false for social-only accounts). */
  has_password?: boolean;
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

export type ProfileKind = "personal" | "business" | "corporate";

/** A user's role on a (business) profile they help manage/post under (Roles P4). */
export type ProfileRole = "owner" | "manager" | "poster";

/** A member of a business profile's team (Roles P4). */
export interface TeamMember {
  handle: string | null;
  name: string;
  avatar_url: string | null;
  role: ProfileRole;
}
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
  /** Business journey stage (V1) — where the member is in their journey. */
  journey_stage?: string | null;
  /** Opted in to mentoring other members (V2 · CONNECT). */
  is_mentor?: boolean | null;
  /** Trusted facilitator: can run challenges + own Spaces (Roles P2). */
  is_facilitator?: boolean | null;
  /** The viewer's role on this profile (Roles P4); set on /me payloads only. */
  my_role?: ProfileRole | null;
  is_private: boolean;
  is_verified: boolean;
  followers_count: number;
  following_count: number;
  posts_count: number;
  /** Lifetime experience points (gamification, Milestone 8). */
  xp_total: number;
  /** Times this profile's comments were marked helpful (V1 · contribution). */
  helpful_count?: number;
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
  /** Ask-the-Community question flag (V2 · CONNECT). */
  is_question?: boolean;
  /** A question is answered once its author marks a reply helpful. */
  is_answered?: boolean;
  /** Celebrated as a win / success story (V2 · BELONG). */
  is_win?: boolean;
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
  /** Marked helpful by the post author (V1 · contribution recognition). */
  is_helpful: boolean;
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
  | "rank_unlocked"
  | "group_approved"
  | "group_rejected";

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

/** Super-admin feature toggles, keyed by flag name (config/features.php). */
export type FeatureFlags = Record<string, boolean>;

/** GET /api/v1/me returns this shape unwrapped (no `data` envelope). */
export interface MeResponse {
  user: User;
  profiles: Profile[];
  active_profile: Profile | null;
  features?: FeatureFlags;
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
  /** Groups only: pending until an admin approves; null for DMs. */
  approval_status: "pending" | "approved" | "rejected" | null;
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

/** An admin-created XP challenge (GET /api/v1/challenges). */
export interface Challenge {
  ulid: string;
  title: string;
  description: string | null;
  starts_at: string;
  ends_at: string;
  status: "upcoming" | "active" | "ended";
  participants_count: number;
  joined: boolean;
}

/** Challenge detail adds its leaderboard (GET /api/v1/challenges/{ulid}). */
export interface ChallengeDetail extends Challenge {
  entries: LeaderboardRow[];
  me: LeaderboardViewer | null;
}

/** Opportunity kinds (V1 · GROW). */
export type OpportunityType =
  | "tender"
  | "funding"
  | "grant"
  | "procurement"
  | "programme"
  | "competition";

/** A growth opportunity surfaced to members (tenders, funding, grants…). */
export interface Opportunity {
  ulid: string;
  type: OpportunityType;
  title: string;
  description: string;
  organisation: string | null;
  url: string | null;
  /** Where the opportunity came from, e.g. "eTenders", "SEDA" (V3). */
  source: string | null;
  /** Canonical link to the original listing (V3). */
  source_url: string | null;
  /** Verified as coming from an official/trusted source (V3). */
  is_official: boolean;
  /** Partner-featured (metrics-first, no price/budget) — V3. */
  is_sponsored: boolean;
  sponsor_name: string | null;
  sponsor_url: string | null;
  industry: string | null;
  province: string | null;
  amount: string | null;
  /** ISO date (YYYY-MM-DD) or null when it never closes. */
  closes_at: string | null;
  is_open: boolean;
  is_saved: boolean;
  published_at: string | null;
  /** Why it was surfaced when fit-ranked (V3); present on coach/Home suggestions. */
  fit_reason?: string | null;
}

/** A member-set goal or milestone (V3 · PROGRESS). */
export interface Goal {
  ulid: string;
  title: string;
  target: string | null;
  /** ISO date (YYYY-MM-DD) or null when there's no deadline. */
  due_on: string | null;
  is_done: boolean;
  completed_at: string | null;
  created_at: string | null;
}

/** Growth stats shown on the business dashboard (V3 · PROGRESS). */
export interface DashboardStats {
  posts_count: number;
  helpful_count: number;
  xp_total: number;
  streak_days: number;
  goals_completed: number;
  goals_total: number;
}

/** The full dashboard payload (GET /api/v1/me/dashboard). */
export interface DashboardData {
  stats: DashboardStats;
  goals: Goal[];
}

/** An admin-curated wellness prompt/read (V3 · BELONG). */
export interface WellnessResource {
  ulid: string;
  category: string;
  title: string;
  body: string;
}

/** A member's private wellness check-in (V3 · BELONG). */
export interface WellnessCheckin {
  ulid: string;
  /** 1 (finding it hard) … 5 (doing well). */
  mood: number;
  note: string | null;
  created_at: string | null;
}

/** Resource Library kinds (V2 · LEARN). */
export type ResourceType = "template" | "checklist" | "toolkit" | "ai_prompt";

/** Resource Library categories (V2 · LEARN). */
export type ResourceCategory =
  | "marketing"
  | "finance"
  | "operations"
  | "sales"
  | "legal"
  | "people";

/** A curated learning resource (template, checklist, toolkit, AI prompt). */
export interface LibraryResource {
  ulid: string;
  type: ResourceType;
  category: ResourceCategory;
  title: string;
  description: string;
  url: string;
  industry: string | null;
  is_saved: boolean;
  published_at: string | null;
}

/** A bite-size learning module (V2 · LEARN). */
export interface Lesson {
  ulid: string;
  title: string;
  body: string | null;
  external_url: string | null;
  minutes: number;
  journey_stage: string | null;
  position: number;
  track: { ulid: string; title: string } | null;
  is_completed: boolean;
}

/** Lite reference to the next lesson in a track. */
export interface LessonRef {
  ulid: string;
  title: string;
}

/** GET /api/v1/me/learn/progress */
export interface LessonProgress {
  completed: number;
  total: number;
}

/** Product kinds sold on a storefront (Shop). */
export type ProductType = "digital_download" | "course" | "service";

/** A vendor storefront (Shop). */
export interface Store {
  ulid: string;
  slug: string;
  name: string;
  tagline: string | null;
  about: string | null;
  brand_color: string | null;
  accent_color: string | null;
  logo_url: string | null;
  banner_url: string | null;
  policies: string | null;
  is_active: boolean;
  products_count?: number;
  owner: { handle: string | null; name: string | null };
}

/** A product sold by a store (Shop). */
export interface Product {
  ulid: string;
  type: ProductType;
  title: string;
  description: string;
  price_cents: number | null;
  sale_price_cents?: number | null;
  effective_price_cents?: number | null;
  on_sale?: boolean;
  currency: string;
  cover_url: string | null;
  external_url: string | null;
  is_published: boolean;
  has_file?: boolean;
  is_html_tool?: boolean;
  is_free?: boolean;
  course?: { modules_count?: number; lessons_count?: number };
  store?: {
    slug: string;
    name: string;
    vat_registered?: boolean;
    vat_rate_bp?: number;
  };
  cross_sells?: {
    ulid: string;
    title: string;
    price_cents: number | null;
    currency: string;
    cover_url: string | null;
  }[];
  bumps?: {
    ulid: string;
    title: string;
    price_cents: number;
    original_price_cents: number | null;
    currency: string;
  }[];
  upsells?: {
    ulid: string;
    title: string;
    price_cents: number;
    original_price_cents: number | null;
    currency: string;
    cover_url: string | null;
  }[];
}

/** Dry-run checkout price preview (sale prices, coupon, inclusive VAT). */
export interface CheckoutQuote {
  subtotal_cents: number;
  discount_cents: number;
  total_cents: number;
  vat_cents: number;
  vat_rate_bp: number;
  currency: string;
  coupon_applied: boolean;
  coupon_invalid: boolean;
}

/** A marketplace order (Shop P2). */
export interface Order {
  ulid: string;
  status: "pending" | "paid" | "cancelled" | "failed";
  total_cents: number;
  discount_cents?: number;
  vat_cents?: number;
  currency: string;
  paid_at?: string | null;
  created_at?: string | null;
  product_ulid?: string | null;
  items?: { title: string; unit_cents: number; kind: string }[];
}

/** A buyer's purchase entitlement (Shop P2). */
export interface Purchase {
  product: {
    ulid: string | null;
    title: string | null;
    type: ProductType | null;
    store: string | null;
  };
  has_download: boolean;
  purchased_at: string | null;
}

/** The PayFast redirect returned by checkout (Shop P2). */
export interface CheckoutRedirect {
  order: string;
  process_url: string;
  fields: Record<string, string>;
}

/** A single lesson in a course outline (Shop P3). */
export interface CourseOutlineLesson {
  ulid: string;
  title: string;
  minutes: number | null;
  is_preview: boolean;
  has_attachment: boolean;
  is_completed: boolean;
}

/** The browsable curriculum outline for a course product (Shop P3). */
export interface CourseOutline {
  product: { ulid: string; title: string; store: string | null };
  owned: boolean;
  progress: { completed: number; total: number };
  modules: { ulid: string; title: string; lessons: CourseOutlineLesson[] }[];
}

/** Full lesson content once unlocked (Shop P3). */
export interface CourseLesson {
  ulid: string;
  title: string;
  body: string | null;
  video_url: string | null;
  has_attachment: boolean;
  minutes: number | null;
  is_preview: boolean;
  is_completed: boolean;
  next: { ulid: string; title: string } | null;
}

/** A cohort-based programme (V3 · LEARN). */
export interface Masterclass {
  ulid: string;
  title: string;
  description: string;
  facilitator_name: string | null;
  brand_color?: string | null;
  accent_color?: string | null;
  logo_url?: string | null;
  banner_url?: string | null;
  is_sponsored?: boolean;
  sponsor_name?: string | null;
  sponsor_url?: string | null;
  sponsor_blurb?: string | null;
  starts_at: string;
  ends_at: string;
  status: "upcoming" | "active" | "ended";
  capacity: number | null;
  seats_left: number | null;
  participants_count: number;
  enrolled: boolean;
}

/** A masterclass live-stream session (ask #4). */
export interface LiveSession {
  ulid: string;
  status: "idle" | "active" | "ended";
  is_live: boolean;
  title: string | null;
  started_at: string | null;
  playback_url: string | null;
  ingest_url?: string | null; // host only
  stream_key?: string | null; // host only
}

/** A past session's recording (replay) available to watch (ask #4). */
export interface LiveReplay {
  ulid: string;
  title: string | null;
  recording_url: string;
  recorded_at: string | null;
}

/** The live state of a masterclass room for the current viewer (ask #4). */
export interface LiveState {
  enabled: boolean;
  is_host: boolean;
  can_watch: boolean;
  chat_conversation: string | null;
  session: LiveSession | null;
  replays: LiveReplay[];
}

/** Vendor store analytics payload (Shop P4). */
export interface StoreAnalytics {
  days: number;
  series: {
    date: string;
    views: number;
    orders: number;
    gross_cents: number;
    earnings_cents: number;
  }[];
  totals: {
    views: number;
    orders: number;
    gross_cents: number;
    earnings_cents: number;
    conversion_pct: number;
    currency: string;
  };
  top_products: { ulid: string; title: string; views: number }[];
}

/** One turn in an AI Coach conversation (V2 · LEARN). */
export interface CoachMessage {
  id: number;
  role: "user" | "assistant";
  body: string;
  created_at: string | null;
}

/** Streak snapshot (V1 · PROGRESS) — powers the streak chip. */
export interface StreakSnapshot {
  current_days: number;
  ends_today: boolean;
}

/** A curated briefing snippet on the Daily Business Brief (V2 · Feature 7). */
export interface BriefItem {
  ulid: string;
  kind: "tip" | "regulation" | "news" | "resource";
  title: string;
  body: string;
  url: string | null;
  published_at: string | null;
}

/** The Daily Business Brief payload (GET /me/brief). */
export interface DailyBriefData {
  headline: string;
  date: string;
  items: BriefItem[];
}

/** Today's daily challenge + the viewer's completion + streak (GET me/daily). */
export interface DailyChallenge {
  action: { ulid: string; title: string; description: string | null } | null;
  completed: boolean;
  streak: StreakSnapshot;
}

/** A suggested connection (V1 · CONNECT) — a person to meet + why. */
export interface ConnectionSuggestion {
  profile: Profile;
  reason: string;
}

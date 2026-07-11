import type { ComponentType } from "react";

import type { Post, PostType } from "@/lib/api/types";

import { AudioPost } from "./audio-post";
import { BlogPost } from "./blog-post";
import { CheckinPost } from "./checkin-post";
import { EventPost } from "./event-post";
import { ImagePost } from "./image-post";
import { JobPost } from "./job-post";
import { LinkPost } from "./link-post";
import { MagnifierPost } from "./magnifier-post";
import { PollPost } from "./poll-post";
import { PortfolioPost } from "./portfolio-post";
import { QuizPost } from "./quiz-post";
import { QuotePost } from "./quote-post";
import { RepostPost } from "./repost-post";
import { SecretPost } from "./secret-post";
import { TextPost } from "./text-post";
import { TypewriterPost } from "./typewriter-post";
import { UnknownPost } from "./unknown-post";
import { VideoPost } from "./video-post";

/** `detail` is true on the post detail page (full render + live subscriptions). */
export type PostRenderer = ComponentType<{ post: Post; detail?: boolean }>;

const RENDERERS: Record<PostType, PostRenderer> = {
  text: TextPost,
  link: LinkPost,
  image: ImagePost,
  quote: QuotePost,
  repost: RepostPost,
  typewriter: TypewriterPost,
  magnifier: MagnifierPost,
  secret: SecretPost,
  checkin: CheckinPost,
  video: VideoPost,
  audio: AudioPost,
  blog: BlogPost,
  poll: PollPost,
  quiz: QuizPost,
  event: EventPost,
  job: JobPost,
  portfolio: PortfolioPost,
};

/** Human labels for the type badge shown on special post types. */
export const TYPE_BADGES: Partial<Record<PostType, string>> = {
  typewriter: "Typewriter",
  magnifier: "Magnifier",
  secret: "Secret",
  checkin: "Check-in",
  blog: "Article",
  poll: "Poll",
  quiz: "Quiz",
  event: "Event",
  job: "Job",
  portfolio: "Portfolio",
};

export function getPostRenderer(type: string): PostRenderer {
  return RENDERERS[type as PostType] ?? UnknownPost;
}

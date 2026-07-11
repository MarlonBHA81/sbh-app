import type { ComponentType } from "react";

import type { Post, PostType } from "@/lib/api/types";

import { CheckinPost } from "./checkin-post";
import { ImagePost } from "./image-post";
import { LinkPost } from "./link-post";
import { MagnifierPost } from "./magnifier-post";
import { QuotePost } from "./quote-post";
import { RepostPost } from "./repost-post";
import { SecretPost } from "./secret-post";
import { TextPost } from "./text-post";
import { TypewriterPost } from "./typewriter-post";
import { UnknownPost } from "./unknown-post";

export type PostRenderer = ComponentType<{ post: Post }>;

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
};

/** Human labels for the type badge shown on special post types. */
export const TYPE_BADGES: Partial<Record<PostType, string>> = {
  typewriter: "Typewriter",
  magnifier: "Magnifier",
  secret: "Secret",
  checkin: "Check-in",
};

export function getPostRenderer(type: string): PostRenderer {
  return RENDERERS[type as PostType] ?? UnknownPost;
}

import type { Post } from "@/lib/api/types";

import { PostBody } from "./post-body";

export function TextPost({ post }: { post: Post }) {
  if (!post.body) return null;
  return <PostBody text={post.body} />;
}

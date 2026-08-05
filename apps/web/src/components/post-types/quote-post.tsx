import type { Post } from "@/lib/api/types";

import { ParentCard } from "./parent-card";
import { PostBody } from "./post-body";

export function QuotePost({ post }: { post: Post }) {
  return (
    <div className="flex flex-col gap-2">
      {post.body ? <PostBody text={post.body} /> : null}
      <ParentCard post={post.parent} />
    </div>
  );
}

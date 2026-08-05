import { Repeat2 } from "lucide-react";

import type { Post } from "@/lib/api/types";

import { ParentCard } from "./parent-card";

export function RepostPost({ post }: { post: Post }) {
  return (
    <div className="flex flex-col gap-2">
      <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
        <Repeat2 className="size-4" aria-hidden />
        {post.profile.name} reposted
      </p>
      <ParentCard post={post.parent} />
    </div>
  );
}

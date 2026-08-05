import { CircleHelp } from "lucide-react";

import type { Post } from "@/lib/api/types";

export function UnknownPost({ post }: { post: Post }) {
  return (
    <div className="flex items-center gap-2 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
      <CircleHelp className="size-4 shrink-0" aria-hidden />
      This post type ({post.type}) isn&apos;t supported yet. Update the app to
      view it.
    </div>
  );
}

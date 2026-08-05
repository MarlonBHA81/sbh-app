import Link from "next/link";

import type { PostTopic } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Small tappable topic chip linking to the topic page. */
export function TopicChip({
  topic,
  className,
}: {
  topic: PostTopic;
  className?: string;
}) {
  return (
    <Link
      href={`/topics/${encodeURIComponent(topic.slug)}`}
      className={cn(
        "inline-flex max-w-full items-center gap-1 rounded-full border bg-muted/40 px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground",
        className,
      )}
    >
      {topic.icon ? <span aria-hidden>{topic.icon}</span> : null}
      <span className="truncate">{topic.name}</span>
    </Link>
  );
}

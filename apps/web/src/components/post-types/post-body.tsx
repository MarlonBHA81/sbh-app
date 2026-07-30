import Link from "next/link";

import { ExternalLink } from "@/components/ui/external-link";
import { cn } from "@/lib/utils";

const TOKEN_RE = /(https?:\/\/[^\s<]+|@[a-z0-9_]{2,30}|#[\p{L}\p{N}_]{1,50})/giu;

/**
 * Renders a post/comment body with URLs and @mentions linkified and #hashtags
 * visually styled (hashtag pages arrive later).
 */
export function PostBody({
  text,
  className,
}: {
  text: string;
  className?: string;
}) {
  const parts = text.split(TOKEN_RE);

  return (
    <p
      className={cn(
        "text-[15px] leading-relaxed break-words whitespace-pre-wrap",
        className,
      )}
    >
      {parts.map((part, i) => {
        if (/^https?:\/\//i.test(part)) {
          return (
            <ExternalLink
              key={i}
              href={part}
              stopPropagation
              className="text-primary underline-offset-4 hover:underline"
            >
              {part.replace(/^https?:\/\//, "")}
            </ExternalLink>
          );
        }
        if (part.startsWith("@")) {
          return (
            <Link
              key={i}
              href={`/${part.slice(1)}`}
              className="font-medium text-primary underline-offset-4 hover:underline"
              onClick={(event) => event.stopPropagation()}
            >
              {part}
            </Link>
          );
        }
        if (part.startsWith("#")) {
          return (
            <span key={i} className="font-medium text-primary">
              {part}
            </span>
          );
        }
        return part;
      })}
    </p>
  );
}

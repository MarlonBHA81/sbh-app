import { cn } from "@/lib/utils";

const TOKEN_RE = /(https?:\/\/[^\s<]+|@[a-z0-9_]{2,30}|#[\p{L}\p{N}_]{1,50})/giu;

/**
 * Renders a post body with URLs linkified and @mentions / #hashtags
 * visually styled (styling only — mention/hashtag pages arrive later).
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
            <a
              key={i}
              href={part}
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary underline-offset-4 hover:underline"
              onClick={(event) => event.stopPropagation()}
            >
              {part.replace(/^https?:\/\//, "")}
            </a>
          );
        }
        if (part.startsWith("@") || part.startsWith("#")) {
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

import { Link2 } from "lucide-react";

import type { LinkPayload, Post } from "@/lib/api/types";

import { PostBody } from "./post-body";

function domainOf(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, "");
  } catch {
    return url;
  }
}

export function LinkPost({ post }: { post: Post }) {
  const payload = (post.payload ?? {}) as unknown as LinkPayload;
  if (!payload.url) return post.body ? <PostBody text={post.body} /> : null;

  return (
    <div className="flex flex-col gap-2">
      {post.body ? <PostBody text={post.body} /> : null}
      <a
        href={payload.url}
        target="_blank"
        rel="noopener noreferrer"
        onClick={(event) => event.stopPropagation()}
        className="flex flex-col gap-1 rounded-xl border p-3 transition-colors hover:bg-accent/50"
      >
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Link2 className="size-3.5" aria-hidden />
          {domainOf(payload.url)}
        </span>
        <span className="text-sm font-semibold">
          {payload.title || payload.url}
        </span>
        {payload.description ? (
          <span className="line-clamp-2 text-sm text-muted-foreground">
            {payload.description}
          </span>
        ) : null}
      </a>
    </div>
  );
}

import type { BlogPayload, Post } from "@/lib/api/types";

import { TiptapRenderer } from "./tiptap-render";

/**
 * Blog/article post. In feeds (`detail` false) it shows a title + excerpt with
 * a "Read article" affordance. On the detail page it renders the full Tiptap
 * document via the dependency-free walker.
 */
export function BlogPost({ post, detail }: { post: Post; detail?: boolean }) {
  const payload = (post.payload ?? {}) as unknown as BlogPayload;
  const title = payload.title ?? "Untitled article";

  if (detail) {
    return (
      <div className="flex flex-col gap-3">
        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
        <TiptapRenderer doc={payload.doc} />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1.5 rounded-xl border p-4">
      <h2 className="text-lg font-semibold">{title}</h2>
      {payload.excerpt ? (
        <p className="line-clamp-3 text-sm text-muted-foreground">
          {payload.excerpt}
        </p>
      ) : null}
      <span className="mt-1 text-sm font-medium text-primary">
        Read article →
      </span>
    </div>
  );
}

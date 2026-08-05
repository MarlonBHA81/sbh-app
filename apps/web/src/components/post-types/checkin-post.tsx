import { MapPin } from "lucide-react";

import type { CheckinPayload, Post } from "@/lib/api/types";

import { PostBody } from "./post-body";

export function CheckinPost({ post }: { post: Post }) {
  const payload = (post.payload ?? {}) as unknown as CheckinPayload;
  const place = [payload.place_name, payload.city].filter(Boolean).join(", ");

  return (
    <div className="flex flex-col gap-2">
      {place ? (
        <p className="flex items-center gap-2 rounded-xl border bg-accent/40 p-3 text-sm font-medium">
          <MapPin className="size-4 shrink-0 text-primary" aria-hidden />
          <span>
            {place}
            {payload.country_code ? (
              <span className="ml-1 text-xs font-normal text-muted-foreground uppercase">
                {payload.country_code}
              </span>
            ) : null}
          </span>
        </p>
      ) : null}
      {post.body ? <PostBody text={post.body} /> : null}
    </div>
  );
}

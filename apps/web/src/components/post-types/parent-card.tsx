import { BadgeCheck } from "lucide-react";
import Link from "next/link";

import { ProfileAvatar } from "@/components/profile-avatar";
import type { Post } from "@/lib/api/types";
import { relativeTime } from "@/lib/time";
import { cn } from "@/lib/utils";

import { PostBody } from "./post-body";

function parentPreviewText(post: Post): string | null {
  if (post.body) return post.body;
  const payload = (post.payload ?? {}) as Record<string, unknown>;
  if (typeof payload.text === "string") return payload.text;
  if (typeof payload.title === "string") return payload.title;
  if (typeof payload.place_name === "string") return `📍 ${payload.place_name}`;
  return null;
}

/** Compact embedded parent post used by quote and repost renderers. */
export function ParentCard({
  post,
  className,
}: {
  post: Post | null;
  className?: string;
}) {
  if (!post) {
    return (
      <div
        className={cn(
          "rounded-xl border border-dashed p-3 text-sm text-muted-foreground",
          className,
        )}
      >
        This post is unavailable.
      </div>
    );
  }

  const preview = parentPreviewText(post);
  const timestamp = post.published_at ?? post.created_at;

  return (
    <div className={cn("flex flex-col gap-2 rounded-xl border p-3", className)}>
      <div className="flex items-center gap-2 text-sm">
        <ProfileAvatar profile={post.profile} className="size-5" />
        <Link
          href={`/${post.profile.handle}`}
          onClick={(event) => event.stopPropagation()}
          className="flex min-w-0 items-center gap-1 font-semibold hover:underline"
        >
          <span className="truncate">{post.profile.name}</span>
          {post.profile.is_verified ? (
            <BadgeCheck
              className="size-3.5 shrink-0 text-sky-500"
              aria-label="Verified"
            />
          ) : null}
        </Link>
        <span className="truncate text-muted-foreground">
          @{post.profile.handle}
        </span>
        <span className="shrink-0 text-xs text-muted-foreground">
          {relativeTime(timestamp)}
        </span>
      </div>
      {post.sensitive ? (
        <p className="text-sm text-muted-foreground italic">
          Sensitive content
        </p>
      ) : (
        <>
          {preview ? (
            <PostBody text={preview} className="line-clamp-4 text-sm" />
          ) : null}
          {post.media.length > 0 ? (
            <div
              className="overflow-hidden rounded-lg border bg-muted"
              style={{ aspectRatio: "16 / 9" }}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={post.media[0].thumb_url}
                alt=""
                loading="lazy"
                width={post.media[0].width}
                height={post.media[0].height}
                className="size-full object-cover"
              />
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}

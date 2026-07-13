"use client";

import { BadgeCheck, Eye, Heart, MessageCircle, Repeat2 } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";

import type { PublicPost } from "@/lib/api/public-types";
import { formatCount } from "@/lib/reactions";
import { feedTimestamp } from "@/lib/time";
import { cn } from "@/lib/utils";

/**
 * Read-only feed card for logged-out visitors (SEO-crawlable pages).
 * Mirrors the PostCard shell; engagement counts are display-only and any
 * interaction goes through the sign-in CTA rendered by the page.
 */
export function PublicPostCard({
  post,
  linkToDetail = true,
}: {
  post: PublicPost;
  linkToDetail?: boolean;
}) {
  const router = useRouter();
  const timestamp = post.published_at;
  const images = post.media.filter((m) => m.thumb_url || m.url);

  const body = (
    <>
      <header className="flex items-center gap-2.5 bg-slate-wash px-3 py-2.5">
        <Link href={`/${post.profile.handle}`} className="shrink-0">
          {post.profile.avatar_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={post.profile.avatar_url}
              alt=""
              width={36}
              height={36}
              className="size-9 rounded-full object-cover"
            />
          ) : (
            <span className="flex size-9 items-center justify-center rounded-full bg-teal font-heading text-xs font-medium text-white">
              {post.profile.name.slice(0, 2).toUpperCase()}
            </span>
          )}
        </Link>
        <div className="flex min-w-0 flex-1 flex-col">
          <Link
            href={`/${post.profile.handle}`}
            className="flex items-center gap-1.5 hover:underline"
          >
            <span className="truncate font-heading text-sm font-medium">
              {post.profile.name}
            </span>
            {post.profile.is_verified ? (
              <span
                aria-label="Verified"
                role="img"
                className="flex size-4 shrink-0 items-center justify-center rounded-full bg-sage"
              >
                <BadgeCheck className="size-2.5 text-white" strokeWidth={3} aria-hidden />
              </span>
            ) : null}
          </Link>
          {timestamp ? (
            <time dateTime={timestamp} className="text-xs text-text-secondary">
              {feedTimestamp(timestamp)}
            </time>
          ) : null}
        </div>
      </header>

      <div className="flex flex-col gap-3 p-3">
        {post.sensitive ? (
          <p className="rounded-(--radius-media) bg-muted px-4 py-8 text-center text-sm text-text-secondary">
            Sensitive content — sign in to view.
          </p>
        ) : (
          <>
            {post.body ? (
              <p className="text-base leading-relaxed font-medium whitespace-pre-wrap">
                {post.body}
              </p>
            ) : null}
            {images.length > 0 ? (
              <div
                className={cn(
                  "grid gap-0.5 overflow-hidden rounded-(--radius-media) border border-warmgray",
                  images.length > 1 && "grid-cols-2",
                )}
              >
                {images.slice(0, 4).map((media, index) => (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    key={index}
                    src={media.thumb_url ?? media.url ?? ""}
                    alt=""
                    loading="lazy"
                    className="aspect-square size-full object-cover"
                  />
                ))}
              </div>
            ) : null}
          </>
        )}

        {post.topics.length > 0 ? (
          <div className="flex flex-wrap gap-1.5">
            {post.topics.map((topic) => (
              <span
                key={topic.slug}
                className="rounded-full border border-warmgray px-2.5 py-1 text-xs text-text-secondary"
              >
                {topic.name}
              </span>
            ))}
          </div>
        ) : null}

        <footer className="flex items-center gap-4 text-xs text-text-secondary tabular-nums">
          <span className="flex items-center gap-1.5">
            <Heart className="size-4" aria-hidden />
            {formatCount(post.likes_count) || "0"}
          </span>
          <span className="flex items-center gap-1.5">
            <MessageCircle className="size-4" aria-hidden />
            {formatCount(post.comments_count) || "0"}
          </span>
          <span className="flex items-center gap-1.5">
            <Repeat2 className="size-4" aria-hidden />
            {formatCount(post.reposts_count) || "0"}
          </span>
          <span className="ms-auto flex items-center gap-1.5">
            <Eye className="size-4" aria-hidden />
            {formatCount(post.views_count) || "0"}
          </span>
        </footer>
      </div>
    </>
  );

  const shell =
    "flex flex-col overflow-hidden rounded-(--radius-card) border border-warmgray bg-card text-card-foreground shadow-card";

  if (!linkToDetail) return <article className={shell}>{body}</article>;

  // Whole-card navigation without nesting anchors (the header contains
  // profile links); clicks landing on real links pass through.
  return (
    <article
      className={cn(shell, "cursor-pointer transition-colors hover:border-warmgray-tint")}
      onClick={(event) => {
        if ((event.target as HTMLElement).closest("a")) return;
        if (window.getSelection()?.toString()) return;
        router.push(`/p/${post.ulid}`);
      }}
    >
      {body}
    </article>
  );
}

/** "Sign in to interact" call-to-action card for logged-out visitors. */
export function GuestCta({ message }: { message: string }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-(--radius-card) border border-warmgray bg-card p-6 text-center shadow-card">
      <p className="text-sm text-text-secondary">{message}</p>
      <div className="flex gap-2">
        <Link
          href="/login"
          className="flex h-11 items-center rounded-full border border-warmgray bg-card px-5 font-heading text-[13px] font-medium text-text-primary transition-colors hover:bg-accent active:scale-[0.98]"
        >
          Sign in
        </Link>
        <Link
          href="/register"
          className="flex h-11 items-center rounded-full bg-teal px-5 font-heading text-[13px] font-medium text-white transition-colors hover:bg-teal-tint active:scale-[0.98]"
        >
          Join SBH Community
        </Link>
      </div>
    </div>
  );
}

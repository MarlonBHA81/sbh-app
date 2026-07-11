"use client";

import {
  ArrowBigDown,
  ArrowBigUp,
  BadgeCheck,
  Eye,
  EyeOff,
  Heart,
  MessageCircle,
  Repeat2,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createElement, useRef, useState } from "react";
import { toast } from "sonner";

import { useComposer } from "@/components/composer/composer-provider";
import { ProfileAvatar } from "@/components/profile-avatar";
import { TopicChip } from "@/components/topics/topic-chip";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import * as api from "@/lib/api/client";
import type { Post, Vote } from "@/lib/api/types";
import { applyVote, formatCount, formatNetVotes } from "@/lib/reactions";
import { relativeTime } from "@/lib/time";
import { cn } from "@/lib/utils";

import { getPostRenderer, TYPE_BADGES } from "./registry";

/** Absolute counts pushed from a live `ReactionUpdated` broadcast. */
export interface LiveCounts {
  likes_count: number;
  upvotes_count: number;
  downvotes_count: number;
}

function VotePair({
  vote,
  net,
  onVote,
}: {
  vote: Vote;
  net: number;
  onVote: (value: Exclude<Vote, 0>) => void;
}) {
  return (
    <div className="flex items-center rounded-lg text-muted-foreground">
      <button
        type="button"
        aria-label="Upvote"
        aria-pressed={vote === 1}
        onClick={() => onVote(1)}
        className={cn(
          "flex min-h-11 items-center rounded-lg px-1.5 transition-colors hover:text-emerald-600",
          vote === 1 && "text-emerald-600",
        )}
      >
        <ArrowBigUp
          className={cn("size-[19px]", vote === 1 && "fill-current")}
          aria-hidden
        />
      </button>
      <span
        className={cn(
          "min-w-4 text-center text-xs font-medium tabular-nums",
          vote === 1 && "text-emerald-600",
          vote === -1 && "text-red-600",
        )}
      >
        {formatNetVotes(net)}
      </span>
      <button
        type="button"
        aria-label="Downvote"
        aria-pressed={vote === -1}
        onClick={() => onVote(-1)}
        className={cn(
          "flex min-h-11 items-center rounded-lg px-1.5 transition-colors hover:text-red-600",
          vote === -1 && "text-red-600",
        )}
      >
        <ArrowBigDown
          className={cn("size-[19px]", vote === -1 && "fill-current")}
          aria-hidden
        />
      </button>
    </div>
  );
}

function ActionButton({
  icon: Icon,
  count,
  label,
  onClick,
  className,
  iconClassName,
  active,
}: {
  icon: typeof Heart;
  count: number;
  label: string;
  onClick?: () => void;
  className?: string;
  iconClassName?: string;
  active?: boolean;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      aria-pressed={active}
      onClick={onClick}
      className={cn(
        "flex min-h-11 min-w-11 items-center gap-1.5 rounded-lg px-2 text-muted-foreground transition-colors hover:text-foreground",
        className,
      )}
    >
      <Icon className={cn("size-[18px]", iconClassName)} aria-hidden />
      <span className="text-xs tabular-nums">{formatCount(count)}</span>
    </button>
  );
}

export function PostCard({
  post,
  linkToDetail = true,
  liveCounts = null,
}: {
  post: Post;
  /** Whether the card navigates to the post detail page on click. */
  linkToDetail?: boolean;
  /** Absolute counts from a live broadcast (detail page only). */
  liveCounts?: LiveCounts | null;
}) {
  const router = useRouter();
  const { openComposer, notifyPostsMutated } = useComposer();
  const [showSensitive, setShowSensitive] = useState(false);
  const [repostConfirmOpen, setRepostConfirmOpen] = useState(false);
  const [reposting, setReposting] = useState(false);

  // Optimistic reaction state (seeded once from props; live counts are
  // absolute overrides pushed via `liveCounts`).
  const [liked, setLiked] = useState(post.liked);
  const [likesCount, setLikesCount] = useState(post.likes_count);
  const [vote, setVote] = useState<Vote>(post.my_vote);
  const [upCount, setUpCount] = useState(post.upvotes_count);
  const [downCount, setDownCount] = useState(post.downvotes_count);
  const [heartPop, setHeartPop] = useState(false);
  const [seenLive, setSeenLive] = useState<LiveCounts | null>(null);
  const likeBusy = useRef(false);
  const voteBusy = useRef(false);

  // Live counts are authoritative server totals — apply (during render, guarded
  // against re-applying the same broadcast) without touching the viewer's own
  // liked/vote flags.
  if (liveCounts && liveCounts !== seenLive) {
    setSeenLive(liveCounts);
    setLikesCount(liveCounts.likes_count);
    setUpCount(liveCounts.upvotes_count);
    setDownCount(liveCounts.downvotes_count);
  }

  const content = createElement(getPostRenderer(post.type), { post });
  const badge = TYPE_BADGES[post.type];
  const timestamp = post.published_at ?? post.created_at;
  const detailHref = `/p/${post.ulid}`;
  // Reposting a repost targets the original post.
  const repostTarget = post.type === "repost" ? (post.parent ?? post) : post;

  const hidden = post.sensitive && !showSensitive;

  async function toggleLike() {
    if (likeBusy.current) return;
    likeBusy.current = true;
    const previous = { liked, likesCount };
    const nextLiked = !liked;
    setLiked(nextLiked);
    setLikesCount((c) => Math.max(0, c + (nextLiked ? 1 : -1)));
    if (nextLiked) {
      setHeartPop(true);
      window.setTimeout(() => setHeartPop(false), 260);
    }
    try {
      if (nextLiked) await api.post(`/api/v1/posts/${post.ulid}/like`);
      else await api.del(`/api/v1/posts/${post.ulid}/like`);
    } catch {
      setLiked(previous.liked);
      setLikesCount(previous.likesCount);
    } finally {
      likeBusy.current = false;
    }
  }

  async function castVote(value: Exclude<Vote, 0>) {
    if (voteBusy.current) return;
    voteBusy.current = true;
    const previous = { vote, upCount, downCount };
    const result = applyVote(vote, value, {
      upvotes_count: upCount,
      downvotes_count: downCount,
    });
    setVote(result.vote);
    setUpCount(result.counts.upvotes_count);
    setDownCount(result.counts.downvotes_count);
    try {
      await api.post(`/api/v1/posts/${post.ulid}/vote`, { value: result.vote });
    } catch {
      setVote(previous.vote);
      setUpCount(previous.upCount);
      setDownCount(previous.downCount);
    } finally {
      voteBusy.current = false;
    }
  }

  async function repost() {
    if (reposting) return;
    setReposting(true);
    try {
      await api.post<{ data: Post }>("/api/v1/posts", {
        type: "repost",
        parent_post_id: repostTarget.ulid,
        visibility: "public",
        status: "published",
        sensitive: repostTarget.sensitive,
      });
      toast.success("Reposted");
      notifyPostsMutated();
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't repost",
      );
    } finally {
      setReposting(false);
      setRepostConfirmOpen(false);
    }
  }

  // Whole-card navigation to the detail page, ignoring clicks that land on
  // interactive descendants (links, buttons, menu items, media) or during a
  // text selection.
  function handleCardClick(event: React.MouseEvent<HTMLElement>) {
    if (!linkToDetail) return;
    const target = event.target as HTMLElement;
    if (target.closest("a,button,[role='menuitem'],[data-no-nav]")) return;
    if (window.getSelection()?.toString()) return;
    router.push(detailHref);
  }

  return (
    <article
      onClick={handleCardClick}
      className={cn(
        "flex flex-col gap-3 rounded-xl border bg-card p-4 text-card-foreground",
        linkToDetail && "cursor-pointer transition-colors hover:bg-accent/30",
      )}
    >
      <header className="flex items-center gap-3">
        <Link
          href={`/${post.profile.handle}`}
          aria-label={`${post.profile.name} (@${post.profile.handle})`}
        >
          <ProfileAvatar profile={post.profile} className="size-10" />
        </Link>
        <div className="flex min-w-0 flex-1 flex-col">
          <Link
            href={`/${post.profile.handle}`}
            className="flex items-center gap-1 hover:underline"
          >
            <span className="truncate text-sm font-semibold">
              {post.profile.name}
            </span>
            {post.profile.is_verified ? (
              <BadgeCheck
                className="size-4 shrink-0 text-sky-500"
                aria-label="Verified"
              />
            ) : null}
          </Link>
          <span className="flex items-center gap-1 truncate text-xs text-muted-foreground">
            @{post.profile.handle}
            <span aria-hidden>·</span>
            {linkToDetail ? (
              <Link
                href={detailHref}
                className="hover:underline"
                aria-label="View post"
              >
                <time dateTime={timestamp}>{relativeTime(timestamp)}</time>
              </Link>
            ) : (
              <time dateTime={timestamp}>{relativeTime(timestamp)}</time>
            )}
          </span>
        </div>
        {badge ? (
          <Badge variant="secondary" className="shrink-0 text-[10px]">
            {badge}
          </Badge>
        ) : null}
      </header>

      {hidden ? (
        <div className="relative overflow-hidden rounded-xl">
          <div
            className="pointer-events-none min-h-32 opacity-60 blur-xl select-none"
            aria-hidden
          >
            {content}
          </div>
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted/60">
            <EyeOff className="size-5 text-muted-foreground" aria-hidden />
            <p className="text-sm font-medium">Sensitive content</p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-9"
              onClick={() => setShowSensitive(true)}
            >
              Show
            </Button>
          </div>
        </div>
      ) : (
        content
      )}

      {post.topics && post.topics.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {post.topics.map((topic) => (
            <TopicChip key={topic.id} topic={topic} />
          ))}
        </div>
      ) : null}

      <footer className="-mx-2 -mb-2 flex items-center justify-between">
        <ActionButton
          icon={Heart}
          count={likesCount}
          label={liked ? "Unlike" : "Like"}
          active={liked}
          onClick={() => void toggleLike()}
          className={cn(liked && "text-rose-500 hover:text-rose-500")}
          iconClassName={cn(
            "transition-transform",
            liked && "fill-current",
            heartPop && "scale-125",
          )}
        />
        <VotePair
          vote={vote}
          net={upCount - downCount}
          onVote={(value) => void castVote(value)}
        />
        <ActionButton
          icon={MessageCircle}
          count={post.comments_count}
          label="Comments"
          onClick={() => router.push(detailHref)}
        />
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              type="button"
              aria-label="Repost or quote"
              className="flex min-h-11 min-w-11 items-center gap-1.5 rounded-lg px-2 text-muted-foreground transition-colors hover:text-foreground"
            >
              <Repeat2 className="size-[18px]" aria-hidden />
              <span className="text-xs tabular-nums">
                {formatCount(post.reposts_count)}
              </span>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="center">
            <DropdownMenuItem
              className="min-h-11 gap-3"
              onSelect={() => setRepostConfirmOpen(true)}
            >
              <Repeat2 className="size-4" aria-hidden />
              Repost
            </DropdownMenuItem>
            <DropdownMenuItem
              className="min-h-11 gap-3"
              onSelect={() => openComposer({ quoteParent: repostTarget })}
            >
              <MessageCircle className="size-4" aria-hidden />
              Quote
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <ActionButton icon={Eye} count={post.views_count} label="Views" />
      </footer>

      <AlertDialog open={repostConfirmOpen} onOpenChange={setRepostConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Repost this?</AlertDialogTitle>
            <AlertDialogDescription>
              @{repostTarget.profile.handle}&apos;s post will be shared with
              your followers.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11"
              disabled={reposting}
              onClick={(event) => {
                event.preventDefault();
                void repost();
              }}
            >
              {reposting ? "Reposting…" : "Repost"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </article>
  );
}

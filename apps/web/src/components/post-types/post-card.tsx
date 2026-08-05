"use client";

import {
  ArrowBigDown,
  ArrowBigUp,
  BadgeCheck,
  Ban,
  Eye,
  EyeOff,
  Flag,
  Heart,
  Link2,
  Megaphone,
  MessageCircle,
  MoreHorizontal,
  Repeat2,
  Trash2,
  VolumeX,
} from "lucide-react";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { createElement, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { usePromote } from "@/components/ads/promote-provider";
import { useComposer } from "@/components/composer/composer-provider";
import { ProfileAvatar } from "@/components/profile-avatar";
import { ReportDialog } from "@/components/safety/report-dialog";
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
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  trackCampaignClick,
  trackCampaignImpression,
  trackCampaignLinkClick,
} from "@/lib/ads/track";
import * as api from "@/lib/api/client";
import type { Post, Vote } from "@/lib/api/types";
import { applyVote, formatCount, formatNetVotes } from "@/lib/reactions";
import { blockProfile, muteProfile } from "@/lib/safety";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useModerationStore } from "@/lib/stores/moderation-store";
import { feedTimestamp } from "@/lib/time";
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
    <div className="flex min-h-11 items-center rounded-full border border-warmgray bg-card text-text-secondary">
      <button
        type="button"
        aria-label="Upvote"
        aria-pressed={vote === 1}
        onClick={() => onVote(1)}
        className={cn(
          "flex h-full min-h-11 items-center rounded-s-full ps-2 pe-0.5 transition-colors hover:text-sage-tint active:scale-[0.98]",
          vote === 1 && "text-sage-tint",
        )}
      >
        <ArrowBigUp
          className={cn("size-[18px]", vote === 1 && "fill-current")}
          aria-hidden
        />
      </button>
      <span
        className={cn(
          "min-w-4 text-center text-xs font-medium tabular-nums",
          vote === 1 && "text-sage-tint",
          vote === -1 && "text-destructive",
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
          "flex h-full min-h-11 items-center rounded-e-full ps-0.5 pe-2 transition-colors hover:text-destructive active:scale-[0.98]",
          vote === -1 && "text-destructive",
        )}
      >
        <ArrowBigDown
          className={cn("size-[18px]", vote === -1 && "fill-current")}
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
        "flex min-h-11 items-center gap-1 rounded-full border border-warmgray bg-card px-2.5 text-xs text-text-secondary transition-colors hover:bg-accent hover:text-text-primary active:scale-[0.98]",
        className,
      )}
    >
      <Icon className={cn("size-4", iconClassName)} aria-hidden />
      <span className="tabular-nums">{formatCount(count)}</span>
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
  const t = useTranslations("post");
  const tf = useTranslations("feed");
  const { openComposer, notifyPostsMutated } = useComposer();
  const { openPromote } = usePromote();
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const showSensitivePref = useAuthStore((s) =>
    Boolean(s.user?.settings?.show_sensitive),
  );
  const hideProfile = useModerationStore((s) => s.hideProfile);
  const [showSensitive, setShowSensitive] = useState(false);
  const [repostConfirmOpen, setRepostConfirmOpen] = useState(false);
  const [reposting, setReposting] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);
  const [muteConfirmOpen, setMuteConfirmOpen] = useState(false);
  const [blockConfirmOpen, setBlockConfirmOpen] = useState(false);
  const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
  const [moderating, setModerating] = useState(false);
  const [deleted, setDeleted] = useState(false);

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
  const articleRef = useRef<HTMLElement | null>(null);

  // Promoted posts: track an impression once the card is on screen (deduped
  // per campaign for the session).
  const promoted = Boolean(post.promoted && post.campaign_ulid);
  const campaignUlid = post.campaign_ulid ?? null;
  useEffect(() => {
    if (!promoted || !campaignUlid) return;
    const node = articleRef.current;
    if (!node || typeof IntersectionObserver === "undefined") {
      trackCampaignImpression(campaignUlid);
      return;
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          trackCampaignImpression(campaignUlid);
          observer.disconnect();
        }
      },
      { threshold: 0.5 },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [promoted, campaignUlid]);

  const trackPromotedClick = () => {
    if (promoted && campaignUlid) trackCampaignClick(campaignUlid);
  };

  // Outbound link clicks anywhere in a promoted card (capture phase, so it
  // sees clicks on anchors the card-navigation handler ignores).
  const trackPromotedLinkClick = (event: React.MouseEvent<HTMLElement>) => {
    if (!promoted || !campaignUlid) return;
    const target = event.target as HTMLElement;
    const href = target.closest("a[href]")?.getAttribute("href") ?? "";
    if (/^https?:\/\//.test(href)) trackCampaignLinkClick(campaignUlid);
  };

  // Live counts are authoritative server totals — apply (during render, guarded
  // against re-applying the same broadcast) without touching the viewer's own
  // liked/vote flags.
  if (liveCounts && liveCounts !== seenLive) {
    setSeenLive(liveCounts);
    setLikesCount(liveCounts.likes_count);
    setUpCount(liveCounts.upvotes_count);
    setDownCount(liveCounts.downvotes_count);
  }

  const content = createElement(getPostRenderer(post.type), {
    post,
    detail: !linkToDetail,
  });
  const badge = TYPE_BADGES[post.type];
  const timestamp = post.published_at ?? post.created_at;
  const detailHref = `/p/${post.ulid}`;
  // Reposting a repost targets the original post.
  const repostTarget = post.type === "repost" ? (post.parent ?? post) : post;

  const isOwnPost = activeUlid !== null && post.profile.ulid === activeUlid;
  const hidden = post.sensitive && !showSensitive && !showSensitivePref;
  const postUrl =
    typeof window !== "undefined"
      ? `${window.location.origin}${detailHref}`
      : detailHref;

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(postUrl);
      toast.success("Link copied");
    } catch {
      toast.error("Couldn't copy the link");
    }
  }

  async function confirmMute() {
    if (moderating) return;
    setModerating(true);
    try {
      await muteProfile(post.profile.handle);
      hideProfile(post.profile.ulid);
      toast.success(`Muted @${post.profile.handle}`);
      setMuteConfirmOpen(false);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't mute",
      );
    } finally {
      setModerating(false);
    }
  }

  async function confirmBlock() {
    if (moderating) return;
    setModerating(true);
    try {
      await blockProfile(post.profile.handle);
      hideProfile(post.profile.ulid);
      toast.success(`Blocked @${post.profile.handle}`);
      setBlockConfirmOpen(false);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't block",
      );
    } finally {
      setModerating(false);
    }
  }

  async function confirmDelete() {
    if (moderating) return;
    setModerating(true);
    try {
      await api.del(`/api/v1/posts/${post.ulid}`);
      toast.success("Post deleted");
      setDeleteConfirmOpen(false);
      if (linkToDetail) {
        setDeleted(true);
        notifyPostsMutated();
      } else {
        router.push("/home");
      }
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't delete",
      );
    } finally {
      setModerating(false);
    }
  }

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
    trackPromotedClick();
    router.push(detailHref);
  }

  if (deleted) return null;

  return (
    <article
      ref={articleRef}
      onClick={handleCardClick}
      onClickCapture={trackPromotedLinkClick}
      className={cn(
        "flex flex-col overflow-hidden rounded-(--radius-card) border border-warmgray bg-card text-card-foreground shadow-card",
        linkToDetail &&
          "sbh-lift cursor-pointer hover:border-warmgray-tint",
      )}
    >
      <header className="flex items-center gap-2.5 bg-slate-wash px-3 py-2.5">
        <Link
          href={`/${post.profile.handle}`}
          aria-label={`${post.profile.name} (@${post.profile.handle})`}
        >
          <ProfileAvatar profile={post.profile} className="size-9" />
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
                <BadgeCheck
                  className="size-2.5 text-white"
                  strokeWidth={3}
                  aria-hidden
                />
              </span>
            ) : null}
          </Link>
          <span className="flex items-center gap-1.5 truncate text-xs text-text-secondary">
            {linkToDetail ? (
              <Link
                href={detailHref}
                className="hover:underline"
                aria-label="View post"
                onClick={trackPromotedClick}
              >
                <time dateTime={timestamp}>{feedTimestamp(timestamp)}</time>
              </Link>
            ) : (
              <time dateTime={timestamp}>{feedTimestamp(timestamp)}</time>
            )}
            {promoted ? (
              <span className="inline-flex shrink-0 items-center rounded-full bg-warmgray px-2 py-0.5 text-[11px] font-medium text-text-secondary">
                {tf("promoted")}
              </span>
            ) : null}
          </span>
        </div>
        {badge ? (
          <Badge
            variant="secondary"
            className={cn(
              "shrink-0 rounded-full text-[10px]",
              post.type === "job" && "bg-plum text-white",
            )}
          >
            {badge}
          </Badge>
        ) : null}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              type="button"
              aria-label="More options"
              className="-me-2 flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
              <MoreHorizontal className="size-5" aria-hidden />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-52">
            <DropdownMenuItem
              className="min-h-11 gap-3"
              onSelect={() => void copyLink()}
            >
              <Link2 className="size-4" aria-hidden />
              {t("copyLink")}
            </DropdownMenuItem>
            {isOwnPost ? (
              <>
                {isAdmin &&
                post.status === "published" &&
                post.visibility === "public" &&
                post.type !== "repost" ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      className="min-h-11 gap-3"
                      onSelect={() => openPromote(post)}
                    >
                      <Megaphone className="size-4" aria-hidden />
                      {t("promote")}
                    </DropdownMenuItem>
                  </>
                ) : null}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  className="min-h-11 gap-3"
                  onSelect={() => setDeleteConfirmOpen(true)}
                >
                  <Trash2 className="size-4" aria-hidden />
                  {t("delete")}
                </DropdownMenuItem>
              </>
            ) : (
              <>
                <DropdownMenuItem
                  className="min-h-11 gap-3"
                  onSelect={() => setReportOpen(true)}
                >
                  <Flag className="size-4" aria-hidden />
                  {t("report")}
                </DropdownMenuItem>
                <DropdownMenuItem
                  className="min-h-11 gap-3"
                  onSelect={() => setMuteConfirmOpen(true)}
                >
                  <VolumeX className="size-4" aria-hidden />
                  {t("mute")} @{post.profile.handle}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  className="min-h-11 gap-3"
                  onSelect={() => setBlockConfirmOpen(true)}
                >
                  <Ban className="size-4" aria-hidden />
                  {t("block")} @{post.profile.handle}
                </DropdownMenuItem>
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      </header>

      <div className="flex flex-col gap-3 p-3">
      {hidden ? (
        <div className="relative overflow-hidden rounded-(--radius-media)">
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

      <footer className="flex flex-wrap items-center gap-2">
        <ActionButton
          icon={Heart}
          count={likesCount}
          label={liked ? "Unlike" : t("like")}
          active={liked}
          onClick={() => void toggleLike()}
          className={cn(liked && "border-plum/30 text-plum hover:text-plum")}
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
          label={t("comment")}
          onClick={() => {
            trackPromotedClick();
            router.push(detailHref);
          }}
        />
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              type="button"
              aria-label="Repost or quote"
              className="flex min-h-11 items-center gap-1 rounded-full border border-warmgray bg-card px-2.5 text-xs text-text-secondary transition-colors hover:bg-accent hover:text-text-primary active:scale-[0.98]"
            >
              <Repeat2 className="size-4" aria-hidden />
              <span className="tabular-nums">
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
              {t("repost")}
            </DropdownMenuItem>
            <DropdownMenuItem
              className="min-h-11 gap-3"
              onSelect={() => openComposer({ quoteParent: repostTarget })}
            >
              <MessageCircle className="size-4" aria-hidden />
              {t("quote")}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <span className="ms-auto flex min-h-11 items-center gap-1 ps-1 text-xs text-text-secondary tabular-nums">
          <Eye className="size-4" aria-hidden />
          <span className="sr-only">Views</span>
          {formatCount(post.views_count)}
        </span>
      </footer>
      </div>

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

      <ReportDialog
        open={reportOpen}
        onOpenChange={setReportOpen}
        reportableType="post"
        reportableUlid={post.ulid}
        contextLabel={`@${post.profile.handle}'s post`}
      />

      <AlertDialog open={muteConfirmOpen} onOpenChange={setMuteConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Mute @{post.profile.handle}?</AlertDialogTitle>
            <AlertDialogDescription>
              You won&apos;t see their posts in your feeds. They won&apos;t be
              notified, and you can unmute them any time from Settings.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11"
              disabled={moderating}
              onClick={(event) => {
                event.preventDefault();
                void confirmMute();
              }}
            >
              {moderating ? "Muting…" : "Mute"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={blockConfirmOpen} onOpenChange={setBlockConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Block @{post.profile.handle}?</AlertDialogTitle>
            <AlertDialogDescription>
              They won&apos;t be able to follow you or see your posts, and you
              won&apos;t see theirs. Any follow between you will be removed.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={moderating}
              onClick={(event) => {
                event.preventDefault();
                void confirmBlock();
              }}
            >
              {moderating ? "Blocking…" : "Block"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this post?</AlertDialogTitle>
            <AlertDialogDescription>
              This can&apos;t be undone. Your post will be permanently removed.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={moderating}
              onClick={(event) => {
                event.preventDefault();
                void confirmDelete();
              }}
            >
              {moderating ? "Deleting…" : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </article>
  );
}

"use client";

import {
  ArrowBigDown,
  ArrowBigUp,
  BadgeCheck,
  Flag,
  Heart,
  MessageSquare,
  MoreHorizontal,
  Sparkles,
  Trash2,
} from "lucide-react";
import Link from "next/link";
import { useRef, useState } from "react";
import { toast } from "sonner";

import { PostBody } from "@/components/post-types/post-body";
import { ProfileAvatar } from "@/components/profile-avatar";
import { ReportDialog } from "@/components/safety/report-dialog";
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import * as api from "@/lib/api/client";
import type { Comment, Paginated, Vote } from "@/lib/api/types";
import { applyVote, formatCount, formatNetVotes } from "@/lib/reactions";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { relativeTime } from "@/lib/time";
import { cn, withParam } from "@/lib/utils";

import { CommentComposer } from "./comment-composer";

const MAX_DEPTH = 3;

function isTombstone(comment: Comment): boolean {
  return comment.body === "[deleted]";
}

export function CommentItem({
  comment,
  postUlid,
  postAuthorUlid,
}: {
  comment: Comment;
  postUlid: string;
  /** Post author's profile ulid — enables delete on your own post's comments. */
  postAuthorUlid: string;
}) {
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);

  const [liked, setLiked] = useState(comment.liked);
  const [likesCount, setLikesCount] = useState(comment.likes_count);
  const [vote, setVote] = useState<Vote>(comment.my_vote);
  const [upCount, setUpCount] = useState(comment.upvotes_count);
  const [downCount, setDownCount] = useState(comment.downvotes_count);
  const likeBusy = useRef(false);
  const voteBusy = useRef(false);

  const [isHelpful, setIsHelpful] = useState(comment.is_helpful);
  const helpfulBusy = useRef(false);

  const [tombstoned, setTombstoned] = useState(isTombstone(comment));
  const [replyOpen, setReplyOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [reportOpen, setReportOpen] = useState(false);

  const [replies, setReplies] = useState<Comment[]>(comment.replies ?? []);
  const [repliesCount, setRepliesCount] = useState(comment.replies_count);
  const [repliesCursor, setRepliesCursor] = useState<string | null>(null);
  const [fetchedReplies, setFetchedReplies] = useState(false);
  const [loadingReplies, setLoadingReplies] = useState(false);

  const canReply = comment.depth < MAX_DEPTH && !tombstoned;
  const canDelete =
    !tombstoned &&
    activeUlid !== null &&
    (comment.profile.ulid === activeUlid || postAuthorUlid === activeUlid);
  const canReport =
    !tombstoned && activeUlid !== null && comment.profile.ulid !== activeUlid;
  const hasMenu = canReport || canDelete;
  // Only the post author can mark helpful, and never their own comment.
  const canMarkHelpful =
    !tombstoned &&
    activeUlid !== null &&
    postAuthorUlid === activeUlid &&
    comment.profile.ulid !== activeUlid;

  const hasMore = fetchedReplies
    ? repliesCursor !== null
    : replies.length < repliesCount;
  const remaining = Math.max(0, repliesCount - replies.length);

  async function toggleLike() {
    if (likeBusy.current || tombstoned) return;
    likeBusy.current = true;
    const previous = { liked, likesCount };
    const nextLiked = !liked;
    setLiked(nextLiked);
    setLikesCount((c) => Math.max(0, c + (nextLiked ? 1 : -1)));
    try {
      if (nextLiked) await api.post(`/api/v1/comments/${comment.ulid}/like`);
      else await api.del(`/api/v1/comments/${comment.ulid}/like`);
    } catch {
      setLiked(previous.liked);
      setLikesCount(previous.likesCount);
    } finally {
      likeBusy.current = false;
    }
  }

  async function castVote(value: Exclude<Vote, 0>) {
    if (voteBusy.current || tombstoned) return;
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
      await api.post(`/api/v1/comments/${comment.ulid}/vote`, {
        value: result.vote,
      });
    } catch {
      setVote(previous.vote);
      setUpCount(previous.upCount);
      setDownCount(previous.downCount);
    } finally {
      voteBusy.current = false;
    }
  }

  async function loadReplies() {
    if (loadingReplies) return;
    setLoadingReplies(true);
    try {
      const path = `/api/v1/comments/${comment.ulid}/replies`;
      const url = repliesCursor ? withParam(path, "cursor", repliesCursor) : path;
      const res = await api.get<Paginated<Comment>>(url);
      setReplies((prev) => {
        const seen = new Set(prev.map((r) => r.ulid));
        return [...prev, ...res.data.filter((r) => !seen.has(r.ulid))];
      });
      setRepliesCursor(res.meta.next_cursor);
      setFetchedReplies(true);
    } catch {
      // Non-fatal; the button stays available to retry.
    } finally {
      setLoadingReplies(false);
    }
  }

  function handleReplyCreated(created: Comment) {
    // Replies read oldest-first, so newly created replies go at the end.
    setReplies((prev) =>
      prev.some((r) => r.ulid === created.ulid) ? prev : [...prev, created],
    );
    setRepliesCount((c) => c + 1);
    setReplyOpen(false);
  }

  async function toggleHelpful() {
    if (helpfulBusy.current) return;
    helpfulBusy.current = true;
    const next = !isHelpful;
    setIsHelpful(next);
    try {
      if (next) await api.post(`/api/v1/comments/${comment.ulid}/helpful`);
      else await api.del(`/api/v1/comments/${comment.ulid}/helpful`);
    } catch {
      setIsHelpful(!next);
      toast.error("Couldn't update — try again.");
    } finally {
      helpfulBusy.current = false;
    }
  }

  async function confirmDelete() {
    try {
      await api.del(`/api/v1/comments/${comment.ulid}`);
      setTombstoned(true);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't delete",
      );
    } finally {
      setDeleteOpen(false);
    }
  }

  const net = upCount - downCount;

  return (
    <div className="flex flex-col gap-2">
      <div className="flex gap-2">
        <Link href={`/${comment.profile.handle}`} className="mt-0.5 shrink-0">
          <ProfileAvatar profile={comment.profile} className="size-8" />
        </Link>
        <div className="flex min-w-0 flex-1 flex-col gap-1">
          <div className="flex flex-wrap items-center gap-x-1.5 text-xs">
            <Link
              href={`/${comment.profile.handle}`}
              className="flex items-center gap-1 font-semibold hover:underline"
            >
              <span className="truncate">{comment.profile.name}</span>
              {comment.profile.is_verified ? (
                <BadgeCheck
                  className="size-3.5 shrink-0 text-sky-500"
                  aria-label="Verified"
                />
              ) : null}
            </Link>
            <span className="text-muted-foreground">
              @{comment.profile.handle}
            </span>
            <span className="text-muted-foreground" aria-hidden>
              ·
            </span>
            <time
              dateTime={comment.created_at}
              className="text-muted-foreground"
            >
              {relativeTime(comment.created_at)}
            </time>
            {isHelpful ? (
              <span className="flex items-center gap-1 rounded-full bg-teal/12 px-1.5 py-0.5 text-[10px] font-medium text-teal-text">
                <Sparkles className="size-3" aria-hidden />
                Helpful
              </span>
            ) : null}
          </div>

          {tombstoned ? (
            <p className="text-sm text-muted-foreground italic">
              [comment deleted]
            </p>
          ) : (
            <PostBody text={comment.body} className="text-sm" />
          )}

          {tombstoned ? null : (
            <div className="-ml-1.5 flex items-center gap-1 text-muted-foreground">
              <button
                type="button"
                aria-label={liked ? "Unlike" : "Like"}
                aria-pressed={liked}
                onClick={() => void toggleLike()}
                className={cn(
                  "flex min-h-9 items-center gap-1 rounded-md px-1.5 text-xs transition-colors hover:text-rose-500",
                  liked && "text-rose-500",
                )}
              >
                <Heart
                  className={cn("size-4", liked && "fill-current")}
                  aria-hidden
                />
                {formatCount(likesCount)}
              </button>

              <div className="flex items-center">
                <button
                  type="button"
                  aria-label="Upvote"
                  aria-pressed={vote === 1}
                  onClick={() => void castVote(1)}
                  className={cn(
                    "flex min-h-9 items-center rounded-md px-1 transition-colors hover:text-emerald-600",
                    vote === 1 && "text-emerald-600",
                  )}
                >
                  <ArrowBigUp
                    className={cn("size-4", vote === 1 && "fill-current")}
                    aria-hidden
                  />
                </button>
                <span
                  className={cn(
                    "min-w-3 text-center text-xs tabular-nums",
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
                  onClick={() => void castVote(-1)}
                  className={cn(
                    "flex min-h-9 items-center rounded-md px-1 transition-colors hover:text-red-600",
                    vote === -1 && "text-red-600",
                  )}
                >
                  <ArrowBigDown
                    className={cn("size-4", vote === -1 && "fill-current")}
                    aria-hidden
                  />
                </button>
              </div>

              {canReply ? (
                <button
                  type="button"
                  onClick={() => setReplyOpen((v) => !v)}
                  className="flex min-h-9 items-center gap-1 rounded-md px-1.5 text-xs transition-colors hover:text-foreground"
                >
                  <MessageSquare className="size-4" aria-hidden />
                  Reply
                </button>
              ) : null}

              {canMarkHelpful ? (
                <button
                  type="button"
                  aria-pressed={isHelpful}
                  onClick={() => void toggleHelpful()}
                  className={cn(
                    "flex min-h-9 items-center gap-1 rounded-md px-1.5 text-xs transition-colors hover:text-teal-text",
                    isHelpful && "text-teal-text",
                  )}
                >
                  <Sparkles
                    className={cn("size-4", isHelpful && "fill-current")}
                    aria-hidden
                  />
                  {isHelpful ? "Helpful" : "Mark helpful"}
                </button>
              ) : null}

              {hasMenu ? (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <button
                      type="button"
                      aria-label="Comment options"
                      className="flex min-h-9 items-center gap-1 rounded-md px-1.5 text-xs transition-colors hover:text-foreground"
                    >
                      <MoreHorizontal className="size-4" aria-hidden />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" className="w-44">
                    {canReport ? (
                      <DropdownMenuItem
                        className="min-h-11 gap-3"
                        onSelect={() => setReportOpen(true)}
                      >
                        <Flag className="size-4" aria-hidden />
                        Report comment
                      </DropdownMenuItem>
                    ) : null}
                    {canDelete ? (
                      <DropdownMenuItem
                        variant="destructive"
                        className="min-h-11 gap-3"
                        onSelect={() => setDeleteOpen(true)}
                      >
                        <Trash2 className="size-4" aria-hidden />
                        Delete comment
                      </DropdownMenuItem>
                    ) : null}
                  </DropdownMenuContent>
                </DropdownMenu>
              ) : null}
            </div>
          )}

          {replyOpen ? (
            <CommentComposer
              postUlid={postUlid}
              parentCommentUlid={comment.ulid}
              onCreated={handleReplyCreated}
              onCancel={() => setReplyOpen(false)}
              autoFocus
              compact
              placeholder={`Reply to @${comment.profile.handle}…`}
            />
          ) : null}
        </div>
      </div>

      {(replies.length > 0 || hasMore) && (
        <div className="flex flex-col gap-3 border-l pl-3 sm:ml-4">
          {replies.map((reply) => (
            <CommentItem
              key={reply.ulid}
              comment={reply}
              postUlid={postUlid}
              postAuthorUlid={postAuthorUlid}
            />
          ))}
          {hasMore ? (
            <button
              type="button"
              onClick={() => void loadReplies()}
              disabled={loadingReplies}
              className="self-start text-xs font-medium text-primary underline-offset-4 hover:underline disabled:opacity-60"
            >
              {loadingReplies
                ? "Loading…"
                : fetchedReplies
                  ? "Show more replies"
                  : `Show ${remaining} more ${remaining === 1 ? "reply" : "replies"}`}
            </button>
          ) : null}
        </div>
      )}

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this comment?</AlertDialogTitle>
            <AlertDialogDescription>
              This can&apos;t be undone. The comment will be replaced with a
              tombstone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              onClick={(event) => {
                event.preventDefault();
                void confirmDelete();
              }}
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <ReportDialog
        open={reportOpen}
        onOpenChange={setReportOpen}
        reportableType="comment"
        reportableUlid={comment.ulid}
        contextLabel={`@${comment.profile.handle}'s comment`}
      />
    </div>
  );
}

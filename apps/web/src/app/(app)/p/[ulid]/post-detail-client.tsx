"use client";

import { ArrowLeft, FileWarning } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";

import {
  CommentList,
  type CommentListHandle,
} from "@/components/comments/comment-list";
import { CommentComposer } from "@/components/comments/comment-composer";
import { EmptyState } from "@/components/empty-state";
import {
  PostCard,
  type LiveCounts,
} from "@/components/post-types/post-card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { GuestCta, PublicPostCard } from "@/components/posts/public-post-card";
import * as api from "@/lib/api/client";
import type { PublicPost } from "@/lib/api/public-types";
import type { Comment, Post } from "@/lib/api/types";
import { useEchoChannel } from "@/lib/echo";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

interface CommentAddedPayload {
  comment: Comment;
}

interface ReactionUpdatedPayload {
  post_ulid: string;
  likes_count: number;
  upvotes_count: number;
  downvotes_count: number;
}

function DetailSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <Skeleton className="h-40 w-full rounded-xl" />
      <Skeleton className="h-24 w-full rounded-xl" />
    </div>
  );
}

export function PostDetailClient({ ulid }: { ulid: string }) {
  const activeUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const authStatus = useAuthStore((s) => s.status);
  const [publicState, setPublicState] = useState<{
    ulid: string;
    post: PublicPost | null;
    phase: "loading" | "loaded" | "error";
  }>({ ulid, post: null, phase: "loading" });

  const [state, setState] = useState<{
    ulid: string;
    post: Post | null;
    phase: "loading" | "loaded" | "error";
  }>({ ulid, post: null, phase: "loading" });
  const [liveCounts, setLiveCounts] = useState<LiveCounts | null>(null);
  const listRef = useRef<CommentListHandle>(null);

  // Reset while rendering when navigating between posts.
  if (state.ulid !== ulid) {
    setState({ ulid, post: null, phase: "loading" });
    setLiveCounts(null);
  }

  useEffect(() => {
    if (authStatus !== "authed") return;
    let cancelled = false;
    api
      .get<{ data: Post }>(`/api/v1/posts/${ulid}`)
      .then((res) => {
        if (!cancelled) setState({ ulid, post: res.data, phase: "loaded" });
      })
      .catch(() => {
        if (!cancelled) setState({ ulid, post: null, phase: "error" });
      });
    return () => {
      cancelled = true;
    };
  }, [ulid, authStatus]);

  // Logged-out visitors get the public, read-only shape (SEO pages).
  useEffect(() => {
    if (authStatus !== "guest") return;
    let cancelled = false;
    api
      .get<{ data: PublicPost }>(`/api/v1/public/posts/${ulid}`)
      .then((res) => {
        if (!cancelled) {
          setPublicState({ ulid, post: res.data, phase: "loaded" });
        }
      })
      .catch(() => {
        if (!cancelled) setPublicState({ ulid, post: null, phase: "error" });
      });
    return () => {
      cancelled = true;
    };
  }, [ulid, authStatus]);

  function bumpCommentCount(delta: number) {
    setState((prev) =>
      prev.post
        ? {
            ...prev,
            post: {
              ...prev.post,
              comments_count: Math.max(0, prev.post.comments_count + delta),
            },
          }
        : prev,
    );
  }

  // Live updates for this post: new comments prepend, reactions sync counts.
  useEchoChannel(state.phase === "loaded" ? `post.${ulid}` : null, {
    ".CommentAdded": (payload) => onCommentAdded(payload),
    CommentAdded: (payload) => onCommentAdded(payload),
    ".ReactionUpdated": (payload) => onReactionUpdated(payload),
    ReactionUpdated: (payload) => onReactionUpdated(payload),
  });

  function onCommentAdded(payload: unknown) {
    const comment = (payload as CommentAddedPayload)?.comment;
    if (!comment) return;
    // Only top-level comments belong in the main list.
    if (comment.parent_comment_ulid == null) {
      listRef.current?.prepend(comment);
    }
    // Avoid double-counting our own comment (already bumped on submit).
    if (comment.profile?.ulid !== activeUlid) bumpCommentCount(1);
  }

  function onReactionUpdated(payload: unknown) {
    const data = payload as ReactionUpdatedPayload;
    if (!data || typeof data.likes_count !== "number") return;
    setLiveCounts({
      likes_count: data.likes_count,
      upvotes_count: data.upvotes_count,
      downvotes_count: data.downvotes_count,
    });
  }

  function handleCommentCreated(comment: Comment) {
    listRef.current?.prepend(comment);
    bumpCommentCount(1);
  }

  if (authStatus === "guest") {
    return (
      <div className="flex flex-col gap-4">
        {publicState.phase === "loading" ? <DetailSkeleton /> : null}
        {publicState.phase === "error" ? (
          <EmptyState
            icon={FileWarning}
            title="Post not found"
            description="This post may have been removed or is only visible to members."
          >
            <Button asChild variant="outline" className="mt-2 h-11">
              <Link href="/login">Sign in to view more</Link>
            </Button>
          </EmptyState>
        ) : null}
        {publicState.phase === "loaded" && publicState.post ? (
          <>
            <PublicPostCard post={publicState.post} linkToDetail={false} />
            <GuestCta
              personName={publicState.post.profile.name}
              action="reply to"
              message="Sign in to like, reply and follow small business owners in your community."
            />
          </>
        ) : null}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="-mx-4 -mt-4 flex items-center gap-2 border-b bg-background/90 px-2 py-2 backdrop-blur md:-mt-6">
        <Button
          variant="ghost"
          size="icon"
          className="size-10 rounded-full"
          asChild
        >
          <Link href="/home" aria-label="Back">
            <ArrowLeft className="size-5" aria-hidden />
          </Link>
        </Button>
        <h1 className="text-base font-semibold">Post</h1>
      </div>

      {state.phase === "loading" ? <DetailSkeleton /> : null}

      {state.phase === "error" ? (
        <EmptyState
          icon={FileWarning}
          title="Post not found"
          description="This post may have been removed or is no longer available."
        >
          <Button asChild variant="outline" className="mt-2 h-11">
            <Link href="/home">Back to home</Link>
          </Button>
        </EmptyState>
      ) : null}

      {state.phase === "loaded" && state.post ? (
        <>
          <PostCard
            post={state.post}
            linkToDetail={false}
            liveCounts={liveCounts}
          />

          <div className="rounded-xl border bg-card p-4">
            <CommentComposer
              postUlid={ulid}
              onCreated={handleCommentCreated}
              placeholder="Add a comment…"
            />
          </div>

          <CommentList
            ref={listRef}
            postUlid={ulid}
            postAuthorUlid={state.post.profile.ulid}
          />
        </>
      ) : null}
    </div>
  );
}

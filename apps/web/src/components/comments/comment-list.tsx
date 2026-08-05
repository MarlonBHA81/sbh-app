"use client";

import { useEffect, useImperativeHandle, useState } from "react";

import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Comment, Paginated } from "@/lib/api/types";
import { withParam } from "@/lib/utils";

import { CommentItem } from "./comment-item";

export interface CommentListHandle {
  /** Insert a new top-level comment at the top (deduped by ulid). */
  prepend: (comment: Comment) => void;
}

interface ListState {
  key: string;
  comments: Comment[];
  cursor: string | null;
  phase: "loading" | "loaded" | "error";
}

/**
 * Top-level comment list with cursor "Load more" pagination (newest first).
 * Live `CommentAdded` broadcasts and composer-created comments are inserted
 * via the imperative `prepend` handle (deduped by ulid).
 */
export function CommentList({
  postUlid,
  postAuthorUlid,
  ref,
}: {
  postUlid: string;
  postAuthorUlid: string;
  ref?: React.Ref<CommentListHandle>;
}) {
  const [retry, setRetry] = useState(0);
  const key = `${postUlid}#${retry}`;

  const [state, setState] = useState<ListState>({
    key,
    comments: [],
    cursor: null,
    phase: "loading",
  });
  const [loadingMore, setLoadingMore] = useState(false);

  // Reset while rendering when the list identity changes (no effect needed).
  if (state.key !== key) {
    setState({ key, comments: [], cursor: null, phase: "loading" });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Comment>>(`/api/v1/posts/${postUlid}/comments`)
      .then((res) => {
        if (!cancelled) {
          setState({
            key,
            comments: res.data,
            cursor: res.meta.next_cursor,
            phase: "loaded",
          });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key, comments: [], cursor: null, phase: "error" });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [key, postUlid]);

  useImperativeHandle(
    ref,
    () => ({
      prepend: (comment) =>
        setState((prev) =>
          prev.comments.some((c) => c.ulid === comment.ulid)
            ? prev
            : { ...prev, comments: [comment, ...prev.comments] },
        ),
    }),
    [],
  );

  async function loadMore() {
    if (!state.cursor || loadingMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get<Paginated<Comment>>(
        withParam(`/api/v1/posts/${postUlid}/comments`, "cursor", state.cursor),
      );
      setState((prev) => {
        const seen = new Set(prev.comments.map((c) => c.ulid));
        return {
          ...prev,
          comments: [
            ...prev.comments,
            ...res.data.filter((c) => !seen.has(c.ulid)),
          ],
          cursor: res.meta.next_cursor,
        };
      });
    } catch {
      // Keep the current list; the button stays for a retry.
    } finally {
      setLoadingMore(false);
    }
  }

  if (state.phase === "loading") {
    return (
      <div className="flex flex-col gap-4 pt-2">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="flex gap-2">
            <Skeleton className="size-8 rounded-full" />
            <div className="flex flex-1 flex-col gap-1.5">
              <Skeleton className="h-3 w-32" />
              <Skeleton className="h-3 w-full" />
              <Skeleton className="h-3 w-2/3" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (state.phase === "error") {
    return (
      <p className="py-6 text-center text-sm text-muted-foreground">
        Couldn&apos;t load comments.{" "}
        <button
          type="button"
          className="font-medium text-foreground underline-offset-4 hover:underline"
          onClick={() => setRetry((c) => c + 1)}
        >
          Try again
        </button>
      </p>
    );
  }

  if (state.comments.length === 0) {
    return (
      <p className="py-8 text-center text-sm text-muted-foreground">
        No comments yet. Be the first to reply.
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-5 pt-2">
      {state.comments.map((comment) => (
        <CommentItem
          key={comment.ulid}
          comment={comment}
          postUlid={postUlid}
          postAuthorUlid={postAuthorUlid}
        />
      ))}
      {state.cursor ? (
        <button
          type="button"
          onClick={() => void loadMore()}
          disabled={loadingMore}
          className="self-center text-sm font-medium text-primary underline-offset-4 hover:underline disabled:opacity-60"
        >
          {loadingMore ? "Loading…" : "Load more comments"}
        </button>
      ) : null}
    </div>
  );
}

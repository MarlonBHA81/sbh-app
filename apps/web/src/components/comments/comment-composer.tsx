"use client";

import { useState } from "react";
import { toast } from "sonner";

import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { Comment } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

import { MentionTextarea } from "./mention-textarea";

const MAX_BODY = 1000;

/**
 * Comment / reply composer. Posts to the post's comment endpoint (optionally
 * as a reply to `parentCommentUlid`) and hands the created comment back to the
 * caller for optimistic insertion.
 */
export function CommentComposer({
  postUlid,
  parentCommentUlid,
  onCreated,
  onCancel,
  autoFocus,
  compact,
  placeholder = "Add a comment…",
}: {
  postUlid: string;
  parentCommentUlid?: string;
  onCreated: (comment: Comment) => void;
  onCancel?: () => void;
  autoFocus?: boolean;
  compact?: boolean;
  placeholder?: string;
}) {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const [body, setBody] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const trimmed = body.trim();
  const overLimit = body.length > MAX_BODY;
  const canSubmit = trimmed.length > 0 && !overLimit && !submitting;

  async function submit() {
    if (!canSubmit) return;
    setSubmitting(true);
    try {
      const res = await api.post<{ data: Comment }>(
        `/api/v1/posts/${postUlid}/comments`,
        {
          body: trimmed,
          ...(parentCommentUlid && { parent_comment_id: parentCommentUlid }),
        },
      );
      setBody("");
      onCreated(res.data);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't post comment",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className={cn("flex gap-2", compact ? "pt-2" : "")}>
      {compact ? null : (
        <ProfileAvatar profile={activeProfile} className="mt-1 size-8" />
      )}
      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <MentionTextarea
          value={body}
          onChange={setBody}
          placeholder={placeholder}
          maxLength={MAX_BODY + 200}
          autoFocus={autoFocus}
          minHeightClass={compact ? "min-h-11" : "min-h-16"}
          onKeyDown={(event) => {
            // Ctrl/Cmd+Enter submits.
            if ((event.metaKey || event.ctrlKey) && event.key === "Enter") {
              event.preventDefault();
              void submit();
            }
          }}
        />
        <div className="flex items-center justify-end gap-2">
          <span
            className={cn(
              "mr-auto text-xs tabular-nums",
              overLimit ? "font-medium text-destructive" : "text-muted-foreground",
            )}
            aria-live="polite"
          >
            {body.length}/{MAX_BODY}
          </span>
          {onCancel ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9"
              onClick={onCancel}
            >
              Cancel
            </Button>
          ) : null}
          <Button
            type="button"
            size="sm"
            className="h-9"
            disabled={!canSubmit}
            onClick={() => void submit()}
          >
            {submitting ? "Posting…" : parentCommentUlid ? "Reply" : "Comment"}
          </Button>
        </div>
      </div>
    </div>
  );
}

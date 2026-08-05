"use client";

import { useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { Post, PostStatus, PostType, PostVisibility } from "@/lib/api/types";

export interface SavePostInput {
  type: PostType;
  body?: string;
  payload?: Record<string, unknown>;
  media_ids?: string[];
  parent_post_id?: string;
  topic_ids?: number[];
  visibility: PostVisibility;
  status: PostStatus;
  scheduled_at?: string;
  sensitive: boolean;
  is_question?: boolean;
  is_win?: boolean;
}

function successMessage(status: PostStatus, editing: boolean): string {
  // Honest reassurance (UX pattern 5): drafts persist indefinitely — we tell
  // the user it's safe, never that it's about to expire (it can't).
  if (status === "draft") return "Draft saved — it'll be here when you're back";
  if (status === "scheduled") return "Post scheduled";
  return editing ? "Post updated" : "Posted";
}

/** Create (POST /posts) or update (PATCH /posts/{ulid}) a post and toast. */
export function useSavePost() {
  const [saving, setSaving] = useState(false);

  async function save(
    input: SavePostInput,
    editUlid?: string,
  ): Promise<Post | null> {
    if (saving) return null;
    setSaving(true);
    try {
      const res = editUlid
        ? await api.patch<{ data: Post }>(`/api/v1/posts/${editUlid}`, input)
        : await api.post<{ data: Post }>("/api/v1/posts", input);
      toast.success(successMessage(input.status, Boolean(editUlid)));
      return res.data;
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't save your post",
      );
      return null;
    } finally {
      setSaving(false);
    }
  }

  return { save, saving };
}

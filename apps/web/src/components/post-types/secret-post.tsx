"use client";

import { Lock, LockOpen } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { Post, SecretPayload } from "@/lib/api/types";

import { PostBody } from "./post-body";

/**
 * Locked card — the secret text isn't in the payload until the viewer taps
 * to reveal (POST /reveal), then it animates in.
 */
export function SecretPost({ post }: { post: Post }) {
  const payload = (post.payload ?? {}) as unknown as SecretPayload;
  const [secretText, setSecretText] = useState<string | null>(
    payload.revealed && payload.secret_text ? payload.secret_text : null,
  );
  const [revealing, setRevealing] = useState(false);

  async function reveal() {
    if (revealing) return;
    setRevealing(true);
    try {
      const res = await api.post<{ data: { payload: SecretPayload } }>(
        `/api/v1/posts/${post.ulid}/reveal`,
      );
      setSecretText(res.data.payload.secret_text ?? "");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't reveal this secret",
      );
    } finally {
      setRevealing(false);
    }
  }

  if (secretText !== null) {
    return (
      <div className="flex animate-in flex-col gap-2 rounded-xl border bg-accent/40 p-4 duration-500 fade-in zoom-in-95">
        <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
          <LockOpen className="size-3.5" aria-hidden />
          Secret revealed
        </p>
        <PostBody text={secretText} />
      </div>
    );
  }

  return (
    <button
      type="button"
      disabled={revealing}
      onClick={(event) => {
        event.stopPropagation();
        void reveal();
      }}
      className="flex min-h-28 w-full flex-col items-center justify-center gap-2 rounded-xl border border-dashed bg-gradient-to-br from-muted/60 to-muted p-6 text-center transition-colors hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-70"
    >
      <span className="flex size-11 items-center justify-center rounded-full bg-background shadow-sm">
        <Lock className="size-5 text-muted-foreground" aria-hidden />
      </span>
      <span className="text-sm font-semibold">
        {revealing ? "Unlocking…" : "This post is a secret"}
      </span>
      <span className="text-xs text-muted-foreground">Tap to reveal</span>
    </button>
  );
}

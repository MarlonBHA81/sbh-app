"use client";

import {
  BadgeCheck,
  Eye,
  EyeOff,
  Heart,
  MessageCircle,
  Repeat2,
} from "lucide-react";
import Link from "next/link";
import { createElement, useState } from "react";
import { toast } from "sonner";

import { useComposer } from "@/components/composer/composer-provider";
import { ProfileAvatar } from "@/components/profile-avatar";
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
import type { Post } from "@/lib/api/types";
import { relativeTime } from "@/lib/time";

import { getPostRenderer, TYPE_BADGES } from "./registry";

function formatCount(n: number): string {
  if (n === 0) return "";
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

function ActionButton({
  icon: Icon,
  count,
  label,
  onClick,
}: {
  icon: typeof Heart;
  count: number;
  label: string;
  onClick?: () => void;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      className="flex min-h-11 min-w-11 items-center gap-1.5 rounded-lg px-2 text-muted-foreground transition-colors hover:text-foreground"
    >
      <Icon className="size-[18px]" aria-hidden />
      <span className="text-xs tabular-nums">{formatCount(count)}</span>
    </button>
  );
}

export function PostCard({ post }: { post: Post }) {
  const { openComposer, notifyPostsMutated } = useComposer();
  const [showSensitive, setShowSensitive] = useState(false);
  const [repostConfirmOpen, setRepostConfirmOpen] = useState(false);
  const [reposting, setReposting] = useState(false);

  const content = createElement(getPostRenderer(post.type), { post });
  const badge = TYPE_BADGES[post.type];
  const timestamp = post.published_at ?? post.created_at;
  // Reposting a repost targets the original post.
  const repostTarget = post.type === "repost" ? (post.parent ?? post) : post;

  const hidden = post.sensitive && !showSensitive;

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

  return (
    <article className="flex flex-col gap-3 rounded-xl border bg-card p-4 text-card-foreground">
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
            <time dateTime={timestamp}>{relativeTime(timestamp)}</time>
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

      <footer className="-mx-2 -mb-2 flex items-center justify-between">
        <ActionButton icon={Heart} count={post.likes_count} label="Likes" />
        <ActionButton
          icon={MessageCircle}
          count={post.comments_count}
          label="Comments"
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

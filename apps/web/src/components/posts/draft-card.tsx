"use client";

import { CalendarClock, Pencil, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { useComposer } from "@/components/composer/composer-provider";
import { TYPE_BADGES } from "@/components/post-types/registry";
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
import * as api from "@/lib/api/client";
import type { Post, PostType } from "@/lib/api/types";
import { formatLocalDateTime, relativeTime } from "@/lib/time";

const TYPE_LABELS: Record<PostType, string> = {
  text: "Text",
  link: "Link",
  image: "Photos",
  quote: "Quote",
  repost: "Repost",
  typewriter: "Typewriter",
  magnifier: "Magnifier",
  secret: "Secret",
  checkin: "Check-in",
};

function snippet(post: Post): string {
  if (post.body) return post.body;
  const payload = (post.payload ?? {}) as Record<string, unknown>;
  for (const key of ["text", "secret_text", "place_name", "title", "url"]) {
    if (typeof payload[key] === "string" && payload[key]) {
      return payload[key];
    }
  }
  if (post.media.length > 0) {
    return `${post.media.length} photo${post.media.length > 1 ? "s" : ""}`;
  }
  return "(no text)";
}

/** Compact row for the drafts/scheduled lists with edit + delete. */
export function DraftCard({
  post,
  onDeleted,
}: {
  post: Post;
  onDeleted: (ulid: string) => void;
}) {
  const { openComposer } = useComposer();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  async function remove() {
    if (deleting) return;
    setDeleting(true);
    try {
      await api.del(`/api/v1/posts/${post.ulid}`);
      toast.success(post.status === "scheduled" ? "Post deleted" : "Draft deleted");
      onDeleted(post.ulid);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't delete",
      );
      setDeleting(false);
      setConfirmOpen(false);
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-xl border bg-card p-4">
      <div className="flex items-center gap-2">
        <Badge variant="secondary" className="text-[10px]">
          {TYPE_BADGES[post.type] ?? TYPE_LABELS[post.type]}
        </Badge>
        {post.status === "scheduled" && post.scheduled_at ? (
          <Badge variant="outline" className="gap-1 text-[10px]">
            <CalendarClock className="size-3" aria-hidden />
            Publishes at {formatLocalDateTime(post.scheduled_at)}
          </Badge>
        ) : null}
        <span className="ml-auto shrink-0 text-xs text-muted-foreground">
          {relativeTime(post.created_at)}
        </span>
      </div>
      <p className="line-clamp-3 text-sm break-words">{snippet(post)}</p>
      {post.media.length > 0 ? (
        <div className="flex gap-1.5">
          {post.media.map((media) => (
            <div
              key={media.ulid}
              className="size-14 overflow-hidden rounded-md border bg-muted"
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={media.thumb_url}
                alt=""
                loading="lazy"
                width={media.width}
                height={media.height}
                className="size-full object-cover"
              />
            </div>
          ))}
        </div>
      ) : null}
      <div className="flex gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-11 flex-1 gap-1.5"
          onClick={() => openComposer({ post })}
        >
          <Pencil className="size-4" aria-hidden />
          Edit
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-11 flex-1 gap-1.5 text-destructive hover:text-destructive"
          onClick={() => setConfirmOpen(true)}
        >
          <Trash2 className="size-4" aria-hidden />
          Delete
        </Button>
      </div>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              Delete this {post.status === "scheduled" ? "scheduled post" : "draft"}?
            </AlertDialogTitle>
            <AlertDialogDescription>
              This can&apos;t be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={deleting}
              onClick={(event) => {
                event.preventDefault();
                void remove();
              }}
            >
              {deleting ? "Deleting…" : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

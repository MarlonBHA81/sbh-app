"use client";

import { MessageCircle, SquarePen } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { NewConversationSheet } from "@/components/messages/new-conversation-sheet";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Conversation, Paginated } from "@/lib/api/types";
import {
  conversationTitle,
  lastMessagePreview,
  otherParticipant,
} from "@/lib/messages";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";
import { relativeTime } from "@/lib/time";
import { cn } from "@/lib/utils";

function formatBadge(count: number): string {
  return count > 99 ? "99+" : String(count);
}

function GroupAvatar({ conversation }: { conversation: Conversation }) {
  const shown = conversation.participants.slice(0, 2);
  if (shown.length === 0) {
    return (
      <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
        <MessageCircle className="size-5 text-muted-foreground" aria-hidden />
      </span>
    );
  }
  return (
    <span className="relative size-11 shrink-0">
      {shown.map((p, index) => (
        <ProfileAvatar
          key={p.profile.ulid}
          profile={p.profile}
          className={cn(
            "absolute size-7 ring-2 ring-background",
            index === 0 ? "top-0 left-0" : "right-0 bottom-0",
          )}
        />
      ))}
    </span>
  );
}

function ConversationRow({
  conversation,
  selfUlid,
}: {
  conversation: Conversation;
  selfUlid: string | null;
}) {
  const title = conversationTitle(conversation, selfUlid);
  const preview = lastMessagePreview(conversation);
  const unread = conversation.unread_count > 0;
  const other =
    conversation.kind === "dm"
      ? otherParticipant(conversation, selfUlid)
      : null;

  return (
    <Link
      href={`/messages/${conversation.ulid}`}
      className="flex items-center gap-3 rounded-xl px-2 py-2.5 transition-colors hover:bg-accent/40"
    >
      {conversation.kind === "group" ? (
        <GroupAvatar conversation={conversation} />
      ) : (
        <ProfileAvatar profile={other?.profile ?? null} className="size-11" />
      )}
      <div className="min-w-0 flex-1">
        <div className="flex items-baseline justify-between gap-2">
          <span
            className={cn(
              "truncate text-sm",
              unread ? "font-semibold" : "font-medium",
            )}
          >
            {title}
          </span>
          {conversation.approval_status === "pending" ? (
            <span className="shrink-0 rounded-full bg-warmgray px-2 py-0.5 text-[10px] font-medium text-text-secondary">
              Pending
            </span>
          ) : conversation.approval_status === "rejected" ? (
            <span className="shrink-0 rounded-full bg-destructive/15 px-2 py-0.5 text-[10px] font-medium text-destructive">
              Not approved
            </span>
          ) : null}
          {conversation.last_message ? (
            <time
              dateTime={conversation.last_message.created_at}
              className="shrink-0 text-xs text-muted-foreground"
            >
              {relativeTime(conversation.last_message.created_at)}
            </time>
          ) : null}
        </div>
        <div className="flex items-center justify-between gap-2">
          <p
            className={cn(
              "truncate text-sm",
              preview.deleted && "italic",
              unread ? "text-foreground" : "text-muted-foreground",
            )}
          >
            {preview.text}
          </p>
          {unread ? (
            <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-foreground tabular-nums">
              {formatBadge(conversation.unread_count)}
            </span>
          ) : null}
        </div>
      </div>
    </Link>
  );
}

export function ConversationList() {
  const selfUlid = useAuthStore((s) => s.activeProfile?.ulid ?? null);
  const conversations = useMessagesStore((s) => s.conversations);
  const nextCursor = useMessagesStore((s) => s.nextCursor);
  const listLoaded = useMessagesStore((s) => s.listLoaded);
  const setConversations = useMessagesStore((s) => s.setConversations);
  const appendConversations = useMessagesStore((s) => s.appendConversations);

  const [phase, setPhase] = useState<"loading" | "loaded" | "error">(
    listLoaded ? "loaded" : "loading",
  );
  const [loadingMore, setLoadingMore] = useState(false);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [retry, setRetry] = useState(0);

  // Fetch / revalidate the first page on mount and when retrying.
  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Conversation>>("/api/v1/conversations")
      .then((res) => {
        if (cancelled) return;
        setConversations(res.data, res.meta.next_cursor);
        setPhase("loaded");
      })
      .catch(() => {
        if (!cancelled) setPhase((prev) => (listLoaded ? prev : "error"));
      });
    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [retry]);

  async function loadMore() {
    if (!nextCursor || loadingMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get<Paginated<Conversation>>(
        `/api/v1/conversations?cursor=${encodeURIComponent(nextCursor)}`,
      );
      appendConversations(res.data, res.meta.next_cursor);
    } catch {
      // Keep what we have.
    } finally {
      setLoadingMore(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold tracking-tight">Messages</h1>
        <Button
          type="button"
          size="sm"
          className="h-9 gap-1.5"
          onClick={() => setSheetOpen(true)}
        >
          <SquarePen className="size-4" aria-hidden />
          New
        </Button>
      </div>

      {phase === "loading" ? (
        <div className="flex flex-col gap-1">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="flex items-center gap-3 px-2 py-2.5">
              <Skeleton className="size-11 rounded-full" />
              <div className="flex flex-1 flex-col gap-1.5">
                <Skeleton className="h-3 w-32" />
                <Skeleton className="h-3 w-48" />
              </div>
            </div>
          ))}
        </div>
      ) : null}

      {phase === "error" ? (
        <p className="py-6 text-center text-sm text-muted-foreground">
          Couldn&apos;t load conversations.{" "}
          <button
            type="button"
            className="font-medium text-foreground underline-offset-4 hover:underline"
            onClick={() => {
              setPhase("loading");
              setRetry((c) => c + 1);
            }}
          >
            Try again
          </button>
        </p>
      ) : null}

      {phase === "loaded" && conversations.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-14 text-center">
          <div className="flex size-12 items-center justify-center rounded-full bg-muted">
            <MessageCircle className="size-6 text-muted-foreground" aria-hidden />
          </div>
          <p className="font-semibold">No messages yet</p>
          <p className="max-w-xs text-sm text-muted-foreground">
            Start a direct conversation or create a group to get going.
          </p>
          <Button
            type="button"
            className="mt-1 h-11 gap-1.5"
            onClick={() => setSheetOpen(true)}
          >
            <SquarePen className="size-4" aria-hidden />
            New message
          </Button>
        </div>
      ) : null}

      {phase === "loaded" && conversations.length > 0 ? (
        <div className="flex flex-col gap-0.5">
          {conversations.map((conversation) => (
            <ConversationRow
              key={conversation.ulid}
              conversation={conversation}
              selfUlid={selfUlid}
            />
          ))}
          {nextCursor ? (
            <button
              type="button"
              onClick={() => void loadMore()}
              disabled={loadingMore}
              className="mt-2 self-center text-sm font-medium text-primary underline-offset-4 hover:underline disabled:opacity-60"
            >
              {loadingMore ? "Loading…" : "Load more"}
            </button>
          ) : null}
        </div>
      ) : null}

      <NewConversationSheet open={sheetOpen} onOpenChange={setSheetOpen} />
    </div>
  );
}

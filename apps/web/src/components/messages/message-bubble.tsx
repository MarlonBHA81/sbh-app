"use client";

import { CornerUpLeft, MoreHorizontal, SmilePlus, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRef, useState } from "react";

import { ProfileAvatar } from "@/components/profile-avatar";
import {
  Dialog,
  DialogContent,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Popover,
  PopoverAnchor,
  PopoverContent,
} from "@/components/ui/popover";
import type { Media } from "@/lib/api/types";
import { REACTION_EMOJIS, messageTime } from "@/lib/messages";
import { cn } from "@/lib/utils";

import type { ChatMessage } from "./chat-types";

function MessageMedia({ media }: { media: Media[] }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  if (media.length === 0) return null;
  const single = media.length === 1;
  const openItem = openIndex !== null ? (media[openIndex] ?? null) : null;

  return (
    <>
      <div
        className={cn(
          "grid gap-0.5 overflow-hidden rounded-xl",
          single ? "grid-cols-1" : "grid-cols-2",
        )}
      >
        {media.map((item, index) => (
          <button
            key={item.ulid}
            type="button"
            onClick={() => setOpenIndex(index)}
            className="block overflow-hidden bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            style={single ? { aspectRatio: `${item.width} / ${item.height}` } : undefined}
            aria-label="View image"
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={item.thumb_url}
              alt=""
              loading="lazy"
              width={item.width}
              height={item.height}
              className={cn(
                "size-full object-cover",
                !single && "aspect-square",
              )}
            />
          </button>
        ))}
      </div>

      <Dialog
        open={openIndex !== null}
        onOpenChange={(open) => {
          if (!open) setOpenIndex(null);
        }}
      >
        <DialogContent className="max-w-[calc(100%-1rem)] border-none bg-transparent p-0 shadow-none sm:max-w-3xl">
          <DialogTitle className="sr-only">Image</DialogTitle>
          {openItem ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={openItem.url}
              alt=""
              width={openItem.width}
              height={openItem.height}
              className="max-h-[85dvh] w-full rounded-lg object-contain"
            />
          ) : null}
        </DialogContent>
      </Dialog>
    </>
  );
}

export function MessageBubble({
  message,
  own,
  grouped,
  showSeen,
  onReact,
  onReply,
  onDelete,
  onRetry,
  onJumpToReply,
}: {
  message: ChatMessage;
  own: boolean;
  /** Part of a consecutive same-sender run (hide avatar/name). */
  grouped: boolean;
  /** Render the "Seen" caption under this (own, last-read) message. */
  showSeen: boolean;
  onReact: (message: ChatMessage, emoji: string) => void;
  onReply: (message: ChatMessage) => void;
  onDelete: (message: ChatMessage) => void;
  onRetry: (message: ChatMessage) => void;
  onJumpToReply: (ulid: string) => void;
}) {
  const t = useTranslations("chat");
  const tc = useTranslations("common");
  const [reactionOpen, setReactionOpen] = useState(false);
  const [showTime, setShowTime] = useState(false);
  const pressTimer = useRef<number | null>(null);

  const deleted = message.deleted;
  const hidden = message.hidden;
  const failed = message.pending === "failed";
  const sending = message.pending === "sending";
  const tombstone = deleted || hidden;

  function startPress() {
    pressTimer.current = window.setTimeout(() => setReactionOpen(true), 450);
  }
  function endPress() {
    if (pressTimer.current) {
      window.clearTimeout(pressTimer.current);
      pressTimer.current = null;
    }
  }

  return (
    <div
      id={`msg-${message.ulid}`}
      className={cn(
        "group/msg flex gap-2",
        own ? "flex-row-reverse" : "flex-row",
        grouped ? "mt-0.5" : "mt-3",
      )}
    >
      {/* Avatar column (left, others only) */}
      {!own ? (
        <div className="w-8 shrink-0">
          {!grouped ? (
            <ProfileAvatar profile={message.profile} className="size-8" />
          ) : null}
        </div>
      ) : null}

      <div
        className={cn(
          "flex min-w-0 max-w-[78%] flex-col",
          own ? "items-end" : "items-start",
        )}
      >
        {!own && !grouped ? (
          <span className="mb-0.5 px-1 text-xs font-medium text-muted-foreground">
            {message.profile.name}
          </span>
        ) : null}

        <div className="flex items-end gap-1">
          {/* Hover actions (desktop) — left of own bubbles, right of others */}
          {!tombstone ? (
            <div
              className={cn(
                "flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover/msg:opacity-100 focus-within:opacity-100",
                own ? "order-first" : "order-last",
              )}
            >
              <button
                type="button"
                aria-label="React"
                onClick={() => setReactionOpen(true)}
                className="flex size-7 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
              >
                <SmilePlus className="size-4" aria-hidden />
              </button>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    type="button"
                    aria-label="Message actions"
                    className="flex size-7 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
                  >
                    <MoreHorizontal className="size-4" aria-hidden />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align={own ? "end" : "start"}>
                  <DropdownMenuItem onSelect={() => onReply(message)}>
                    <CornerUpLeft className="size-4" aria-hidden />
                    {t("reply")}
                  </DropdownMenuItem>
                  {own ? (
                    <DropdownMenuItem
                      variant="destructive"
                      onSelect={() => onDelete(message)}
                    >
                      <Trash2 className="size-4" aria-hidden />
                      {tc("delete")}
                    </DropdownMenuItem>
                  ) : null}
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          ) : null}

          <Popover open={reactionOpen} onOpenChange={setReactionOpen}>
            <PopoverAnchor asChild>
              <button
                type="button"
                onClick={() => setShowTime((v) => !v)}
                onContextMenu={(e) => {
                  if (!tombstone) {
                    e.preventDefault();
                    setReactionOpen(true);
                  }
                }}
                onTouchStart={tombstone ? undefined : startPress}
                onTouchEnd={endPress}
                onTouchMove={endPress}
                className={cn(
                  "block rounded-2xl px-3 py-2 text-start text-sm break-words whitespace-pre-wrap",
                  tombstone
                    ? "bg-muted text-muted-foreground italic"
                    : own
                      ? "bg-primary text-primary-foreground"
                      : "bg-muted text-foreground",
                  sending && "opacity-70",
                  failed && "ring-1 ring-destructive",
                )}
              >
                {/* Reply quote */}
                {message.reply_to && !tombstone ? (
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      onJumpToReply(message.reply_to!.ulid);
                    }}
                    className={cn(
                      "mb-1 block w-full rounded-md border-s-2 px-2 py-1 text-start text-xs",
                      own
                        ? "border-primary-foreground/50 bg-primary-foreground/10"
                        : "border-border bg-background/60",
                    )}
                  >
                    <span className="block font-medium">
                      {message.reply_to.sender.name || t("message")}
                    </span>
                    <span className="line-clamp-2 opacity-80">
                      {message.reply_to.body ?? t("messageUnavailable")}
                    </span>
                  </button>
                ) : null}

                {message.media.length > 0 && !tombstone ? (
                  <div className="mb-1">
                    <MessageMedia media={message.media} />
                  </div>
                ) : null}

                {tombstone ? (
                  <span>
                    {deleted ? t("messageDeleted") : t("messageUnavailable")}
                  </span>
                ) : message.body ? (
                  <span>{message.body}</span>
                ) : null}
              </button>
            </PopoverAnchor>
            <PopoverContent
              className="flex w-auto gap-1 rounded-full p-1"
              side="top"
              align={own ? "end" : "start"}
            >
              {REACTION_EMOJIS.map((emoji) => (
                <button
                  key={emoji}
                  type="button"
                  onClick={() => {
                    onReact(message, emoji);
                    setReactionOpen(false);
                  }}
                  className="flex size-9 items-center justify-center rounded-full text-lg transition-transform hover:scale-110 hover:bg-accent"
                  aria-label={`React ${emoji}`}
                >
                  {emoji}
                </button>
              ))}
            </PopoverContent>
          </Popover>
        </div>

        {/* Reaction chips */}
        {message.reactions.length > 0 ? (
          <div
            className={cn(
              "mt-1 flex flex-wrap gap-1",
              own ? "justify-end" : "justify-start",
            )}
          >
            {message.reactions.map((reaction) => (
              <button
                key={reaction.emoji}
                type="button"
                onClick={() => onReact(message, reaction.emoji)}
                className={cn(
                  "flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs tabular-nums transition-colors",
                  reaction.reacted_by_me
                    ? "border-primary/40 bg-primary/10 text-foreground"
                    : "border-transparent bg-muted text-muted-foreground hover:bg-accent",
                )}
                aria-pressed={reaction.reacted_by_me}
              >
                <span>{reaction.emoji}</span>
                {reaction.count > 1 ? <span>{reaction.count}</span> : null}
              </button>
            ))}
          </div>
        ) : null}

        {/* Timestamp / status */}
        {failed ? (
          <span className="mt-0.5 flex items-center gap-1.5 px-1 text-[11px]">
            <span className="text-destructive">{t("failedToSend")}</span>
            <button
              type="button"
              onClick={() => onRetry(message)}
              className="font-medium text-foreground underline underline-offset-2"
            >
              {tc("retry")}
            </button>
          </span>
        ) : showTime || sending ? (
          <span className="mt-0.5 px-1 text-[11px] text-muted-foreground">
            {sending ? t("sending") : messageTime(message.created_at)}
          </span>
        ) : null}

        {showSeen ? (
          <span className="mt-0.5 px-1 text-[11px] text-muted-foreground">
            {t("seen")}
          </span>
        ) : null}
      </div>
    </div>
  );
}

"use client";

import { ChevronLeft, Info, LogOut, MoreHorizontal, Pencil, Users } from "lucide-react";
import Link from "next/link";
import { useState } from "react";

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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { Conversation } from "@/lib/api/types";
import { conversationTitle, otherParticipant } from "@/lib/messages";

export function ChatHeader({
  conversation,
  selfUlid,
  online,
  onOpenMembers,
  onEditGroup,
  onShowRules,
  onLeave,
}: {
  conversation: Conversation;
  selfUlid: string | null;
  online: boolean;
  onOpenMembers: () => void;
  onEditGroup: () => void;
  onShowRules: () => void;
  onLeave: () => void;
}) {
  const [confirmLeave, setConfirmLeave] = useState(false);
  const isGroup = conversation.kind === "group";
  const title = conversationTitle(conversation, selfUlid);
  const other = isGroup ? null : otherParticipant(conversation, selfUlid);
  const canEdit =
    isGroup &&
    (conversation.my_role === "owner" || conversation.my_role === "admin");

  const identity = (
    <div className="flex min-w-0 items-center gap-2.5">
      <span className="relative shrink-0">
        {isGroup ? (
          <ProfileAvatar
            profile={
              conversation.participants[0]?.profile ?? { name: title, avatar_url: null }
            }
            className="size-9"
          />
        ) : (
          <ProfileAvatar profile={other?.profile ?? null} className="size-9" />
        )}
        {!isGroup && online ? (
          <span
            className="absolute end-0 bottom-0 size-3 rounded-full bg-sage ring-2 ring-background"
            aria-label="Online"
          />
        ) : null}
      </span>
      <span className="flex min-w-0 flex-col">
        <span className="truncate text-sm font-semibold leading-tight">
          {title}
        </span>
        <span className="truncate text-xs text-muted-foreground">
          {isGroup
            ? `${conversation.participants.length} members`
            : online
              ? "Online"
              : other
                ? `@${other.profile.handle}`
                : ""}
        </span>
      </span>
    </div>
  );

  return (
    <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b bg-background/95 px-2 backdrop-blur">
      <Link
        href="/messages"
        aria-label="Back to messages"
        className="flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
      >
        <ChevronLeft className="size-5" aria-hidden />
      </Link>

      {isGroup ? (
        <button
          type="button"
          onClick={onOpenMembers}
          className="flex min-w-0 flex-1 items-center text-start"
        >
          {identity}
        </button>
      ) : other ? (
        <Link href={`/${other.profile.handle}`} className="min-w-0 flex-1">
          {identity}
        </Link>
      ) : (
        <div className="min-w-0 flex-1">{identity}</div>
      )}

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <button
            type="button"
            aria-label="Conversation options"
            className="flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground"
          >
            <MoreHorizontal className="size-5" aria-hidden />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {isGroup ? (
            <>
              <DropdownMenuItem onSelect={onOpenMembers}>
                <Users className="size-4" aria-hidden />
                View members
              </DropdownMenuItem>
              {canEdit ? (
                <DropdownMenuItem onSelect={onEditGroup}>
                  <Pencil className="size-4" aria-hidden />
                  Edit group
                </DropdownMenuItem>
              ) : null}
              {conversation.rules ? (
                <DropdownMenuItem onSelect={onShowRules}>
                  <Info className="size-4" aria-hidden />
                  Group rules
                </DropdownMenuItem>
              ) : null}
              <DropdownMenuSeparator />
              <DropdownMenuItem
                variant="destructive"
                onSelect={() => setConfirmLeave(true)}
              >
                <LogOut className="size-4" aria-hidden />
                Leave conversation
              </DropdownMenuItem>
            </>
          ) : (
            <>
              {other ? (
                <DropdownMenuItem asChild>
                  <Link href={`/${other.profile.handle}`}>
                    <Users className="size-4" aria-hidden />
                    View profile
                  </Link>
                </DropdownMenuItem>
              ) : null}
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={confirmLeave} onOpenChange={setConfirmLeave}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Leave conversation?</AlertDialogTitle>
            <AlertDialogDescription>
              You&apos;ll stop receiving messages from this group. You can be
              added back by a member.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={onLeave}>Leave</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </header>
  );
}

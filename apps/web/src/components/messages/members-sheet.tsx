"use client";

import { MoreVertical, UserPlus, X } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";

import { ProfileSearch } from "@/components/messages/profile-search";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import * as api from "@/lib/api/client";
import { ApiError } from "@/lib/api/client";
import type {
  Conversation,
  ConversationParticipant,
  ConversationRole,
  Profile,
} from "@/lib/api/types";

const ROLE_LABEL: Record<ConversationRole, string> = {
  owner: "Owner",
  // "Manager" is the Space-facing name for the admin role (Roles P3).
  admin: "Manager",
  member: "Member",
};

export function MembersSheet({
  conversation,
  selfUlid,
  open,
  onOpenChange,
  onConversationChange,
}: {
  conversation: Conversation;
  selfUlid: string | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onConversationChange: (conversation: Conversation) => void;
}) {
  const [adding, setAdding] = useState(false);
  const [pending, setPending] = useState<Profile[]>([]);
  const [busy, setBusy] = useState(false);

  const myRole = conversation.my_role;
  const isOwner = myRole === "owner";
  const isAdmin = myRole === "admin";

  async function refresh() {
    try {
      const res = await api.get<{ data: Conversation }>(
        `/api/v1/conversations/${conversation.ulid}`,
      );
      onConversationChange(res.data);
    } catch {
      // Non-fatal: the caller keeps the previous state.
    }
  }

  function canManage(target: ConversationParticipant): boolean {
    if (target.profile.ulid === selfUlid) return false;
    if (target.role === "owner") return false;
    if (isOwner) return true;
    // Admins may only remove plain members.
    if (isAdmin) return target.role === "member";
    return false;
  }

  async function removeMember(ulid: string) {
    setBusy(true);
    try {
      await api.del(
        `/api/v1/conversations/${conversation.ulid}/participants/${ulid}`,
      );
      await refresh();
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Couldn't remove member",
      );
    } finally {
      setBusy(false);
    }
  }

  async function setRole(ulid: string, role: ConversationRole) {
    setBusy(true);
    try {
      await api.post(
        `/api/v1/conversations/${conversation.ulid}/participants/${ulid}/role`,
        { role },
      );
      await refresh();
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Couldn't update role",
      );
    } finally {
      setBusy(false);
    }
  }

  async function addPeople() {
    if (busy || pending.length === 0) return;
    setBusy(true);
    try {
      await api.post(
        `/api/v1/conversations/${conversation.ulid}/participants`,
        { profile_ulids: pending.map((p) => p.ulid) },
      );
      setPending([]);
      setAdding(false);
      await refresh();
      toast.success("People added");
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Couldn't add people",
      );
    } finally {
      setBusy(false);
    }
  }

  function togglePending(profile: Profile) {
    setPending((prev) =>
      prev.some((p) => p.ulid === profile.ulid)
        ? prev.filter((p) => p.ulid !== profile.ulid)
        : [...prev, profile],
    );
  }

  const existingUlids = conversation.participants.map((p) => p.profile.ulid);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full gap-0 sm:max-w-md">
        <SheetHeader>
          <SheetTitle>
            Members ({conversation.participants.length})
          </SheetTitle>
          <SheetDescription className="sr-only">
            People in this conversation.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4 pb-6">
          {(isOwner || isAdmin) && !adding ? (
            <Button
              type="button"
              variant="outline"
              className="mb-3 h-11 w-full justify-start gap-2"
              onClick={() => setAdding(true)}
            >
              <UserPlus className="size-4" aria-hidden />
              Add people
            </Button>
          ) : null}

          {adding ? (
            <div className="mb-4 flex flex-col gap-3 rounded-xl border p-3">
              {pending.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {pending.map((p) => (
                    <span
                      key={p.ulid}
                      className="flex items-center gap-1.5 rounded-full bg-accent py-1 pr-1 pl-1.5 text-sm"
                    >
                      <ProfileAvatar profile={p} className="size-5" />
                      <span className="max-w-28 truncate">{p.name}</span>
                      <button
                        type="button"
                        onClick={() => togglePending(p)}
                        aria-label={`Remove ${p.name}`}
                        className="flex size-5 items-center justify-center rounded-full text-muted-foreground hover:bg-background hover:text-foreground"
                      >
                        <X className="size-3.5" aria-hidden />
                      </button>
                    </span>
                  ))}
                </div>
              ) : null}
              <ProfileSearch
                placeholder="Search people to add"
                excludeUlids={[...existingUlids, ...pending.map((p) => p.ulid)]}
                onSelect={togglePending}
              />
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="ghost"
                  className="h-10 flex-1"
                  onClick={() => {
                    setAdding(false);
                    setPending([]);
                  }}
                >
                  Cancel
                </Button>
                <Button
                  type="button"
                  className="h-10 flex-1"
                  disabled={busy || pending.length === 0}
                  onClick={() => void addPeople()}
                >
                  {busy ? "Adding…" : `Add${pending.length ? ` (${pending.length})` : ""}`}
                </Button>
              </div>
            </div>
          ) : null}

          <ul className="flex flex-col gap-0.5">
            {conversation.participants.map((participant) => {
              const { profile, role } = participant;
              const manageable = canManage(participant);
              return (
                <li
                  key={profile.ulid}
                  className="flex items-center gap-3 rounded-lg px-1 py-2"
                >
                  <Link
                    href={`/${profile.handle}`}
                    className="flex min-w-0 flex-1 items-center gap-3"
                  >
                    <ProfileAvatar profile={profile} className="size-10" />
                    <span className="flex min-w-0 flex-col">
                      <span className="truncate text-sm font-medium">
                        {profile.name}
                        {profile.ulid === selfUlid ? " (you)" : ""}
                      </span>
                      <span className="truncate text-xs text-muted-foreground">
                        @{profile.handle}
                      </span>
                    </span>
                  </Link>
                  {role !== "member" ? (
                    <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                      {ROLE_LABEL[role]}
                    </span>
                  ) : null}
                  {manageable ? (
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <button
                          type="button"
                          aria-label={`Manage ${profile.name}`}
                          disabled={busy}
                          className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-50"
                        >
                          <MoreVertical className="size-4" aria-hidden />
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        {isOwner && role === "member" ? (
                          <DropdownMenuItem
                            onSelect={() => void setRole(profile.ulid, "admin")}
                          >
                            Make manager
                          </DropdownMenuItem>
                        ) : null}
                        {isOwner && role === "admin" ? (
                          <DropdownMenuItem
                            onSelect={() => void setRole(profile.ulid, "member")}
                          >
                            Remove manager
                          </DropdownMenuItem>
                        ) : null}
                        <DropdownMenuItem
                          variant="destructive"
                          onSelect={() => void removeMember(profile.ulid)}
                        >
                          Remove from group
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  ) : null}
                </li>
              );
            })}
          </ul>
        </div>
      </SheetContent>
    </Sheet>
  );
}

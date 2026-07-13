"use client";

import { ChevronLeft, MessageSquarePlus, Users, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";

import { ProfileSearch } from "@/components/messages/profile-search";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import { ApiError } from "@/lib/api/client";
import type { Conversation, Profile } from "@/lib/api/types";
import { useMessagesStore } from "@/lib/stores/messages-store";

type Mode = "choose" | "dm" | "group";

export function NewConversationSheet({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const t = useTranslations("chat");
  const router = useRouter();
  const upsertConversation = useMessagesStore((s) => s.upsertConversation);
  const [mode, setMode] = useState<Mode>("choose");
  const [members, setMembers] = useState<Profile[]>([]);
  const [title, setTitle] = useState("");
  const [rules, setRules] = useState("");
  const [busy, setBusy] = useState(false);

  function resetAndClose() {
    onOpenChange(false);
    // Delay reset until the close animation finishes.
    window.setTimeout(() => {
      setMode("choose");
      setMembers([]);
      setTitle("");
      setRules("");
      setBusy(false);
    }, 250);
  }

  function goToConversation(conversation: Conversation) {
    upsertConversation(conversation);
    resetAndClose();
    router.push(`/messages/${conversation.ulid}`);
  }

  async function startDm(profile: Profile) {
    if (busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ data: Conversation }>(
        "/api/v1/conversations",
        { kind: "dm", profile_ulid: profile.ulid },
      );
      goToConversation(res.data);
    } catch (error) {
      setBusy(false);
      if (error instanceof ApiError && error.status === 403) {
        toast.error(`@${profile.handle} isn't accepting messages right now`);
      } else {
        toast.error(
          error instanceof ApiError ? error.message : "Couldn't start the chat",
        );
      }
    }
  }

  async function createGroup() {
    if (busy || members.length === 0 || !title.trim()) return;
    setBusy(true);
    try {
      const res = await api.post<{ data: Conversation }>(
        "/api/v1/conversations",
        {
          kind: "group",
          title: title.trim(),
          member_ulids: members.map((m) => m.ulid),
          ...(rules.trim() ? { rules: rules.trim() } : {}),
        },
      );
      goToConversation(res.data);
      toast.info("Group created — it goes live once an admin approves it.");
    } catch (error) {
      setBusy(false);
      toast.error(
        error instanceof ApiError ? error.message : "Couldn't create the group",
      );
    }
  }

  function toggleMember(profile: Profile) {
    setMembers((prev) =>
      prev.some((m) => m.ulid === profile.ulid)
        ? prev.filter((m) => m.ulid !== profile.ulid)
        : [...prev, profile],
    );
  }

  return (
    <Sheet
      open={open}
      onOpenChange={(next) => {
        if (!next) resetAndClose();
        else onOpenChange(true);
      }}
    >
      <SheetContent
        side="bottom"
        className="mx-auto max-h-[90dvh] gap-0 rounded-t-2xl sm:max-w-lg"
      >
        <SheetHeader className="flex-row items-center gap-2">
          {mode !== "choose" ? (
            <button
              type="button"
              onClick={() => setMode("choose")}
              aria-label="Back"
              className="-ms-1 flex size-8 items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
            >
              <ChevronLeft className="size-5" aria-hidden />
            </button>
          ) : null}
          <div className="flex-1">
            <SheetTitle>
              {mode === "choose"
                ? t("newMessage")
                : mode === "dm"
                  ? t("newMessage")
                  : t("newGroup")}
            </SheetTitle>
            <SheetDescription className="sr-only">
              Start a direct message or create a group conversation.
            </SheetDescription>
          </div>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4 pb-6">
          {mode === "choose" ? (
            <div className="flex flex-col gap-2">
              <button
                type="button"
                onClick={() => setMode("dm")}
                className="flex items-center gap-3 rounded-xl border p-4 text-start transition-colors hover:bg-accent/40"
              >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted">
                  <MessageSquarePlus className="size-5" aria-hidden />
                </span>
                <span className="flex flex-col">
                  <span className="text-sm font-medium">{t("newMessage")}</span>
                  <span className="text-sm text-muted-foreground">
                    Start a direct chat with one person
                  </span>
                </span>
              </button>
              <button
                type="button"
                onClick={() => setMode("group")}
                className="flex items-center gap-3 rounded-xl border p-4 text-start transition-colors hover:bg-accent/40"
              >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted">
                  <Users className="size-5" aria-hidden />
                </span>
                <span className="flex flex-col">
                  <span className="text-sm font-medium">{t("newGroup")}</span>
                  <span className="text-sm text-muted-foreground">
                    Chat with several people at once
                  </span>
                </span>
              </button>
            </div>
          ) : null}

          {mode === "dm" ? (
            <ProfileSearch
              autoFocus
              placeholder="Search people to message"
              onSelect={(profile) => void startDm(profile)}
            />
          ) : null}

          {mode === "group" ? (
            <div className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <label htmlFor="group-title" className="text-sm font-medium">
                  Group name
                </label>
                <Input
                  id="group-title"
                  value={title}
                  onChange={(event) => setTitle(event.target.value)}
                  placeholder="e.g. Weekend planning"
                  maxLength={80}
                  className="h-11"
                />
              </div>

              {members.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {members.map((member) => (
                    <span
                      key={member.ulid}
                      className="flex items-center gap-1.5 rounded-full bg-accent py-1 pe-1 ps-1.5 text-sm"
                    >
                      <ProfileAvatar profile={member} className="size-5" />
                      <span className="max-w-32 truncate">{member.name}</span>
                      <button
                        type="button"
                        onClick={() => toggleMember(member)}
                        aria-label={`Remove ${member.name}`}
                        className="flex size-5 items-center justify-center rounded-full text-muted-foreground hover:bg-background hover:text-foreground"
                      >
                        <X className="size-3.5" aria-hidden />
                      </button>
                    </span>
                  ))}
                </div>
              ) : null}

              <ProfileSearch
                placeholder="Add people"
                excludeUlids={members.map((m) => m.ulid)}
                onSelect={toggleMember}
              />

              <div className="flex flex-col gap-1.5">
                <label htmlFor="group-rules" className="text-sm font-medium">
                  Group rules{" "}
                  <span className="font-normal text-muted-foreground">
                    (optional)
                  </span>
                </label>
                <Textarea
                  id="group-rules"
                  value={rules}
                  onChange={(event) => setRules(event.target.value)}
                  placeholder="Set expectations for the group"
                  rows={3}
                  maxLength={1000}
                />
              </div>

              <Button
                type="button"
                className="h-11"
                disabled={busy || members.length === 0 || !title.trim()}
                onClick={() => void createGroup()}
              >
                {busy
                  ? "Creating…"
                  : `Create group${
                      members.length ? ` (${members.length})` : ""
                    }`}
              </Button>
            </div>
          ) : null}
        </div>
      </SheetContent>
    </Sheet>
  );
}

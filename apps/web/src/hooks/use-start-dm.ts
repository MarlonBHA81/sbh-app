"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import { ApiError } from "@/lib/api/client";
import type { Conversation, Profile } from "@/lib/api/types";
import { useMessagesStore } from "@/lib/stores/messages-store";

/**
 * Starts (or reopens) a DM with a profile and navigates to the chat, reusing
 * the same POST /conversations flow as the new-conversation sheet.
 */
export function useStartDm() {
  const router = useRouter();
  const upsertConversation = useMessagesStore((s) => s.upsertConversation);
  const [busyUlid, setBusyUlid] = useState<string | null>(null);

  async function startDm(profile: Pick<Profile, "ulid" | "handle">) {
    if (busyUlid) return;
    setBusyUlid(profile.ulid);
    try {
      const res = await api.post<{ data: Conversation }>(
        "/api/v1/conversations",
        { kind: "dm", profile_ulid: profile.ulid },
      );
      upsertConversation(res.data);
      router.push(`/messages/${res.data.ulid}`);
    } catch (error) {
      setBusyUlid(null);
      if (error instanceof ApiError && error.status === 403) {
        toast.error(`@${profile.handle} isn't accepting messages right now`);
      } else {
        toast.error(
          error instanceof ApiError ? error.message : "Couldn't start the chat",
        );
      }
    }
  }

  return { startDm, busyUlid };
}

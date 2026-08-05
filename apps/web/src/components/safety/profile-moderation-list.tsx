"use client";

import { Ban, VolumeX } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { Paginated, Profile } from "@/lib/api/types";
import { unblockProfile, unmuteProfile } from "@/lib/safety";
import { useModerationStore } from "@/lib/stores/moderation-store";
import { withParam } from "@/lib/utils";

type Kind = "block" | "mute";

const CONFIG: Record<
  Kind,
  {
    endpoint: string;
    action: (handle: string) => Promise<unknown>;
    actionLabel: string;
    busyLabel: string;
    emptyTitle: string;
    emptyDescription: string;
    icon: typeof Ban;
  }
> = {
  block: {
    endpoint: "/api/v1/me/blocks",
    action: unblockProfile,
    actionLabel: "Unblock",
    busyLabel: "Unblocking…",
    emptyTitle: "No blocked accounts",
    emptyDescription:
      "Accounts you block can't see your posts or follow you. They'll appear here.",
    icon: Ban,
  },
  mute: {
    endpoint: "/api/v1/me/mutes",
    action: unmuteProfile,
    actionLabel: "Unmute",
    busyLabel: "Unmuting…",
    emptyTitle: "No muted accounts",
    emptyDescription:
      "Muted accounts stay hidden from your feeds without them knowing. They'll appear here.",
    icon: VolumeX,
  },
};

function Row({
  profile,
  kind,
  onRemoved,
}: {
  profile: Profile;
  kind: Kind;
  onRemoved: (ulid: string) => void;
}) {
  const config = CONFIG[kind];
  const unhideProfile = useModerationStore((s) => s.unhideProfile);
  const [busy, setBusy] = useState(false);

  async function handleAction() {
    if (busy) return;
    setBusy(true);
    try {
      await config.action(profile.handle);
      unhideProfile(profile.ulid);
      onRemoved(profile.ulid);
    } catch (error) {
      setBusy(false);
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : `Couldn't ${config.actionLabel.toLowerCase()}`,
      );
    }
  }

  return (
    <div className="flex items-center gap-3 rounded-xl border p-3">
      <Link href={`/${profile.handle}`} className="shrink-0">
        <ProfileAvatar profile={profile} className="size-10" />
      </Link>
      <Link href={`/${profile.handle}`} className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold">{profile.name}</p>
        <p className="truncate text-xs text-muted-foreground">
          @{profile.handle}
        </p>
      </Link>
      <Button
        variant="outline"
        size="sm"
        className="h-9 shrink-0"
        disabled={busy}
        onClick={() => void handleAction()}
      >
        {busy ? config.busyLabel : config.actionLabel}
      </Button>
    </div>
  );
}

interface ListState {
  key: number;
  items: Profile[];
  cursor: string | null;
  phase: "loading" | "loaded" | "error";
}

export function ProfileModerationList({ kind }: { kind: Kind }) {
  const config = CONFIG[kind];
  const [retry, setRetry] = useState(0);
  const [state, setState] = useState<ListState>({
    key: 0,
    items: [],
    cursor: null,
    phase: "loading",
  });
  const [loadingMore, setLoadingMore] = useState(false);

  if (state.key !== retry) {
    setState({ key: retry, items: [], cursor: null, phase: "loading" });
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Profile>>(config.endpoint)
      .then((res) => {
        if (!cancelled) {
          setState({
            key: retry,
            items: res.data,
            cursor: res.meta.next_cursor,
            phase: "loaded",
          });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ key: retry, items: [], cursor: null, phase: "error" });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [retry, config.endpoint]);

  async function loadMore() {
    if (!state.cursor || loadingMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get<Paginated<Profile>>(
        withParam(config.endpoint, "cursor", state.cursor),
      );
      setState((prev) => {
        const seen = new Set(prev.items.map((p) => p.ulid));
        return {
          ...prev,
          items: [...prev.items, ...res.data.filter((p) => !seen.has(p.ulid))],
          cursor: res.meta.next_cursor,
        };
      });
    } catch {
      // Keep the current list; the button stays for a retry.
    } finally {
      setLoadingMore(false);
    }
  }

  function removeItem(ulid: string) {
    setState((prev) => ({
      ...prev,
      items: prev.items.filter((p) => p.ulid !== ulid),
    }));
  }

  if (state.phase === "loading") {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <div key={i} className="flex items-center gap-3 rounded-xl border p-3">
            <Skeleton className="size-10 rounded-full" />
            <div className="flex flex-1 flex-col gap-1.5">
              <Skeleton className="h-3 w-32" />
              <Skeleton className="h-3 w-20" />
            </div>
            <Skeleton className="h-9 w-20 rounded-md" />
          </div>
        ))}
      </div>
    );
  }

  if (state.phase === "error") {
    return (
      <p className="py-6 text-center text-sm text-muted-foreground">
        Couldn&apos;t load the list.{" "}
        <button
          type="button"
          className="font-medium text-foreground underline-offset-4 hover:underline"
          onClick={() => setRetry((c) => c + 1)}
        >
          Try again
        </button>
      </p>
    );
  }

  if (state.items.length === 0) {
    return (
      <EmptyState
        icon={config.icon}
        title={config.emptyTitle}
        description={config.emptyDescription}
      />
    );
  }

  return (
    <div className="flex flex-col gap-2">
      {state.items.map((profile) => (
        <Row
          key={profile.ulid}
          profile={profile}
          kind={kind}
          onRemoved={removeItem}
        />
      ))}
      {state.cursor ? (
        <button
          type="button"
          onClick={() => void loadMore()}
          disabled={loadingMore}
          className="mt-1 self-center text-sm font-medium text-primary underline-offset-4 hover:underline disabled:opacity-60"
        >
          {loadingMore ? "Loading…" : "Load more"}
        </button>
      ) : null}
    </div>
  );
}

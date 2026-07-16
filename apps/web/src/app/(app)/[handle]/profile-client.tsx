"use client";

import {
  BadgeCheck,
  Ban,
  FileText,
  Flag,
  Globe,
  Lock,
  MapPin,
  MessageCircle,
  MoreHorizontal,
  Pencil,
  Share2,
  Tag,
  UserPlus,
  UserX,
  VolumeX,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import {
  FacebookIcon,
  InstagramIcon,
  LinkedinIcon,
  WhatsappIcon,
} from "@/components/brand/social-icons";
import { useComposer } from "@/components/composer/composer-provider";
import { GuestCta, PublicPostCard } from "@/components/posts/public-post-card";
import { SectionHeader } from "@/components/home/section-header";
import { ScreenHeader } from "@/components/shell/screen-header";
import { EmptyState } from "@/components/empty-state";
import { findRankBadge } from "@/components/gamification/rank-chip";
import { XpProgressCard } from "@/components/gamification/xp-progress-card";
import { ProfileStrengthMeter } from "@/components/profile-strength-meter";
import { PostList } from "@/components/posts/post-list";
import { ProfileAvatar } from "@/components/profile-avatar";
import { ReportDialog } from "@/components/safety/report-dialog";
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
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import * as api from "@/lib/api/client";
import type { PublicPost } from "@/lib/api/public-types";
import type { Conversation, Profile } from "@/lib/api/types";
import {
  blockProfile,
  muteProfile,
  unblockProfile,
  unmuteProfile,
} from "@/lib/safety";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useModerationStore } from "@/lib/stores/moderation-store";

function ProfileSkeleton() {
  return (
    <div className="flex flex-col">
      <Skeleton className="h-32 w-full rounded-xl sm:h-44" />
      <div className="flex flex-col gap-3 px-2">
        <div className="-mt-10 flex items-end justify-between">
          <Skeleton className="size-20 rounded-full ring-4 ring-background" />
          <Skeleton className="h-11 w-28 rounded-md" />
        </div>
        <Skeleton className="h-6 w-40" />
        <Skeleton className="h-4 w-24" />
        <Skeleton className="h-4 w-full max-w-sm" />
        <Skeleton className="h-4 w-56" />
      </div>
    </div>
  );
}

function formatCount(n: number): string {
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

/** Read-only public posts list for logged-out visitors (SEO pages). */
function PublicProfilePosts({
  handle,
  profileName,
}: {
  handle: string;
  profileName?: string;
}) {
  const [posts, setPosts] = useState<PublicPost[] | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: PublicPost[]; meta: { next_cursor: string | null } }>(
        `/api/v1/public/profiles/${encodeURIComponent(handle)}/posts`,
      )
      .then((res) => {
        if (cancelled) return;
        setPosts(res.data);
        setCursor(res.meta.next_cursor);
      })
      .catch(() => {
        if (!cancelled) setPosts([]);
      });
    return () => {
      cancelled = true;
    };
  }, [handle]);

  async function loadMore() {
    if (!cursor || busy) return;
    setBusy(true);
    try {
      const res = await api.get<{
        data: PublicPost[];
        meta: { next_cursor: string | null };
      }>(
        `/api/v1/public/profiles/${encodeURIComponent(handle)}/posts?cursor=${encodeURIComponent(cursor)}`,
      );
      setPosts((prev) => [...(prev ?? []), ...res.data]);
      setCursor(res.meta.next_cursor);
    } catch {
      // Keep what we have; the button stays for a retry.
    } finally {
      setBusy(false);
    }
  }

  if (posts === null) {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-40 w-full rounded-(--radius-card)" />
        ))}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {posts.map((post) => (
        <PublicPostCard key={post.ulid} post={post} />
      ))}
      {cursor ? (
        <Button
          type="button"
          variant="outline"
          className="h-11"
          disabled={busy}
          onClick={() => void loadMore()}
        >
          {busy ? "Loading…" : "Load more"}
        </Button>
      ) : null}
      <GuestCta
        personName={profileName}
        action="follow"
        message="Sign in to follow, message and see the full conversation."
      />
    </div>
  );
}

/** Outbound LinkedIn / Facebook / Instagram / WhatsApp links as 40px circle buttons. */
function SocialLinkRow({
  profile,
  hidden,
}: {
  profile: Profile;
  hidden: boolean;
}) {
  const links = profile.social_links;
  if (hidden || !links) return null;

  const entries = (
    [
      ["LinkedIn", links.linkedin, LinkedinIcon],
      ["Facebook", links.facebook, FacebookIcon],
      ["Instagram", links.instagram, InstagramIcon],
      ["WhatsApp", links.whatsapp, WhatsappIcon],
    ] as const
  ).filter(([, url]) => Boolean(url));

  if (entries.length === 0) return null;

  return (
    <div className="mt-1 flex items-center gap-2">
      {entries.map(([label, url, Icon]) => (
        <a
          key={label}
          href={url as string}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={`${profile.name} on ${label}`}
          className="flex size-10 items-center justify-center rounded-full border border-warmgray bg-card text-teal-text transition-colors hover:bg-accent active:scale-[0.98]"
        >
          <Icon />
        </a>
      ))}
    </div>
  );
}

export function ProfileClient({ handle }: { handle: string }) {
  const router = useRouter();
  const status = useAuthStore((s) => s.status);
  const { openComposer, mutationCount } = useComposer();
  const hideProfile = useModerationStore((s) => s.hideProfile);
  const unhideProfile = useModerationStore((s) => s.unhideProfile);

  const [state, setState] = useState<{
    handle: string;
    profile: Profile | null;
    phase: "loading" | "loaded" | "error";
  }>({ handle, profile: null, phase: "loading" });
  const [followBusy, setFollowBusy] = useState(false);
  const [modBusy, setModBusy] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);
  const [reportOpen, setReportOpen] = useState(false);
  const [blockConfirmOpen, setBlockConfirmOpen] = useState(false);
  // Optimistic mute state (backend may not expose is_muted on the payload).
  const [muteOverride, setMuteOverride] = useState<boolean | null>(null);

  // Reset while rendering when navigating between profiles (no effect needed).
  if (state.handle !== handle) {
    setState({ handle, profile: null, phase: "loading" });
    setMuteOverride(null);
  }

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Profile }>(`/api/v1/profiles/${encodeURIComponent(handle)}`)
      .then((res) => {
        if (!cancelled) {
          setState({ handle, profile: res.data, phase: "loaded" });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ handle, profile: null, phase: "error" });
        }
      });
    return () => {
      cancelled = true;
    };
  }, [handle, reloadKey]);

  const profile = state.profile;
  const loading = state.phase === "loading";
  const notFound = state.phase === "error";

  function setProfile(next: Profile) {
    setState((prev) => ({ ...prev, profile: next }));
  }

  const [dmBusy, setDmBusy] = useState(false);

  async function messageProfile() {
    if (status === "guest") {
      router.push("/login");
      return;
    }
    if (!profile || dmBusy) return;
    setDmBusy(true);
    try {
      const res = await api.post<{ data: Conversation }>(
        "/api/v1/conversations",
        { kind: "dm", profile_ulid: profile.ulid },
      );
      router.push(`/messages/${res.data.ulid}`);
    } catch (error) {
      setDmBusy(false);
      toast.error(
        error instanceof api.ApiError && error.status === 403
          ? `@${handle} isn't accepting messages right now`
          : "Couldn't start the chat",
      );
    }
  }

  async function shareProfile() {
    const url = `${window.location.origin}/${handle}`;
    try {
      if (navigator.share) {
        await navigator.share({ url });
        return;
      }
      await navigator.clipboard.writeText(url);
      toast.success("Link copied");
    } catch {
      // Share sheet dismissed — nothing to do.
    }
  }

  async function toggleFollow() {
    if (!profile || followBusy) return;
    if (status === "guest") {
      router.push("/login");
      return;
    }

    const previous = profile;
    const currentlyFollowing = profile.relationship === "following";
    const currentlyPending = profile.relationship === "pending";

    // Optimistic update.
    let optimistic: Profile;
    if (currentlyFollowing || currentlyPending) {
      optimistic = {
        ...profile,
        relationship: "none",
        followers_count: currentlyFollowing
          ? Math.max(0, profile.followers_count - 1)
          : profile.followers_count,
      };
    } else {
      const willBePending = profile.is_private;
      optimistic = {
        ...profile,
        relationship: willBePending ? "pending" : "following",
        followers_count: willBePending
          ? profile.followers_count
          : profile.followers_count + 1,
      };
    }
    setProfile(optimistic);
    setFollowBusy(true);

    try {
      if (currentlyFollowing || currentlyPending) {
        await api.del(`/api/v1/profiles/${encodeURIComponent(handle)}/follow`);
      } else {
        await api.post(`/api/v1/profiles/${encodeURIComponent(handle)}/follow`);
      }
    } catch (error) {
      setProfile(previous);
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update follow state",
      );
    } finally {
      setFollowBusy(false);
    }
  }

  async function toggleMute() {
    if (!profile || modBusy) return;
    const nextMuted = !(muteOverride ?? profile.is_muted ?? false);
    setMuteOverride(nextMuted);
    setModBusy(true);
    try {
      if (nextMuted) {
        await muteProfile(handle);
        hideProfile(profile.ulid);
        toast.success(`Muted @${handle}`);
      } else {
        await unmuteProfile(handle);
        unhideProfile(profile.ulid);
        toast.success(`Unmuted @${handle}`);
      }
    } catch (error) {
      setMuteOverride(!nextMuted);
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't update mute",
      );
    } finally {
      setModBusy(false);
    }
  }

  async function confirmBlock() {
    if (!profile || modBusy) return;
    setModBusy(true);
    const previous = profile;
    setProfile({ ...profile, relationship: "blocked" });
    try {
      await blockProfile(handle);
      hideProfile(profile.ulid);
      toast.success(`Blocked @${handle}`);
      setBlockConfirmOpen(false);
    } catch (error) {
      setProfile(previous);
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't block",
      );
    } finally {
      setModBusy(false);
    }
  }

  async function unblock() {
    if (!profile || modBusy) return;
    setModBusy(true);
    try {
      await unblockProfile(handle);
      unhideProfile(profile.ulid);
      toast.success(`Unblocked @${handle}`);
      // Refetch so the profile + posts come back with a fresh relationship.
      setReloadKey((k) => k + 1);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't unblock",
      );
    } finally {
      setModBusy(false);
    }
  }

  if (loading) return <ProfileSkeleton />;

  if (notFound || !profile) {
    return (
      <EmptyState
        icon={UserX}
        title="Profile not found"
        description={`We couldn't find @${handle}. The account may have been removed or the handle changed.`}
      >
        <Button asChild variant="outline" className="mt-2 h-11">
          <Link href="/home">Back to home</Link>
        </Button>
      </EmptyState>
    );
  }

  const isGuest = status === "guest";
  const isSelf = profile.relationship === "self";
  const isBlocked = profile.relationship === "blocked";
  const rankBadge = findRankBadge(profile);
  const muted = muteOverride ?? profile.is_muted ?? false;
  const isPrivateHidden =
    profile.is_private &&
    profile.relationship !== "following" &&
    profile.relationship !== "self";

  let followLabel = "Follow";
  if (profile.relationship === "following") {
    followLabel = "Following";
  } else if (profile.relationship === "pending") {
    followLabel = "Requested";
  }

  const menuContent = (
    <DropdownMenuContent align="end" className="w-52">
      {isGuest ? (
        <DropdownMenuItem className="min-h-11 gap-3" onSelect={() => void shareProfile()}>
          <Share2 className="size-4" aria-hidden />
          Share profile
        </DropdownMenuItem>
      ) : isSelf ? (
        <>
          <DropdownMenuItem className="min-h-11 gap-3" onSelect={() => router.push("/settings/profile")}>
            <Pencil className="size-4" aria-hidden />
            Edit profile
          </DropdownMenuItem>
          <DropdownMenuItem className="min-h-11 gap-3" onSelect={() => void shareProfile()}>
            <Share2 className="size-4" aria-hidden />
            Share profile
          </DropdownMenuItem>
        </>
      ) : (
        <>
          <DropdownMenuItem className="min-h-11 gap-3" onSelect={() => void shareProfile()}>
            <Share2 className="size-4" aria-hidden />
            Share profile
          </DropdownMenuItem>
          <DropdownMenuItem className="min-h-11 gap-3" onSelect={() => setReportOpen(true)}>
            <Flag className="size-4" aria-hidden />
            Report @{handle}
          </DropdownMenuItem>
          {!isBlocked ? (
            <DropdownMenuItem
              className="min-h-11 gap-3"
              disabled={modBusy}
              onSelect={(event) => {
                event.preventDefault();
                void toggleMute();
              }}
            >
              <VolumeX className="size-4" aria-hidden />
              {muted ? `Unmute @${handle}` : `Mute @${handle}`}
            </DropdownMenuItem>
          ) : null}
          <DropdownMenuSeparator />
          {isBlocked ? (
            <DropdownMenuItem
              className="min-h-11 gap-3"
              disabled={modBusy}
              onSelect={(event) => {
                event.preventDefault();
                void unblock();
              }}
            >
              <Ban className="size-4" aria-hidden />
              Unblock @{handle}
            </DropdownMenuItem>
          ) : (
            <DropdownMenuItem
              variant="destructive"
              className="min-h-11 gap-3"
              onSelect={() => setBlockConfirmOpen(true)}
            >
              <Ban className="size-4" aria-hidden />
              Block @{handle}
            </DropdownMenuItem>
          )}
        </>
      )}
    </DropdownMenuContent>
  );

  const actionPillBase =
    "flex h-11 flex-1 items-center justify-center gap-2 rounded-full border border-warmgray bg-card font-heading text-[13px] font-medium transition-colors hover:bg-accent active:scale-[0.98] disabled:opacity-50";
  const actionPill = `${actionPillBase} text-text-primary`;

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader
        title="Profile"
        right={
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                aria-label={`More options for @${handle}`}
                className="relative flex size-10 shrink-0 items-center justify-center rounded-full border border-warmgray bg-card text-text-primary transition-colors hover:bg-accent active:scale-[0.98]"
              >
                <MoreHorizontal className="size-5" aria-hidden />
              </button>
            </DropdownMenuTrigger>
            {menuContent}
          </DropdownMenu>
        }
      />

      {!isPrivateHidden && !isBlocked && profile.cover_url ? (
        <div className="h-28 w-full overflow-hidden rounded-(--radius-card) bg-muted sm:h-40">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={profile.cover_url} alt="" className="size-full object-cover" />
        </div>
      ) : null}

      <div className="flex flex-col items-center gap-2 text-center">
        <ProfileAvatar
          profile={profile}
          className="size-22 text-xl ring-2 ring-teal ring-offset-4 ring-offset-background"
        />
        <div className="mt-1 flex flex-col items-center gap-0.5">
          <h2 className="flex items-center gap-1.5 font-heading text-xl font-semibold text-text-primary">
            {profile.name}
            {profile.is_verified ? (
              <span
                aria-label="Verified"
                role="img"
                className="flex size-5 shrink-0 items-center justify-center rounded-full bg-sage"
              >
                <BadgeCheck className="size-3 text-white" strokeWidth={3} aria-hidden />
              </span>
            ) : null}
          </h2>
          <p className="text-sm text-text-secondary">@{profile.handle}</p>
        </div>
        {rankBadge || profile.xp_total > 0 ? (
          <span className="flex items-center gap-1.5 rounded-full bg-teal px-3 py-1 font-heading text-xs font-medium text-white">
            {rankBadge?.icon ? <span aria-hidden>{rankBadge.icon}</span> : null}
            {rankBadge
              ? `${rankBadge.name} · ${formatCount(profile.xp_total)} XP`
              : `${formatCount(profile.xp_total)} XP`}
          </span>
        ) : null}
        {isSelf ? <ProfileStrengthMeter profile={profile} /> : null}
        {!isPrivateHidden && !isBlocked && profile.location ? (
          <span className="flex items-center gap-1 text-[13px] text-text-secondary">
            <MapPin className="size-3.5" aria-hidden />
            {profile.location}
          </span>
        ) : null}
        <SocialLinkRow profile={profile} hidden={isPrivateHidden || isBlocked} />
      </div>

      <div className="flex items-center gap-2">
        {isSelf ? (
          <>
            <Link href="/settings/profile" className={actionPill}>
              <Pencil className="size-4" aria-hidden />
              Edit Profile
            </Link>
            <button type="button" className={actionPill} onClick={() => void shareProfile()}>
              <Share2 className="size-4" aria-hidden />
              Share
            </button>
          </>
        ) : isBlocked ? (
          <button
            type="button"
            className={actionPill}
            disabled={modBusy}
            onClick={() => void unblock()}
          >
            {modBusy ? "Working…" : "Unblock"}
          </button>
        ) : (
          <>
            <button
              type="button"
              disabled={followBusy}
              onClick={() => void toggleFollow()}
              className={
                profile.relationship === "following"
                  ? "flex h-11 flex-1 items-center justify-center gap-2 rounded-full bg-teal font-heading text-[13px] font-medium text-white transition-colors hover:bg-teal-tint active:scale-[0.98] disabled:opacity-50"
                  : `${actionPillBase} text-teal-text`
              }
            >
              <UserPlus className="size-4" aria-hidden />
              {followLabel}
            </button>
            <button
              type="button"
              className={actionPill}
              disabled={dmBusy}
              onClick={() => void messageProfile()}
            >
              <MessageCircle className="size-4" aria-hidden />
              Message
            </button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button type="button" className={actionPill}>
                  <MoreHorizontal className="size-4" aria-hidden />
                  More
                </button>
              </DropdownMenuTrigger>
              {menuContent}
            </DropdownMenu>
          </>
        )}
      </div>

      {!isPrivateHidden && !isBlocked ? (
        <div className="flex flex-col items-center gap-3">
          {profile.bio ? (
            <p className="max-w-sm text-center text-sm leading-relaxed">{profile.bio}</p>
          ) : null}
          <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-text-secondary">
            {profile.category ? (
              <span className="flex items-center gap-1">
                <Tag className="size-3.5" aria-hidden />
                {profile.category}
              </span>
            ) : null}
            {profile.website ? (
              <a
                href={profile.website}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1 text-teal-text underline-offset-4 hover:underline"
              >
                <Globe className="size-3.5" aria-hidden />
                {profile.website.replace(/^https?:\/\//, "")}
              </a>
            ) : null}
          </div>
          <div className="flex gap-6 text-sm">
            <span>
              <strong className="font-heading font-semibold tabular-nums">
                {formatCount(profile.posts_count)}
              </strong>{" "}
              <span className="text-text-secondary">Posts</span>
            </span>
            <span>
              <strong className="font-heading font-semibold tabular-nums">
                {formatCount(profile.followers_count)}
              </strong>{" "}
              <span className="text-text-secondary">Followers</span>
            </span>
            <span>
              <strong className="font-heading font-semibold tabular-nums">
                {formatCount(profile.following_count)}
              </strong>{" "}
              <span className="text-text-secondary">Following</span>
            </span>
            {profile.helpful_count && profile.helpful_count > 0 ? (
              <span>
                <strong className="font-heading font-semibold tabular-nums text-teal-text">
                  {formatCount(profile.helpful_count)}
                </strong>{" "}
                <span className="text-text-secondary">Helpful</span>
              </span>
            ) : null}
          </div>
        </div>
      ) : null}

      {isBlocked ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
              <Ban className="size-6 text-muted-foreground" aria-hidden />
            </div>
            <p className="font-semibold">You blocked @{profile.handle}</p>
            <p className="max-w-xs text-sm text-muted-foreground">
              They can&apos;t follow you or see your posts, and you won&apos;t
              see theirs. Unblock to restore their profile.
            </p>
            <Button
              variant="outline"
              className="mt-1 h-11"
              disabled={modBusy}
              onClick={() => void unblock()}
            >
              {modBusy ? "Working…" : "Unblock"}
            </Button>
          </CardContent>
        </Card>
      ) : isPrivateHidden ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
              <Lock className="size-6 text-muted-foreground" aria-hidden />
            </div>
            <p className="font-semibold">This account is private</p>
            <p className="max-w-xs text-sm text-muted-foreground">
              Follow @{profile.handle} to see their posts and profile details.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="flex flex-col gap-3">
        <SectionHeader title="Activity" />
        <Tabs defaultValue="posts">
          <TabsList className="w-full">
            <TabsTrigger value="posts" className="h-10 flex-1">
              Posts
            </TabsTrigger>
            <TabsTrigger value="about" className="h-10 flex-1">
              About
            </TabsTrigger>
          </TabsList>
          <TabsContent value="posts" className="pt-2">
            {isGuest ? (
              <PublicProfilePosts handle={handle} profileName={profile.name} />
            ) : (
            <PostList
              buildUrl={(cursor) =>
                `/api/v1/profiles/${encodeURIComponent(handle)}/posts${cursor ? `?cursor=${encodeURIComponent(cursor)}` : ""}`
              }
              refreshKey={`${handle}:${isSelf ? mutationCount : 0}`}
              emptyState={
                <EmptyState
                  icon={FileText}
                  title="No posts yet"
                  description={
                    isSelf
                      ? "Share your first post — it'll show up here."
                      : `@${profile.handle} hasn't posted yet.`
                  }
                >
                  {isSelf ? (
                    <Button
                      className="mt-2 h-11"
                      onClick={() => openComposer()}
                    >
                      Write a post
                    </Button>
                  ) : null}
                </EmptyState>
              }
            />
            )}
          </TabsContent>
          <TabsContent value="about" className="flex flex-col gap-3 pt-2">
            {isSelf ? <XpProgressCard /> : null}
            <Card>
              <CardContent className="flex flex-col gap-3 py-6 text-sm">
                <p className="leading-relaxed">
                  {profile.bio ?? (
                    <span className="text-muted-foreground">No bio yet.</span>
                  )}
                </p>
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-muted-foreground">
                  {profile.category ? (
                    <>
                      <dt className="font-medium text-foreground">Category</dt>
                      <dd>{profile.category}</dd>
                    </>
                  ) : null}
                  {profile.location ? (
                    <>
                      <dt className="font-medium text-foreground">Location</dt>
                      <dd>{profile.location}</dd>
                    </>
                  ) : null}
                  {profile.website ? (
                    <>
                      <dt className="font-medium text-foreground">Website</dt>
                      <dd>
                        <a
                          href={profile.website}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="underline-offset-4 hover:underline"
                        >
                          {profile.website}
                        </a>
                      </dd>
                    </>
                  ) : null}
                </dl>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
        </div>
      )}

      <ReportDialog
        open={reportOpen}
        onOpenChange={setReportOpen}
        reportableType="profile"
        reportableUlid={profile.ulid}
        contextLabel={`@${profile.handle}`}
      />

      <AlertDialog open={blockConfirmOpen} onOpenChange={setBlockConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Block @{profile.handle}?</AlertDialogTitle>
            <AlertDialogDescription>
              They won&apos;t be able to follow you or see your posts, and you
              won&apos;t see theirs. Any follow between you will be removed. You
              can unblock them any time.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={modBusy}
              onClick={(event) => {
                event.preventDefault();
                void confirmBlock();
              }}
            >
              {modBusy ? "Blocking…" : "Block"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

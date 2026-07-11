"use client";

import {
  BadgeCheck,
  FileText,
  Globe,
  Lock,
  MapPin,
  Tag,
  UserX,
} from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useEffect, useState } from "react";
import { toast } from "sonner";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { PostList } from "@/components/posts/post-list";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import * as api from "@/lib/api/client";
import type { Profile } from "@/lib/api/types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

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

export default function ProfilePage({
  params,
}: {
  params: Promise<{ handle: string }>;
}) {
  const { handle } = use(params);
  const router = useRouter();
  const status = useAuthStore((s) => s.status);
  const { openComposer, mutationCount } = useComposer();

  const [state, setState] = useState<{
    handle: string;
    profile: Profile | null;
    phase: "loading" | "loaded" | "error";
  }>({ handle, profile: null, phase: "loading" });
  const [followBusy, setFollowBusy] = useState(false);

  // Reset while rendering when navigating between profiles (no effect needed).
  if (state.handle !== handle) {
    setState({ handle, profile: null, phase: "loading" });
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
  }, [handle]);

  const profile = state.profile;
  const loading = state.phase === "loading";
  const notFound = state.phase === "error";

  function setProfile(next: Profile) {
    setState((prev) => ({ ...prev, profile: next }));
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

  const isSelf = profile.relationship === "self";
  const isPrivateHidden =
    profile.is_private &&
    profile.relationship !== "following" &&
    profile.relationship !== "self";

  let followLabel = "Follow";
  let followVariant: "default" | "outline" = "default";
  if (profile.relationship === "following") {
    followLabel = "Following";
    followVariant = "outline";
  } else if (profile.relationship === "pending") {
    followLabel = "Requested";
    followVariant = "outline";
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col">
        <div className="h-32 w-full overflow-hidden rounded-xl bg-muted sm:h-44">
          {profile.cover_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={profile.cover_url}
              alt=""
              className="size-full object-cover"
            />
          ) : null}
        </div>
        <div className="flex flex-col gap-3 px-2">
          <div className="-mt-10 flex items-end justify-between">
            <ProfileAvatar
              profile={profile}
              className="size-20 border-4 border-background text-lg"
            />
            {isSelf ? (
              <Button asChild variant="outline" className="h-11">
                <Link href="/settings/profile">Edit profile</Link>
              </Button>
            ) : (
              <Button
                variant={followVariant}
                className="h-11 min-w-28"
                disabled={followBusy}
                onClick={() => void toggleFollow()}
              >
                {followLabel}
              </Button>
            )}
          </div>
          <div className="flex flex-col gap-0.5">
            <h1 className="flex items-center gap-1.5 text-xl font-semibold tracking-tight">
              {profile.name}
              {profile.is_verified ? (
                <BadgeCheck
                  className="size-5 shrink-0 text-sky-500"
                  aria-label="Verified"
                />
              ) : null}
            </h1>
            <p className="text-sm text-muted-foreground">@{profile.handle}</p>
          </div>
          {!isPrivateHidden && profile.bio ? (
            <p className="text-sm leading-relaxed">{profile.bio}</p>
          ) : null}
          {!isPrivateHidden ? (
            <>
              <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                {profile.category ? (
                  <span className="flex items-center gap-1">
                    <Tag className="size-3.5" aria-hidden />
                    {profile.category}
                  </span>
                ) : null}
                {profile.location ? (
                  <span className="flex items-center gap-1">
                    <MapPin className="size-3.5" aria-hidden />
                    {profile.location}
                  </span>
                ) : null}
                {profile.website ? (
                  <a
                    href={profile.website}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-foreground underline-offset-4 hover:underline"
                  >
                    <Globe className="size-3.5" aria-hidden />
                    {profile.website.replace(/^https?:\/\//, "")}
                  </a>
                ) : null}
              </div>
              <div className="flex gap-5 text-sm">
                <span>
                  <strong className="font-semibold tabular-nums">
                    {formatCount(profile.posts_count)}
                  </strong>{" "}
                  <span className="text-muted-foreground">Posts</span>
                </span>
                <span>
                  <strong className="font-semibold tabular-nums">
                    {formatCount(profile.followers_count)}
                  </strong>{" "}
                  <span className="text-muted-foreground">Followers</span>
                </span>
                <span>
                  <strong className="font-semibold tabular-nums">
                    {formatCount(profile.following_count)}
                  </strong>{" "}
                  <span className="text-muted-foreground">Following</span>
                </span>
              </div>
            </>
          ) : null}
        </div>
      </div>

      {isPrivateHidden ? (
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
          </TabsContent>
          <TabsContent value="about" className="pt-2">
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
      )}
    </div>
  );
}

"use client";

import { BadgeCheck, Compass, MapPin } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { GuestCta, PublicPostCard } from "@/components/posts/public-post-card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  fetchPublicBusinesses,
  fetchPublicFeed,
  type PublicBusiness,
} from "@/lib/api/public-feed";
import type { PublicPost } from "@/lib/api/public-types";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

/** One directory row linking to a business's (fully viewable) profile page. */
function BusinessRow({ business }: { business: PublicBusiness }) {
  return (
    <Link
      href={`/${business.handle}`}
      className="flex items-center gap-3 rounded-(--radius-card) border border-warmgray bg-card p-3 shadow-card transition-colors hover:border-warmgray-tint active:scale-[0.99]"
    >
      {business.avatar_url ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={business.avatar_url}
          alt=""
          className="size-11 rounded-full object-cover"
        />
      ) : (
        <span className="flex size-11 items-center justify-center rounded-full bg-plum font-heading text-sm font-medium text-white">
          {business.name.slice(0, 2).toUpperCase()}
        </span>
      )}
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="flex items-center gap-1.5">
          <span className="truncate font-heading text-sm font-medium text-text-primary">
            {business.name}
          </span>
          {business.is_verified ? (
            <BadgeCheck className="size-3.5 shrink-0 text-sage" aria-hidden />
          ) : null}
        </span>
        <span className="flex items-center gap-2 truncate text-xs text-text-secondary">
          {business.category ? <span>{business.category}</span> : null}
          {business.location ? (
            <span className="flex items-center gap-0.5">
              <MapPin className="size-3" aria-hidden />
              {business.location}
            </span>
          ) : null}
        </span>
      </span>
    </Link>
  );
}

/**
 * Guest-facing Explore surface (UX pattern 3 — reciprocity). Logged-out
 * visitors browse the public feed and business directory in full; the signup
 * ask only appears as a CTA when they'd act. Authed users are sent to /home.
 */
export function ExploreView() {
  const status = useAuthStore((s) => s.status);
  const router = useRouter();
  const [posts, setPosts] = useState<PublicPost[] | null>(null);
  const [businesses, setBusinesses] = useState<PublicBusiness[]>([]);

  useEffect(() => {
    if (status === "authed") {
      router.replace("/home");
      return;
    }
    if (status !== "guest") return;
    let cancelled = false;
    void Promise.all([fetchPublicFeed(), fetchPublicBusinesses()]).then(
      ([feed, dir]) => {
        if (cancelled) return;
        setPosts(feed.data);
        setBusinesses(dir);
      },
    );
    return () => {
      cancelled = true;
    };
  }, [status, router]);

  if (status !== "guest") {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-40 w-full rounded-(--radius-card)" />
        ))}
      </div>
    );
  }

  const loading = posts === null;
  const hasPosts = (posts?.length ?? 0) > 0;

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col gap-1">
        <h1 className="font-heading text-xl font-semibold text-text-primary">
          Explore SBH
        </h1>
        <p className="text-sm text-text-secondary">
          See what small business owners are sharing. Reading is open to
          everyone — join when you want to join in.
        </p>
      </div>

      {businesses.length > 0 ? (
        <section className="flex flex-col gap-2">
          <h2 className="font-heading text-[15px] font-semibold text-text-primary">
            Businesses to discover
          </h2>
          {businesses.map((b) => (
            <BusinessRow key={b.handle} business={b} />
          ))}
        </section>
      ) : null}

      <section className="flex flex-col gap-3">
        {businesses.length > 0 ? (
          <h2 className="font-heading text-[15px] font-semibold text-text-primary">
            Latest public posts
          </h2>
        ) : null}

        {loading ? (
          <div className="flex flex-col gap-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-40 w-full rounded-(--radius-card)" />
            ))}
          </div>
        ) : hasPosts ? (
          <>
            {posts!.map((post) => (
              <PublicPostCard key={post.ulid} post={post} />
            ))}
            <GuestCta message="Enjoying what you see? Join SBH to like, reply and follow." />
          </>
        ) : (
          <EmptyState
            icon={Compass}
            title="Public posts are on the way"
            description="Open a profile to read someone's posts in full — reading never needs an account. Join to start posting yourself."
          >
            <Button asChild className="mt-2 h-11">
              <Link href="/register">Join SBH Community</Link>
            </Button>
          </EmptyState>
        )}
      </section>
    </div>
  );
}

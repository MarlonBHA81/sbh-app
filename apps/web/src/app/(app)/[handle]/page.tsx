import type { Metadata } from "next";

import { fetchPublicProfile, truncate } from "@/lib/seo";

import { ProfileClient } from "./profile-client";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ handle: string }>;
}): Promise<Metadata> {
  const { handle } = await params;
  const profile = await fetchPublicProfile(handle);

  // Unknown/private handle: keep it out of the index; the page still renders
  // client-side for authenticated viewers.
  if (!profile) {
    return { robots: { index: false, follow: false } };
  }

  const description = profile.bio
    ? truncate(profile.bio, 160)
    : `${profile.name} (@${profile.handle}) on SBH Community — the social app for small business owners.`;
  const fullTitle = `${profile.name} (@${profile.handle}) · SBH Community`;

  return {
    // Root layout's template appends " · SBH Community".
    title: `${profile.name} (@${profile.handle})`,
    description,
    openGraph: {
      type: "profile",
      title: fullTitle,
      description,
      images: profile.avatar_url
        ? [{ url: profile.avatar_url }]
        : [{ url: "/og.png", width: 1200, height: 630 }],
    },
    twitter: {
      card: profile.avatar_url ? "summary" : "summary_large_image",
      title: fullTitle,
      description,
      images: [profile.avatar_url ?? "/og.png"],
    },
  };
}

export default async function ProfilePage({
  params,
}: {
  params: Promise<{ handle: string }>;
}) {
  const { handle } = await params;
  return <ProfileClient handle={handle} />;
}

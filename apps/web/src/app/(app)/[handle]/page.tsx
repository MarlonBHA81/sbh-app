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
      ...(profile.avatar_url ? { images: [{ url: profile.avatar_url }] } : {}),
    },
    twitter: {
      card: "summary",
      title: fullTitle,
      description,
      ...(profile.avatar_url ? { images: [profile.avatar_url] } : {}),
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

import type { Metadata } from "next";

import { fetchPublicPost, truncate } from "@/lib/seo";

import { PostDetailClient } from "./post-detail-client";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ ulid: string }>;
}): Promise<Metadata> {
  const { ulid } = await params;
  const post = await fetchPublicPost(ulid);

  // Private / unpublished / followers-only posts 404 from the public API.
  if (!post) {
    return { robots: { index: false, follow: false } };
  }

  const body = (post.body ?? "").trim();
  const authorName = post.profile.name;
  const snippet = body ? truncate(body, 60) : "";
  const title = snippet
    ? `${authorName} on SBH Community: “${snippet}”`
    : `${authorName} on SBH Community`;
  const description = body
    ? truncate(body, 160)
    : `See this post by ${authorName} (@${post.profile.handle}) on SBH Community.`;

  // Omit imagery for sensitive posts.
  const thumb = post.sensitive
    ? null
    : (post.media[0]?.thumb_url ?? post.media[0]?.url ?? null);

  return {
    // Fully-formed title; bypass the root " · SBH Community" template.
    title: { absolute: title },
    description,
    openGraph: {
      type: "article",
      title,
      description,
      ...(post.published_at ? { publishedTime: post.published_at } : {}),
      images: thumb
        ? [{ url: thumb }]
        : [{ url: "/og.png", width: 1200, height: 630 }],
    },
    twitter: {
      card: "summary_large_image",
      title,
      description,
      images: [thumb ?? "/og.png"],
    },
  };
}

export default async function PostDetailPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <PostDetailClient ulid={ulid} />;
}

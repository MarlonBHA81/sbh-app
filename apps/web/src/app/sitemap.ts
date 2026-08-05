import type { MetadataRoute } from "next";

import { serverApiBase } from "@/lib/seo";

const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000";

interface SitemapPayload {
  profiles: { handle: string; updated_at: string | null }[];
  posts: { ulid: string; updated_at: string | null }[];
}

/** Refresh at most hourly — matches the API-side cache. */
export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const staticRoutes: MetadataRoute.Sitemap = [
    "",
    "/login",
    "/register",
    "/forgot-password",
    "/discover",
  ].map((route) => ({
    url: `${appUrl}${route}`,
    lastModified: new Date(),
    changeFrequency: "weekly",
    priority: route === "" ? 1 : 0.7,
  }));

  // Public profiles and posts are viewable logged-out, so they belong in
  // the index. Sourced from the cached public sitemap endpoint; a failure
  // degrades to the static routes rather than breaking the sitemap.
  let dynamicRoutes: MetadataRoute.Sitemap = [];
  try {
    const res = await fetch(`${serverApiBase()}/api/v1/public/sitemap`, {
      next: { revalidate: 3600 },
    });
    if (res.ok) {
      const { data } = (await res.json()) as { data: SitemapPayload };
      dynamicRoutes = [
        ...data.profiles.map((profile) => ({
          url: `${appUrl}/${profile.handle}`,
          ...(profile.updated_at && {
            lastModified: new Date(profile.updated_at),
          }),
          changeFrequency: "daily" as const,
          priority: 0.8,
        })),
        ...data.posts.map((post) => ({
          url: `${appUrl}/p/${post.ulid}`,
          ...(post.updated_at && { lastModified: new Date(post.updated_at) }),
          changeFrequency: "weekly" as const,
          priority: 0.6,
        })),
      ];
    }
  } catch {
    // API unreachable during build/preview — static routes only.
  }

  return [...staticRoutes, ...dynamicRoutes];
}

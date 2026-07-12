import type { MetadataRoute } from "next";

const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: "/",
      // Private / authenticated-only areas stay out of the index.
      disallow: [
        "/settings",
        "/messages",
        "/drafts",
        "/ads",
        "/insights",
        "/notifications",
      ],
    },
    sitemap: `${appUrl}/sitemap.xml`,
  };
}

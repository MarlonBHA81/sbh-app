import type { MetadataRoute } from "next";

const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000";

// Static top-level routes only. Profile/post pages are excluded because they
// are personal, high-cardinality and gated behind auth for most content.
export default function sitemap(): MetadataRoute.Sitemap {
  const routes = ["", "/login", "/register", "/forgot-password", "/discover"];
  const lastModified = new Date();

  return routes.map((route) => ({
    url: `${appUrl}${route}`,
    lastModified,
    changeFrequency: "weekly",
    priority: route === "" ? 1 : 0.7,
  }));
}

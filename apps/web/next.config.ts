import withSerwistInit from "@serwist/next";
import type { NextConfig } from "next";

const withSerwist = withSerwistInit({
  swSrc: "src/sw.ts",
  swDest: "public/sw.js",
});

const nextConfig: NextConfig = {
  output: "standalone",
  images: {
    // Media is served by the Laravel API / storage disk.
    remotePatterns: [
      { protocol: "http", hostname: "localhost" },
      { protocol: "https", hostname: "**" },
    ],
  },
};

// Serwist's webpack plugin is incompatible with Turbopack, so the service
// worker is only wired into production builds (`next build --webpack`).
// `next dev` keeps running on Turbopack without a service worker.
export default process.env.NODE_ENV === "production"
  ? withSerwist(nextConfig)
  : nextConfig;

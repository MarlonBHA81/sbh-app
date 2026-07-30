import withSerwistInit from "@serwist/next";
import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const withSerwist = withSerwistInit({
  swSrc: "src/sw.ts",
  swDest: "public/sw.js",
});

// Restrict the Next image optimizer to our own media host, derived from the
// public API/app URL at build time. A wildcard (`hostname: "**"`) would let
// anyone use /_next/image as an open proxy against arbitrary hosts (bandwidth
// abuse + limited SSRF); all app media is served same-origin from /storage.
const apiHost = (() => {
  try {
    return new URL(
      process.env.NEXT_PUBLIC_API_URL ??
        process.env.NEXT_PUBLIC_APP_URL ??
        "http://localhost:8000",
    ).hostname;
  } catch {
    return "localhost";
  }
})();

const nextConfig: NextConfig = {
  output: "standalone",
  images: {
    // Media is served same-origin by the Laravel API / storage disk.
    remotePatterns: [
      { protocol: "http", hostname: "localhost" },
      { protocol: "https", hostname: apiHost },
      { protocol: "http", hostname: apiHost },
    ],
  },
};

// Serwist's webpack plugin is incompatible with Turbopack, so the service
// worker is only wired into production builds (`next build --webpack`).
// `next dev` keeps running on Turbopack without a service worker.
// next-intl wraps both so its request config is available in every mode.
export default process.env.NODE_ENV === "production"
  ? withNextIntl(withSerwist(nextConfig))
  : withNextIntl(nextConfig);

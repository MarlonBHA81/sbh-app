import { withSentryConfig } from "@sentry/nextjs";
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
const composedConfig =
  process.env.NODE_ENV === "production"
    ? withNextIntl(withSerwist(nextConfig))
    : withNextIntl(nextConfig);

// Sentry wraps the outermost config. Source-map upload only runs during a
// production build when SENTRY_AUTH_TOKEN is present; otherwise it is a no-op,
// and with no DSN the runtime SDK is inert.
export default withSentryConfig(composedConfig, {
  org: process.env.SENTRY_ORG,
  project: process.env.SENTRY_PROJECT,
  // Only print upload logs in CI/verbose runs.
  silent: !process.env.CI,
  // Hide framework frames + widen so client bundles map cleanly.
  widenClientFileUpload: true,
  // Skip source-map upload entirely when no auth token is configured, so local
  // and token-less CI builds don't warn or fail.
  sourcemaps: { disable: !process.env.SENTRY_AUTH_TOKEN },
  // Tunnel Sentry requests through the app to dodge ad-blockers (same-origin).
  tunnelRoute: "/monitoring",
  disableLogger: true,
});

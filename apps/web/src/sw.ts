/// <reference lib="webworker" />
import { defaultCache } from "@serwist/next/worker";
import type { PrecacheEntry, SerwistGlobalConfig } from "serwist";
import {
  CacheFirst,
  ExpirationPlugin,
  NetworkFirst,
  NetworkOnly,
  Serwist,
  StaleWhileRevalidate,
} from "serwist";

declare global {
  interface WorkerGlobalScope extends SerwistGlobalConfig {
    __SW_MANIFEST: (PrecacheEntry | string)[] | undefined;
  }
}

declare const self: ServiceWorkerGlobalScope;

const serwist = new Serwist({
  precacheEntries: self.__SW_MANIFEST,
  skipWaiting: true,
  clientsClaim: true,
  navigationPreload: true,
  runtimeCaching: [
    {
      // Keep the last-known session/profile snapshot available offline
      // (matches the API host whether same-origin or not).
      matcher: ({ url }) => url.pathname === "/api/v1/me",
      handler: new NetworkFirst({
        cacheName: "sbh-api-me",
        networkTimeoutSeconds: 3,
        plugins: [
          new ExpirationPlugin({
            maxEntries: 4,
            maxAgeSeconds: 24 * 60 * 60, // 1 day
          }),
        ],
      }),
    },
    {
      // Feed pages: serve fresh when online, fall back to the last cached
      // response (so the timeline is readable offline).
      matcher: ({ url }) => url.pathname.startsWith("/api/v1/feeds/"),
      handler: new NetworkFirst({
        cacheName: "sbh-api-feeds",
        networkTimeoutSeconds: 3,
        plugins: [
          new ExpirationPlugin({
            maxEntries: 50,
            maxAgeSeconds: 24 * 60 * 60, // 1 day
          }),
        ],
      }),
    },
    {
      // Notifications list: same network-first strategy for offline reads.
      matcher: ({ url }) => url.pathname === "/api/v1/notifications",
      handler: new NetworkFirst({
        cacheName: "sbh-api-notifications",
        networkTimeoutSeconds: 3,
        plugins: [
          new ExpirationPlugin({
            maxEntries: 10,
            maxAgeSeconds: 24 * 60 * 60, // 1 day
          }),
        ],
      }),
    },
    {
      // Media thumbnails: show instantly from cache, refresh in the
      // background. Matches the storage disk and any `thumb` variant paths.
      matcher: ({ url, request }) =>
        request.destination === "image" &&
        (url.pathname.includes("/storage/") ||
          url.pathname.includes("thumb")),
      handler: new StaleWhileRevalidate({
        cacheName: "sbh-media-thumbs",
        plugins: [
          new ExpirationPlugin({
            maxEntries: 200,
            maxAgeSeconds: 7 * 24 * 60 * 60, // 7 days
            purgeOnQuotaError: true,
          }),
        ],
      }),
    },
    {
      // App icons and fonts change rarely; serve from cache first.
      matcher: ({ url, request, sameOrigin }) =>
        (sameOrigin && url.pathname.startsWith("/icons/")) ||
        request.destination === "font",
      handler: new CacheFirst({
        cacheName: "sbh-static-assets",
        plugins: [
          new ExpirationPlugin({
            maxEntries: 32,
            maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
          }),
        ],
      }),
    },
    {
      // ---------------------------------------------------------------------
      // Everything else under /api/ is NEVER cached. This rule must stay
      // immediately above ...defaultCache.
      //
      // @serwist/next's defaultCache ends with a broad NetworkFirst rule for
      // any same-origin /api/ GET, keyed by URL alone. Profile scoping in this
      // app travels in the X-Profile-Id *header*, which is not part of the
      // cache key — so conversations, DM bodies, unread counts and business
      // matches were all being stored in origin-wide Cache Storage under keys
      // that are identical across users. On a shared device a slow network
      // could then serve the previous user's private data to the next one.
      //
      // The three endpoints deliberately cached for offline use are matched by
      // the rules above; runtimeCaching is first-match-wins, so they still win.
      // ---------------------------------------------------------------------
      matcher: ({ url, sameOrigin }) =>
        sameOrigin && url.pathname.startsWith("/api/"),
      handler: new NetworkOnly(),
    },
    ...defaultCache,
  ],
  fallbacks: {
    entries: [
      {
        // Shown when a navigation request fails and the route isn't cached.
        url: "/~offline",
        matcher: ({ request }) => request.destination === "document",
      },
    ],
  },
});

serwist.addEventListeners();

// --- Per-user cache purge -------------------------------------------------
// Cache Storage is origin-wide, not per-account, so anything cached from an
// authenticated response outlives the session that produced it. The app posts
// this message on logout AND on profile switch so the next user (or the next
// profile) on a shared device can't be served the previous one's data.
//
// This is an ALLOW-list of what survives, not a deny-list of what to delete.
// The previous deny-list named three caches explicitly and silently missed the
// "apis" bucket that @serwist/next's defaultCache adds — the exact leak this
// exists to prevent. Inverting it means a bucket introduced by a dependency
// upgrade is purged by default rather than forgotten.
const survivesPurge = (name: string): boolean =>
  // Icons/fonts: not user data, expensive to refetch.
  name === "sbh-static-assets" ||
  // Serwist's own precache of build assets; it manages its own lifecycle.
  name.includes("precache");

async function purgeUserCaches(): Promise<void> {
  const names = await caches.keys();
  await Promise.all(
    names.filter((name) => !survivesPurge(name)).map((name) => caches.delete(name)),
  );
}

self.addEventListener("message", (event) => {
  if ((event.data as { type?: string } | undefined)?.type === "sbh-purge-user-cache") {
    event.waitUntil(purgeUserCaches());
  }
});

// --- Web push -------------------------------------------------------------

interface PushPayload {
  title?: string;
  body?: string;
  icon?: string;
  data?: { url?: string };
}

self.addEventListener("push", (event) => {
  if (!event.data) return;
  let payload: PushPayload = {};
  try {
    payload = event.data.json() as PushPayload;
  } catch {
    payload = { body: event.data.text() };
  }

  const title = payload.title ?? "SBH Community";
  const url = payload.data?.url ?? "/notifications";

  event.waitUntil(
    self.registration.showNotification(title, {
      body: payload.body ?? "",
      icon: payload.icon ?? "/icons/icon-192.png",
      badge: "/icons/icon-192.png",
      data: { url },
    }),
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const data = (event.notification.data ?? {}) as { url?: string };
  const targetUrl = data.url ?? "/notifications";

  event.waitUntil(
    (async () => {
      const clientList = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
      });
      for (const client of clientList) {
        // Focus an existing tab and route it to the target.
        if ("focus" in client) {
          await client.focus();
          if ("navigate" in client) {
            try {
              await client.navigate(targetUrl);
            } catch {
              // Cross-origin or unsupported — fall back to the open URL below.
            }
          }
          return;
        }
      }
      await self.clients.openWindow(targetUrl);
    })(),
  );
});

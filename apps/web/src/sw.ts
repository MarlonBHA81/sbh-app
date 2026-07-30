/// <reference lib="webworker" />
import { defaultCache } from "@serwist/next/worker";
import type { PrecacheEntry, SerwistGlobalConfig } from "serwist";
import {
  CacheFirst,
  ExpirationPlugin,
  NetworkFirst,
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
// The API caches below hold per-user data (session/profile, feeds,
// notifications) in origin-wide Cache Storage. On logout the app posts this
// message so the next user on a shared device can't be served the previous
// user's cached data.
const USER_CACHES = ["sbh-api-me", "sbh-api-feeds", "sbh-api-notifications"];

self.addEventListener("message", (event) => {
  if ((event.data as { type?: string } | undefined)?.type === "sbh-purge-user-cache") {
    event.waitUntil(
      Promise.all(USER_CACHES.map((name) => caches.delete(name))),
    );
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

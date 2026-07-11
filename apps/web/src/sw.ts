/// <reference lib="webworker" />
import { defaultCache } from "@serwist/next/worker";
import type { PrecacheEntry, SerwistGlobalConfig } from "serwist";
import {
  CacheFirst,
  ExpirationPlugin,
  NetworkFirst,
  Serwist,
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
});

serwist.addEventListeners();

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

  const title = payload.title ?? "SBH";
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

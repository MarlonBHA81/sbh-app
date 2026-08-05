"use client";

import * as api from "@/lib/api/client";

/** Whether the browser can register a service worker and receive web push. */
export function isPushSupported(): boolean {
  return (
    typeof window !== "undefined" &&
    "serviceWorker" in navigator &&
    "PushManager" in window &&
    "Notification" in window
  );
}

/** Current Notification permission, or "default" when unsupported. */
export function pushPermission(): NotificationPermission {
  if (typeof window === "undefined" || !("Notification" in window)) {
    return "default";
  }
  return Notification.permission;
}

/** VAPID base64url → Uint8Array for applicationServerKey. */
function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) output[i] = raw.charCodeAt(i);
  return output;
}

function arrayBufferToBase64(buffer: ArrayBuffer | null): string {
  if (!buffer) return "";
  const bytes = new Uint8Array(buffer);
  let binary = "";
  for (let i = 0; i < bytes.length; i += 1) {
    binary += String.fromCharCode(bytes[i]);
  }
  return window.btoa(binary);
}

async function readyRegistration(): Promise<ServiceWorkerRegistration | null> {
  if (!isPushSupported()) return null;
  try {
    return await navigator.serviceWorker.ready;
  } catch {
    return null;
  }
}

export type PushSubscribeResult =
  | "subscribed"
  | "denied"
  | "unsupported"
  | "disabled" // server has push turned off (public-key 404)
  | "error";

/**
 * Opt in to web push: fetch the server VAPID key (skip silently if push is
 * disabled server-side), request permission, subscribe via the SW push
 * manager and register the subscription with the API.
 */
export async function subscribeToPush(): Promise<PushSubscribeResult> {
  if (!isPushSupported()) return "unsupported";

  let publicKey: string;
  try {
    const res = await api.get<{ key: string }>(
      "/api/v1/me/push-subscriptions/public-key",
    );
    publicKey = res.key;
  } catch (error) {
    // 404 => push disabled server-side: opt out silently.
    if (error instanceof api.ApiError && error.status === 404) return "disabled";
    return "error";
  }
  if (!publicKey) return "disabled";

  const permission = await Notification.requestPermission();
  if (permission !== "granted") return "denied";

  const registration = await readyRegistration();
  if (!registration) return "unsupported";

  try {
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey) as BufferSource,
    });

    await api.post("/api/v1/me/push-subscriptions", {
      endpoint: subscription.endpoint,
      keys: {
        p256dh: arrayBufferToBase64(subscription.getKey("p256dh")),
        auth: arrayBufferToBase64(subscription.getKey("auth")),
      },
    });
    return "subscribed";
  } catch {
    return "error";
  }
}

/** Remove the active push subscription locally and on the server. */
export async function unsubscribeFromPush(): Promise<boolean> {
  const registration = await readyRegistration();
  if (!registration) return false;
  try {
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return true;
    const { endpoint } = subscription;
    await subscription.unsubscribe();
    try {
      await api.del("/api/v1/me/push-subscriptions", { endpoint });
    } catch {
      // Local unsubscribe already succeeded; server cleanup is best-effort.
    }
    return true;
  } catch {
    return false;
  }
}

/** Whether a push subscription currently exists for this browser. */
export async function isPushSubscribed(): Promise<boolean> {
  const registration = await readyRegistration();
  if (!registration) return false;
  try {
    return (await registration.pushManager.getSubscription()) !== null;
  } catch {
    return false;
  }
}

"use client";

import { useSyncExternalStore } from "react";

const noopSubscribe = () => () => {};

/**
 * Read a client-only value (localStorage, Notification.permission, feature
 * detection …) without a hydration mismatch or a setState-in-effect. The
 * server render and first client render use `serverValue`; subsequent reads
 * use `getClientValue`. The value is assumed static for the component's life.
 */
export function useClientValue<T>(getClientValue: () => T, serverValue: T): T {
  return useSyncExternalStore(noopSubscribe, getClientValue, () => serverValue);
}

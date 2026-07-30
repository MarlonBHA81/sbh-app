"use client";

import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

import { useFeature } from "@/lib/stores/auth-store-provider";

/**
 * Hides a whole surface when its super-admin feature flag is off, sending the
 * viewer back to Home. `useFeature` defaults to `true` until /me has loaded, so
 * an enabled feature never flashes the redirect on first paint; the API gates
 * the underlying data regardless.
 */
export function FeatureGate({
  feature,
  children,
}: {
  feature: string;
  children: ReactNode;
}) {
  const enabled = useFeature(feature);
  const router = useRouter();

  useEffect(() => {
    if (!enabled) router.replace("/home");
  }, [enabled, router]);

  if (!enabled) return null;

  return <>{children}</>;
}

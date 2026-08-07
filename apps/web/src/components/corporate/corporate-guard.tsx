"use client";

import { Building2 } from "lucide-react";
import type { ReactNode } from "react";

import { EmptyState } from "@/components/empty-state";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { Button } from "@/components/ui/button";
import { useAuthStore, useFeature } from "@/lib/stores/auth-store-provider";

/**
 * Renders the corporate portal only when the ESD feature is enabled and the
 * active profile is a corporate. Otherwise it explains the portal and offers
 * the account switcher, mirroring how the rest of the app nudges profile-scoped
 * features.
 */
export function CorporateGuard({ children }: { children: ReactNode }) {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const esdOn = useFeature("esd");

  if (!esdOn) {
    return (
      <EmptyState
        icon={Building2}
        title="Not available"
        description="The Enterprise & Supplier Development portal is currently switched off."
      />
    );
  }

  if (activeProfile?.kind !== "corporate") {
    return (
      <EmptyState
        icon={Building2}
        title="Switch to a corporate profile"
        description="The Enterprise & Supplier Development portal is available when you're acting as a corporate sponsor."
      >
        <AccountSwitcher>
          <Button variant="outline">Switch profile</Button>
        </AccountSwitcher>
      </EmptyState>
    );
  }

  return <>{children}</>;
}

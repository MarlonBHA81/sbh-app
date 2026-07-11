"use client";

import Link from "next/link";

import { ProfileAvatar } from "@/components/profile-avatar";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

export function TopBar() {
  const activeProfile = useAuthStore((s) => s.activeProfile);

  return (
    <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b bg-background/90 px-4 backdrop-blur md:hidden">
      <Link href="/home" className="flex items-center gap-2">
        <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-[10px] font-bold text-primary-foreground">
          SBH
        </span>
        <span className="text-lg font-semibold tracking-tight">SBH</span>
      </Link>
      <AccountSwitcher>
        <Button
          variant="ghost"
          size="icon"
          className="size-11 rounded-full"
          aria-label="Switch account"
        >
          <ProfileAvatar profile={activeProfile} className="size-8" />
        </Button>
      </AccountSwitcher>
    </header>
  );
}

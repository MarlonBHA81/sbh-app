"use client";

import { Gauge } from "lucide-react";
import Link from "next/link";

import { ProfileAvatar } from "@/components/profile-avatar";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useSettingsStore } from "@/lib/stores/settings-store";

export function TopBar() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const lowData = useSettingsStore((s) => s.lowData);

  return (
    <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b bg-background/90 px-4 backdrop-blur md:hidden">
      <Link href="/home" className="flex items-center gap-2">
        <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-[10px] font-bold text-primary-foreground">
          SBH
        </span>
        <span className="text-lg font-semibold tracking-tight">SBH</span>
      </Link>
      {lowData ? (
        <Badge variant="secondary" className="gap-1 text-[10px]">
          <Gauge className="size-3" aria-hidden />
          Data saver
        </Badge>
      ) : null}
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

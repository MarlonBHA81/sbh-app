"use client";

import { Gauge } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { SbhLogo } from "@/components/brand/sbh-logo";
import { ProfileAvatar } from "@/components/profile-avatar";
import { SearchTrigger } from "@/components/search/search-trigger";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { RESERVED_SEGMENTS } from "@/lib/public-routes";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useSettingsStore } from "@/lib/stores/settings-store";

/** Screens that render their own reskin header instead of the top bar. */
function hasOwnHeader(pathname: string): boolean {
  const [first, ...rest] = pathname.split("/").filter(Boolean);
  if (!first) return false;
  if (["home", "feeds", "events"].includes(first)) return true;
  // Profile pages (/{handle}) carry their own header too.
  return rest.length === 0 && !RESERVED_SEGMENTS.has(first);
}

export function TopBar() {
  const pathname = usePathname();
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const lowData = useSettingsStore((s) => s.lowData);

  if (hasOwnHeader(pathname)) return null;

  return (
    <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b bg-background/90 px-4 backdrop-blur md:hidden">
      <Link href="/home" aria-label="SBH Community — home">
        <SbhLogo markClassName="size-8" textClassName="text-base" />
      </Link>
      {lowData ? (
        <Badge variant="secondary" className="gap-1 text-[10px]">
          <Gauge className="size-3" aria-hidden />
          Data saver
        </Badge>
      ) : null}
      <div className="flex items-center gap-1">
        <SearchTrigger variant="icon" />
        <AccountSwitcher>
          <Button
            variant="ghost"
            size="icon"
            className="size-11 rounded-full"
            aria-label="Switch account"
          >
            <ProfileAvatar profile={activeProfile} className="size-8" ring />
          </Button>
        </AccountSwitcher>
      </div>
    </header>
  );
}

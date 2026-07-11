"use client";

import { ChevronsUpDown, PenSquare } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";

import { useComposer } from "@/components/composer/composer-provider";
import { ProfileAvatar } from "@/components/profile-avatar";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { NAV_ITEMS } from "@/components/shell/nav-items";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

export function SidebarNav() {
  const pathname = usePathname();
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const { openComposer } = useComposer();
  const profileHref = activeProfile ? `/${activeProfile.handle}` : "/home";

  return (
    <aside className="sticky top-0 hidden h-dvh w-60 shrink-0 flex-col gap-2 border-r p-4 md:flex">
      <Link href="/home" className="mb-4 flex items-center gap-2 px-2">
        <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-[10px] font-bold text-primary-foreground">
          SBH
        </span>
        <span className="text-xl font-semibold tracking-tight">SBH</span>
      </Link>
      <nav aria-label="Primary" className="flex flex-1 flex-col gap-1">
        {NAV_ITEMS.map(({ label, href, icon: Icon }) => {
          const active = pathname === href || pathname.startsWith(`${href}/`);
          return (
            <Link
              key={href}
              href={href}
              aria-current={active ? "page" : undefined}
              className={cn(
                "flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors",
                active
                  ? "bg-accent text-accent-foreground"
                  : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
              )}
            >
              <Icon className="size-5" aria-hidden />
              {label}
            </Link>
          );
        })}
        <Link
          href={profileHref}
          className={cn(
            "flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors",
            activeProfile && pathname === profileHref
              ? "bg-accent text-accent-foreground"
              : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
          )}
        >
          <ProfileAvatar profile={activeProfile} className="size-5" />
          Profile
        </Link>
        <Button
          type="button"
          className="mt-3 h-11 gap-2"
          onClick={() => openComposer()}
        >
          <PenSquare className="size-4" aria-hidden />
          Post
        </Button>
      </nav>
      <AccountSwitcher align="start">
        <Button
          variant="ghost"
          className="h-14 justify-start gap-3 px-2"
          aria-label="Switch account"
        >
          <ProfileAvatar profile={activeProfile} className="size-9" />
          <span className="flex min-w-0 flex-1 flex-col items-start">
            <span className="max-w-full truncate text-sm font-medium">
              {activeProfile?.name ?? "Account"}
            </span>
            <span className="max-w-full truncate text-xs text-muted-foreground">
              {activeProfile ? `@${activeProfile.handle}` : ""}
            </span>
          </span>
          <ChevronsUpDown
            className="size-4 shrink-0 text-muted-foreground"
            aria-hidden
          />
        </Button>
      </AccountSwitcher>
    </aside>
  );
}

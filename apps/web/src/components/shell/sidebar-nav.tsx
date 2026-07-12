"use client";

import {
  Briefcase,
  ChartLine,
  ChevronsUpDown,
  Megaphone,
  PenSquare,
  Trophy,
} from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTranslations } from "next-intl";

import { useComposer } from "@/components/composer/composer-provider";
import { ProfileAvatar } from "@/components/profile-avatar";
import { SearchTrigger } from "@/components/search/search-trigger";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { NAV_ITEMS } from "@/components/shell/nav-items";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";
import { useNotificationsStore } from "@/lib/stores/notifications-store";
import { cn } from "@/lib/utils";

function formatBadge(count: number): string {
  return count > 99 ? "99+" : String(count);
}

export function SidebarNav() {
  const pathname = usePathname();
  const t = useTranslations("nav");
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const unreadCount = useNotificationsStore((s) => s.unreadCount);
  const unreadMessages = useMessagesStore((s) => s.unreadTotal);
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
      <div className="mb-2 px-1">
        <SearchTrigger variant="bar" />
      </div>
      <nav aria-label="Primary" className="flex flex-1 flex-col gap-1">
        {NAV_ITEMS.map(({ labelKey, href, icon: Icon }) => {
          const label = t(labelKey);
          const active = pathname === href || pathname.startsWith(`${href}/`);
          const badgeCount =
            href === "/notifications"
              ? unreadCount
              : href === "/messages"
                ? unreadMessages
                : 0;
          const showBadge = badgeCount > 0;
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
              <span className="relative">
                <Icon className="size-5" aria-hidden />
                {showBadge ? (
                  <span className="absolute -top-1.5 -end-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground tabular-nums">
                    {formatBadge(badgeCount)}
                  </span>
                ) : null}
              </span>
              {label}
              {showBadge ? (
                <span className="sr-only">{t("unreadCount", { count: badgeCount })}</span>
              ) : null}
            </Link>
          );
        })}
        <Link
          href="/business"
          aria-current={
            pathname === "/business" || pathname.startsWith("/business/")
              ? "page"
              : undefined
          }
          className={cn(
            "flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors",
            pathname === "/business" || pathname.startsWith("/business/")
              ? "bg-accent text-accent-foreground"
              : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
          )}
        >
          <Briefcase className="size-5" aria-hidden />
          {t("business")}
        </Link>
        <Link
          href="/leaderboard"
          aria-current={
            pathname === "/leaderboard" ? "page" : undefined
          }
          className={cn(
            "flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors",
            pathname === "/leaderboard"
              ? "bg-accent text-accent-foreground"
              : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
          )}
        >
          <Trophy className="size-5" aria-hidden />
          {t("leaderboard")}
        </Link>
        <Link
          href="/ads"
          aria-current={
            pathname === "/ads" || pathname.startsWith("/ads/")
              ? "page"
              : undefined
          }
          className={cn(
            "flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors",
            pathname === "/ads" || pathname.startsWith("/ads/")
              ? "bg-accent text-accent-foreground"
              : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
          )}
        >
          <Megaphone className="size-5" aria-hidden />
          {t("adCenter")}
        </Link>
        <Link
          href="/insights"
          aria-current={pathname === "/insights" ? "page" : undefined}
          className={cn(
            "flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors",
            pathname === "/insights"
              ? "bg-accent text-accent-foreground"
              : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
          )}
        >
          <ChartLine className="size-5" aria-hidden />
          {t("insights")}
        </Link>
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
          {t("profile")}
        </Link>
        <Button
          type="button"
          className="mt-3 h-11 gap-2"
          onClick={() => openComposer()}
        >
          <PenSquare className="size-4" aria-hidden />
          {t("post")}
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

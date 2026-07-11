"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { ProfileAvatar } from "@/components/profile-avatar";
import { NAV_ITEMS } from "@/components/shell/nav-items";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";
import { useNotificationsStore } from "@/lib/stores/notifications-store";
import { cn } from "@/lib/utils";

function formatBadge(count: number): string {
  return count > 99 ? "99+" : String(count);
}

export function BottomNav() {
  const pathname = usePathname();
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const unreadCount = useNotificationsStore((s) => s.unreadCount);
  const unreadMessages = useMessagesStore((s) => s.unreadTotal);
  const profileHref = activeProfile ? `/${activeProfile.handle}` : "/home";
  const profileActive = activeProfile
    ? pathname === `/${activeProfile.handle}`
    : false;

  return (
    <nav
      aria-label="Primary"
      className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
    >
      <div className="flex h-14">
        {NAV_ITEMS.map(({ label, href, icon: Icon }) => {
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
              aria-label={
                showBadge ? `${label} (${badgeCount} unread)` : label
              }
              aria-current={active ? "page" : undefined}
              className={cn(
                "flex min-h-11 flex-1 flex-col items-center justify-center gap-0.5 text-[10px]",
                active
                  ? "text-foreground"
                  : "text-muted-foreground hover:text-foreground",
              )}
            >
              <span className="relative">
                <Icon
                  className="size-6"
                  strokeWidth={active ? 2.5 : 2}
                  aria-hidden
                />
                {showBadge ? (
                  <span className="absolute -top-1 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground tabular-nums">
                    {formatBadge(badgeCount)}
                  </span>
                ) : null}
              </span>
              <span className="sr-only">{label}</span>
            </Link>
          );
        })}
        <Link
          href={profileHref}
          aria-label="Profile"
          aria-current={profileActive ? "page" : undefined}
          className="flex min-h-11 flex-1 flex-col items-center justify-center"
        >
          <ProfileAvatar
            profile={activeProfile}
            className={cn(
              "size-7",
              profileActive && "ring-2 ring-foreground ring-offset-2 ring-offset-background",
            )}
          />
          <span className="sr-only">Profile</span>
        </Link>
      </div>
    </nav>
  );
}

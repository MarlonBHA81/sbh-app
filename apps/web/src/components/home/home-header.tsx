"use client";

import { Bell, MessageCircle, Search } from "lucide-react";
import Link from "next/link";
import { useTranslations } from "next-intl";

import { ProfileAvatar } from "@/components/profile-avatar";
import { useSearchStore } from "@/lib/stores/search-store";
import { AccountSwitcher } from "@/components/shell/account-switcher";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useMessagesStore } from "@/lib/stores/messages-store";
import { useNotificationsStore } from "@/lib/stores/notifications-store";
import { cn } from "@/lib/utils";

/** 40px circular white icon button used across the reskin headers. */
export function HeaderIconButton({
  href,
  label,
  onClick,
  showDot = false,
  children,
  className,
}: {
  href?: string;
  label: string;
  onClick?: () => void;
  showDot?: boolean;
  children: React.ReactNode;
  className?: string;
}) {
  const inner = (
    <>
      {children}
      {showDot ? (
        <span
          className="absolute end-2.5 top-2.5 size-2 rounded-full bg-teal"
          aria-hidden
        />
      ) : null}
    </>
  );
  const classes = cn(
    "relative flex size-10 shrink-0 items-center justify-center rounded-full border border-warmgray bg-card text-text-primary transition-colors hover:bg-accent active:scale-[0.98]",
    className,
  );
  if (href) {
    return (
      <Link href={href} aria-label={label} className={classes}>
        {inner}
      </Link>
    );
  }
  return (
    <button type="button" aria-label={label} onClick={onClick} className={classes}>
      {inner}
    </button>
  );
}

function contextualGreetingKey(): "morning" | "afternoon" | "evening" {
  const hour = new Date().getHours();
  if (hour < 12) return "morning";
  if (hour < 17) return "afternoon";
  return "evening";
}

/**
 * Home top row: avatar (opens the account switcher, preserving the profile
 * switch flow) + greeting on the left; search, messages and notifications
 * on the right. Messages/search buttons are additions over the reference
 * design so those existing features stay reachable on mobile.
 */
export function HomeHeader() {
  const t = useTranslations("home");
  const tn = useTranslations("nav");
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const unreadNotifications = useNotificationsStore((s) => s.unreadCount);
  const unreadMessages = useMessagesStore((s) => s.unreadTotal);
  const setSearchOpen = useSearchStore((s) => s.setOpen);

  const firstName = (activeProfile?.name ?? "").split(" ")[0] || "there";

  return (
    <div className="flex items-center gap-3">
      <AccountSwitcher align="start">
        <button
          type="button"
          aria-label="Switch account"
          className="flex min-w-0 items-center gap-3 text-start active:scale-[0.98]"
        >
          <ProfileAvatar profile={activeProfile} className="size-11" ring />
          <span className="flex min-w-0 flex-col">
            <span className="truncate font-heading text-[15px] font-medium text-text-primary">
              {t("hey", { name: firstName })}
            </span>
            <span className="flex items-center gap-1.5 truncate text-[13px] text-text-secondary">
              {t(contextualGreetingKey())}
              {activeProfile ? (
                <span
                  className={
                    activeProfile.kind === "business"
                      ? "shrink-0 rounded-full bg-plum/12 px-1.5 py-0.5 text-[10px] font-medium text-plum-tint"
                      : "shrink-0 rounded-full bg-teal/12 px-1.5 py-0.5 text-[10px] font-medium text-teal-text"
                  }
                >
                  {activeProfile.kind === "business" ? "Business" : "Personal"}
                </span>
              ) : null}
            </span>
          </span>
        </button>
      </AccountSwitcher>
      <div className="ms-auto flex items-center gap-2">
        <HeaderIconButton label={tn("messages")} href="/messages" showDot={unreadMessages > 0}>
          <MessageCircle className="size-5" aria-hidden />
        </HeaderIconButton>
        <HeaderIconButton label="Search" onClick={() => setSearchOpen(true)}>
          <Search className="size-5" aria-hidden />
        </HeaderIconButton>
        <HeaderIconButton
          label={tn("notifications")}
          href="/notifications"
          showDot={unreadNotifications > 0}
        >
          <Bell className="size-5" aria-hidden />
        </HeaderIconButton>
      </div>
    </div>
  );
}

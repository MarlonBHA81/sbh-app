"use client";

import { CalendarDays, Home, Newspaper } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTranslations } from "next-intl";

import { ProfileAvatar } from "@/components/profile-avatar";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

/**
 * Floating slate bottom nav (reskin spec): a pill inset 20px from the sides
 * and 16px from the bottom with Home / Feeds / Events / Profile. Active item
 * gets a white 12% pill; inactive items are white at 55% opacity.
 */
export function BottomNav() {
  const pathname = usePathname();
  const t = useTranslations("nav");
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const profileHref = activeProfile ? `/${activeProfile.handle}` : "/home";

  const items = [
    { label: t("home"), href: "/home", icon: Home },
    { label: t("feeds"), href: "/feeds", icon: Newspaper },
    { label: t("events"), href: "/events", icon: CalendarDays },
  ];

  const profileActive = activeProfile
    ? pathname === profileHref || pathname.startsWith(`${profileHref}/`)
    : false;

  return (
    <nav
      aria-label="Primary"
      className="fixed inset-x-5 bottom-4 z-40 mb-[env(safe-area-inset-bottom)] rounded-full bg-slate shadow-lg md:hidden"
    >
      <div className="flex items-stretch justify-between px-3 py-1.5">
        {items.map(({ label, href, icon: Icon }) => {
          const active = pathname === href || pathname.startsWith(`${href}/`);
          return (
            <Link
              key={href}
              href={href}
              aria-label={label}
              aria-current={active ? "page" : undefined}
              className={cn(
                "flex min-h-11 min-w-16 flex-col items-center justify-center gap-0.5 rounded-full px-3 transition-colors active:scale-[0.98]",
                active ? "bg-white/12 text-white" : "text-white/55 hover:text-white",
              )}
            >
              <Icon className="size-5" strokeWidth={active ? 2.5 : 2} aria-hidden />
              <span className="font-heading text-[10px] font-medium">{label}</span>
            </Link>
          );
        })}
        <Link
          href={profileHref}
          aria-label={t("profile")}
          aria-current={profileActive ? "page" : undefined}
          className={cn(
            "flex min-h-11 min-w-16 flex-col items-center justify-center gap-0.5 rounded-full px-3 transition-colors active:scale-[0.98]",
            profileActive ? "bg-white/12 text-white" : "text-white/55 hover:text-white",
          )}
        >
          <ProfileAvatar
            profile={activeProfile}
            className={cn("size-5", !profileActive && "opacity-70")}
          />
          <span className="font-heading text-[10px] font-medium">
            {t("profile")}
          </span>
        </Link>
      </div>
    </nav>
  );
}

"use client";

import { CalendarDays, Home, Newspaper, Plus, type LucideIcon } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTranslations } from "next-intl";

import { useComposer } from "@/components/composer/composer-provider";
import { ProfileAvatar } from "@/components/profile-avatar";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

/**
 * Floating slate bottom nav (reskin): a pill inset from the sides and bottom
 * with Home / Feeds on the left and Events / Profile on the right, split around
 * a centered floating teal compose button that opens the composer. Active items
 * get a white 12% pill; inactive items are white at 55% opacity.
 */
export function BottomNav() {
  const pathname = usePathname();
  const t = useTranslations("nav");
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const { openComposer } = useComposer();
  const profileHref = activeProfile ? `/${activeProfile.handle}` : "/home";

  const left: { label: string; href: string; icon: LucideIcon }[] = [
    { label: t("home"), href: "/home", icon: Home },
    { label: t("feeds"), href: "/feeds", icon: Newspaper },
  ];
  const right: { label: string; href: string; icon: LucideIcon }[] = [
    { label: t("events"), href: "/events", icon: CalendarDays },
  ];

  const profileActive = activeProfile
    ? pathname === profileHref || pathname.startsWith(`${profileHref}/`)
    : false;

  const itemClass = (active: boolean) =>
    cn(
      "flex min-h-11 min-w-14 flex-col items-center justify-center gap-0.5 rounded-full px-2.5 transition-colors active:scale-[0.98]",
      active ? "bg-white/12 text-white" : "text-white/55 hover:text-white",
    );

  const renderItem = ({
    label,
    href,
    icon: Icon,
  }: {
    label: string;
    href: string;
    icon: LucideIcon;
  }) => {
    const active = pathname === href || pathname.startsWith(`${href}/`);
    return (
      <Link
        key={href}
        href={href}
        aria-label={label}
        aria-current={active ? "page" : undefined}
        className={itemClass(active)}
      >
        <Icon className="size-5" strokeWidth={active ? 2.5 : 2} aria-hidden />
        <span className="max-w-full truncate font-heading text-[10px] font-medium whitespace-nowrap">
          {label}
        </span>
      </Link>
    );
  };

  return (
    <nav
      aria-label="Primary"
      className="fixed inset-x-5 bottom-4 z-40 mb-[env(safe-area-inset-bottom)] md:hidden"
    >
      <div className="relative flex items-stretch justify-between rounded-full bg-slate px-3 py-1.5 shadow-lift">
        {left.map(renderItem)}

        {/* Slot the FAB straddles, so the two groups stay balanced. */}
        <span className="w-14 shrink-0" aria-hidden />

        {right.map(renderItem)}
        <Link
          href={profileHref}
          aria-label={t("profile")}
          aria-current={profileActive ? "page" : undefined}
          className={itemClass(profileActive)}
        >
          <ProfileAvatar
            profile={activeProfile}
            className={cn("size-5", !profileActive && "opacity-70")}
          />
          <span className="max-w-full truncate font-heading text-[10px] font-medium whitespace-nowrap">
            {t("profile")}
          </span>
        </Link>

        {/* Centered floating compose button (teal hero action). */}
        <button
          type="button"
          onClick={() => openComposer()}
          aria-label={t("post")}
          className="absolute left-1/2 top-0 grid size-14 -translate-x-1/2 -translate-y-[46%] place-items-center rounded-full border-4 border-background bg-teal text-white shadow-fab transition-transform active:scale-95"
        >
          <Plus className="size-6" strokeWidth={2.4} aria-hidden />
        </button>
      </div>
    </nav>
  );
}

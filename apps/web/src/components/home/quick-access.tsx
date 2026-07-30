"use client";

import {
  BookOpen,
  Bot,
  GraduationCap,
  HeartHandshake,
  HelpCircle,
  Megaphone,
  MessagesSquare,
  School,
  ShoppingBag,
  Sparkles,
  Store,
  Target,
  UsersRound,
  Wrench,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { useTranslations } from "next-intl";

import { SwirlWatermark } from "@/components/brand/swirl-watermark";
import { SectionHeader } from "@/components/home/section-header";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

interface Tile {
  labelKey:
    | "dashboard"
    | "opportunities"
    | "wellness"
    | "resources"
    | "learn"
    | "coach"
    | "masterclasses"
    | "shop"
    | "mentors"
    | "questions"
    | "forums"
    | "directory"
    | "tools"
    | "promotedPosts";
  href: string;
  icon: LucideIcon;
  /** Brand gradient (design tokens via Tailwind theme). */
  gradient: string;
  /** Super-admin feature flag that must be on for this tile to show. */
  feature?: string;
}

const TILES: Tile[] = [
  {
    labelKey: "dashboard",
    href: "/dashboard",
    icon: Target,
    gradient: "from-teal to-plum",
  },
  {
    labelKey: "opportunities",
    href: "/opportunities",
    icon: Sparkles,
    gradient: "from-teal to-sage",
    feature: "opportunities",
  },
  {
    labelKey: "wellness",
    href: "/wellness",
    icon: HeartHandshake,
    gradient: "from-sage to-plum",
    feature: "wellness",
  },
  {
    labelKey: "resources",
    href: "/resources",
    icon: BookOpen,
    gradient: "from-plum to-sage",
  },
  {
    labelKey: "learn",
    href: "/learn",
    icon: GraduationCap,
    gradient: "from-sage to-teal",
  },
  {
    labelKey: "coach",
    href: "/coach",
    icon: Bot,
    gradient: "from-teal to-plum",
    feature: "coach",
  },
  {
    labelKey: "masterclasses",
    href: "/masterclasses",
    icon: School,
    gradient: "from-plum to-teal",
    feature: "masterclasses",
  },
  {
    labelKey: "shop",
    href: "/shop",
    icon: ShoppingBag,
    gradient: "from-teal to-sage",
    feature: "shop",
  },
  {
    labelKey: "mentors",
    href: "/mentors",
    icon: UsersRound,
    gradient: "from-plum to-slate",
  },
  {
    labelKey: "questions",
    href: "/questions",
    icon: HelpCircle,
    gradient: "from-sage to-plum",
  },
  {
    labelKey: "forums",
    href: "/discover",
    icon: MessagesSquare,
    gradient: "from-teal to-teal-tint",
  },
  {
    labelKey: "directory",
    href: "/business",
    icon: Store,
    gradient: "from-plum to-plum-tint",
  },
  {
    labelKey: "tools",
    href: "/insights",
    icon: Wrench,
    gradient: "from-sage to-sage-tint",
  },
  {
    labelKey: "promotedPosts",
    href: "/ads",
    icon: Megaphone,
    gradient: "from-slate to-slate-tint",
  },
];

/** Horizontally scrollable brand-gradient tiles (Home, reskin spec). */
export function QuickAccess() {
  const t = useTranslations("home");
  const features = useAuthStore((s) => s.features);
  const tiles = TILES.filter(
    (tile) => !tile.feature || (features[tile.feature] ?? true),
  );

  return (
    <section className="flex flex-col gap-3">
      <SectionHeader
        title={t("quickAccess")}
        viewAllHref="/discover"
        viewAllLabel={t("viewAll")}
      />
      <div className="-mx-5 flex gap-3 overflow-x-auto px-5 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {tiles.map(({ labelKey, href, icon: Icon, gradient }) => (
          <Link
            key={labelKey}
            href={href}
            className={`relative flex size-[150px] shrink-0 flex-col justify-between overflow-hidden rounded-(--radius-tile) bg-gradient-to-br p-3.5 transition-transform active:scale-[0.98] ${gradient}`}
          >
            <SwirlWatermark className="-end-8 -bottom-8 size-40 opacity-10" />
            <span className="flex size-9 items-center justify-center rounded-full bg-slate/30 text-white">
              <Icon className="size-[18px]" aria-hidden />
            </span>
            <span className="font-heading text-sm leading-snug font-medium text-white">
              {t(labelKey)}
            </span>
          </Link>
        ))}
      </div>
    </section>
  );
}

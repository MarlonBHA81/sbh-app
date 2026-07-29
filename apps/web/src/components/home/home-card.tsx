import type { LucideIcon } from "lucide-react";

import { cn } from "@/lib/utils";

/**
 * Shared shell for the "Today" dashboard cards.
 *
 * All six cards used to ship the identical surface — same radius, border,
 * padding and shadow, same size-8 icon chip — so the journey-stage ordering
 * in home-view had no visual consequence: the lead card looked exactly like
 * the ones that self-hide. The tiers restore that hierarchy.
 *
 *   lead    — the stage-led card. Filled tonal surface, no border chrome,
 *             bigger type and chip. One per screen.
 *   default — the standard bordered card. The old shared shell.
 *   quiet   — supporting cards. No border, no shadow, no fill; reads as a
 *             list row so it can't compete with the lead.
 */
export type HomeCardTier = "lead" | "default" | "quiet";
export type HomeCardTone = "teal" | "sage" | "plum";

const SURFACE: Record<HomeCardTier, string> = {
  lead: "gap-3.5 rounded-(--radius-tile) p-5",
  default:
    "gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card",
  quiet: "gap-2.5 rounded-(--radius-card) px-1 py-1",
};

/** Lead tier takes a tonal wash of its own accent, not a fixed teal. */
const LEAD_SURFACE: Record<HomeCardTone, string> = {
  teal: "bg-teal/10",
  sage: "bg-sage/12",
  plum: "bg-plum/10",
};

const CHIP: Record<HomeCardTone, string> = {
  teal: "bg-teal/12 text-teal-text",
  sage: "bg-sage/15 text-sage-text",
  plum: "bg-plum/12 text-plum-tint",
};

const CHIP_SIZE: Record<HomeCardTier, string> = {
  lead: "size-10",
  default: "size-8",
  quiet: "size-7",
};

const ICON_SIZE: Record<HomeCardTier, string> = {
  lead: "size-5",
  default: "size-4",
  quiet: "size-3.5",
};

const TITLE: Record<HomeCardTier, string> = {
  lead: "text-[17px] font-semibold",
  default: "text-[15px] font-semibold",
  quiet: "text-[13px] font-semibold",
};

export function HomeCard({
  tier = "default",
  tone = "teal",
  icon: Icon,
  title,
  aside,
  children,
  className,
}: {
  tier?: HomeCardTier;
  tone?: HomeCardTone;
  icon: LucideIcon;
  title: string;
  /** Right-aligned slot in the header row — streak pills, "View all", counts. */
  aside?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <section
      className={cn(
        "flex flex-col",
        SURFACE[tier],
        tier === "lead" && LEAD_SURFACE[tone],
        className,
      )}
    >
      <div className="flex items-center gap-2">
        <span
          className={cn(
            "flex shrink-0 items-center justify-center rounded-full",
            CHIP_SIZE[tier],
            CHIP[tone],
          )}
        >
          <Icon className={ICON_SIZE[tier]} aria-hidden />
        </span>
        <h2
          className={cn("min-w-0 font-heading text-text-primary", TITLE[tier])}
        >
          {title}
        </h2>
        {aside ? <div className="ms-auto shrink-0">{aside}</div> : null}
      </div>
      {children}
    </section>
  );
}

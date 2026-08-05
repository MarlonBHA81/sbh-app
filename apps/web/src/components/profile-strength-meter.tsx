"use client";

import { ArrowRight } from "lucide-react";
import Link from "next/link";

import { Progress } from "@/components/ui/progress";
import type { Profile } from "@/lib/api/types";
import { computeProfileStrength } from "@/lib/profile-strength";

/**
 * Slim goal-gradient strength meter shown under the level badge on the
 * viewer's own profile (UX pattern 2). Never reads below 20% and surfaces the
 * single next action as a one-tap chip ("Add your logo · +20%"). Renders
 * nothing once the profile is fully built.
 */
export function ProfileStrengthMeter({ profile }: { profile: Profile }) {
  const { pct, next, complete } = computeProfileStrength(profile);
  if (complete) return null;

  return (
    <div className="flex w-full max-w-xs flex-col gap-1.5">
      <div className="flex items-center justify-between text-[11px] text-text-secondary">
        <span>Profile strength</span>
        <span className="tabular-nums">{pct}%</span>
      </div>
      <Progress value={pct} className="h-1.5" />
      {next ? (
        <Link
          href={next.href}
          className="mt-0.5 flex items-center gap-1.5 self-start rounded-full bg-teal/12 px-2.5 py-1 text-[12px] font-medium text-teal-text transition-colors hover:bg-teal/20 active:scale-[0.98]"
        >
          {next.action} · +{next.points}%
          <ArrowRight className="size-3" aria-hidden />
        </Link>
      ) : null}
    </div>
  );
}

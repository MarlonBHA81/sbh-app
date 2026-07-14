"use client";

import { Flame } from "lucide-react";
import { useEffect, useState } from "react";

import { fetchStreak, type Streak } from "@/lib/api/streak";

/**
 * Owned/at-risk streak chip (UX pattern 5 — honest loss aversion). Frames the
 * streak as the user's own ("Your 6-day streak") and only warns of loss when
 * the backend confirms it truly ends today ("Your 6-day streak ends tonight").
 * No invented scarcity: renders nothing until a real streak signal exists.
 */
export function StreakChip() {
  const [streak, setStreak] = useState<Streak | null>(null);

  useEffect(() => {
    let cancelled = false;
    void fetchStreak().then((s) => {
      if (!cancelled) setStreak(s);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  // BACKEND-TODO: fetchStreak() returns null today (no streak feature), so this
  // renders nothing. It lights up automatically once the endpoint is live.
  if (!streak || streak.current_days < 1) return null;

  const label = streak.ends_today
    ? `Your ${streak.current_days}-day streak ends tonight`
    : `Your ${streak.current_days}-day streak`;

  return (
    <span className="flex w-fit items-center gap-1.5 rounded-full bg-teal/12 px-3 py-1 text-[13px] font-medium text-teal-text">
      <Flame className="size-4" aria-hidden />
      {label}
    </span>
  );
}

"use client";

import { useTranslations } from "next-intl";

const PROMPT_KEYS = ["prompt1", "prompt2", "prompt3"] as const;

/** Day of year — deterministic within a day, so SSR and client agree. */
function dayOfYear(): number {
  const now = new Date();
  const start = new Date(now.getFullYear(), 0, 0);
  return Math.floor((now.getTime() - start.getTime()) / 86_400_000);
}

/** Rotating two-line hero heading (28px Poppins SemiBold, reskin spec). */
export function HeroPrompt() {
  const t = useTranslations("home");
  const key = PROMPT_KEYS[dayOfYear() % PROMPT_KEYS.length];

  return (
    <h1 className="max-w-[19rem] font-heading text-[28px] leading-[1.2] font-semibold text-text-primary">
      {t(key)}
    </h1>
  );
}

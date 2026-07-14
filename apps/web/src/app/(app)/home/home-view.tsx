"use client";

import { useTranslations } from "next-intl";

import { StreakChip } from "@/components/gamification/streak-chip";
import { ComposerBar } from "@/components/home/composer-bar";
import { HeroPrompt } from "@/components/home/hero-prompt";
import { HomeHeader } from "@/components/home/home-header";
import { QuickAccess } from "@/components/home/quick-access";
import { SectionHeader } from "@/components/home/section-header";
import { HomeFeed } from "@/components/posts/home-feed";

/** Home screen per the SBH Community reskin spec. */
export function HomeView() {
  const t = useTranslations("home");

  return (
    <div className="flex flex-col gap-5">
      <HomeHeader />
      <StreakChip />
      <HeroPrompt />
      <ComposerBar />
      <QuickAccess />
      <section className="flex flex-col gap-3">
        <SectionHeader title={t("recentActivity")} />
        <HomeFeed />
      </section>
    </div>
  );
}

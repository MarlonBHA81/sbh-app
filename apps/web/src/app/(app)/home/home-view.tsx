"use client";

import { useTranslations } from "next-intl";
import { useEffect } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { StreakChip } from "@/components/gamification/streak-chip";
import { BusinessProfilePrompt } from "@/components/home/business-profile-prompt";
import { ComposerBar } from "@/components/home/composer-bar";
import { ConnectionsCard } from "@/components/home/connections-card";
import { DailyBriefCard } from "@/components/home/daily-brief-card";
import { DailyChallengeCard } from "@/components/home/daily-challenge-card";
import { HeroPrompt } from "@/components/home/hero-prompt";
import type { HomeCardTier } from "@/components/home/home-card";
import { HomeHeader } from "@/components/home/home-header";
import { OnboardingChecklist } from "@/components/home/onboarding-checklist";
import { RecentWinsCard } from "@/components/home/recent-wins-card";
import { QuickAccess } from "@/components/home/quick-access";
import { SectionHeader } from "@/components/home/section-header";
import { TodaysLessonCard } from "@/components/home/todays-lesson-card";
import { TodaysOpportunitiesCard } from "@/components/home/todays-opportunities-card";
import { HomeFeed } from "@/components/posts/home-feed";
import { hasComposeDraft } from "@/lib/compose-draft";
import { useAuthStore, useFeature } from "@/lib/stores/auth-store-provider";

type Card = "challenge" | "opportunities" | "connections";

/**
 * Order the "Today" dashboard cards by the member's journey stage (V1.1):
 * finding-customers/expanding lead with people; growing/exporting lead with
 * opportunities; everyone else leads with the daily habit. Each card hides
 * itself when empty, so ordering is the only concern here.
 */
function cardOrder(stage: string | null | undefined): Card[] {
  switch (stage) {
    case "finding_customers":
    case "expanding":
      return ["connections", "opportunities", "challenge"];
    case "growing_sales":
    case "exporting":
      return ["opportunities", "connections", "challenge"];
    default:
      return ["challenge", "opportunities", "connections"];
  }
}

const CARD: Record<Card, React.ComponentType<{ tier?: HomeCardTier }>> = {
  challenge: DailyChallengeCard,
  opportunities: TodaysOpportunitiesCard,
  connections: ConnectionsCard,
};

/** Home screen — the daily dashboard that answers "why open today?". */
export function HomeView() {
  const t = useTranslations("home");
  const { openComposer } = useComposer();
  const stage = useAuthStore((s) => s.activeProfile?.journey_stage ?? null);
  const briefOn = useFeature("daily_brief");
  const coursesOn = useFeature("courses");
  const opportunitiesOn = useFeature("opportunities");

  // Carry a compose draft through signup (UX pattern 4): arriving with
  // ?compose=draft and a saved draft reopens the composer with the text intact.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("compose") === "draft" && hasComposeDraft()) {
      openComposer();
      window.history.replaceState(null, "", "/home");
    }
  }, [openComposer]);

  return (
    <div className="flex flex-col gap-5">
      <HomeHeader />
      <StreakChip />
      <HeroPrompt />
      <ComposerBar />

      {/* Today's dashboard — ordered to the member's journey stage. The
          stage-led card takes the lead tier so the ordering is legible at a
          glance; the rest sit back. */}
      {cardOrder(stage)
        .filter((key) => key !== "opportunities" || opportunitiesOn)
        .map((key, i) => {
          const Component = CARD[key];
          return <Component key={key} tier={i === 0 ? "lead" : "default"} />;
        })}

      {/* Daily Business Brief (V2 · Feature 7) — super-admin togglable;
          self-hides when there are no items. */}
      {briefOn ? <DailyBriefCard /> : null}

      {/* Supporting cards drop to the quiet tier and group under a hairline,
          so the daily dashboard reads as two bands rather than one long run
          of identically-weighted cards. */}
      <section className="flex flex-col gap-2 border-t border-warmgray pt-4">
        {/* Today's lesson (V2 · LEARN) — self-hides when there are no lessons. */}
        {coursesOn ? <TodaysLessonCard tier="quiet" /> : null}

        {/* Recent wins (V2 · BELONG) — self-hides when there are none. */}
        <RecentWinsCard tier="quiet" />
      </section>

      <OnboardingChecklist />
      <BusinessProfilePrompt />
      <QuickAccess />
      <section className="flex flex-col gap-3">
        <SectionHeader title={t("recentActivity")} />
        <HomeFeed />
      </section>
    </div>
  );
}

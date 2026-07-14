"use client";

import { useTranslations } from "next-intl";
import { useEffect } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { ComposerBar } from "@/components/home/composer-bar";
import { HeroPrompt } from "@/components/home/hero-prompt";
import { HomeHeader } from "@/components/home/home-header";
import { OnboardingChecklist } from "@/components/home/onboarding-checklist";
import { QuickAccess } from "@/components/home/quick-access";
import { SectionHeader } from "@/components/home/section-header";
import { HomeFeed } from "@/components/posts/home-feed";
import { hasComposeDraft } from "@/lib/compose-draft";

/** Home screen per the SBH Community reskin spec. */
export function HomeView() {
  const t = useTranslations("home");
  const { openComposer } = useComposer();

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
      <HeroPrompt />
      <ComposerBar />
      <OnboardingChecklist />
      <QuickAccess />
      <section className="flex flex-col gap-3">
        <SectionHeader title={t("recentActivity")} />
        <HomeFeed />
      </section>
    </div>
  );
}

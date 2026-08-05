"use client";

import { useTranslations } from "next-intl";

import { HomeFeed } from "@/components/posts/home-feed";
import { ScreenHeader } from "@/components/shell/screen-header";

/** Feeds screen (reskin spec): header + activity label + the feed tabs. */
export function FeedsView() {
  const t = useTranslations("home");
  const tn = useTranslations("nav");

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title={tn("feeds")} />
      <p className="font-heading text-[15px] font-medium text-text-primary">
        {t("recentActivity")}
      </p>
      <HomeFeed />
    </div>
  );
}

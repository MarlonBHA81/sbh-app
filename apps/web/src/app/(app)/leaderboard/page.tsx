import type { Metadata } from "next";

import { ChallengesSection } from "@/components/gamification/challenges-section";
import { FeatureGate } from "@/components/shell/feature-gate";

import { LeaderboardView } from "./leaderboard-view";

export const metadata: Metadata = { title: "Leaderboard" };

export default function LeaderboardPage() {
  return (
    <FeatureGate feature="gamification">
      <div className="flex flex-col gap-4">
        <h1 className="text-xl font-semibold tracking-tight">Leaderboard</h1>
        <ChallengesSection />
        <LeaderboardView />
      </div>
    </FeatureGate>
  );
}

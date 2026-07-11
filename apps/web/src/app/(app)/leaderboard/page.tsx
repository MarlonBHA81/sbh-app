import type { Metadata } from "next";

import { LeaderboardView } from "./leaderboard-view";

export const metadata: Metadata = { title: "Leaderboard" };

export default function LeaderboardPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Leaderboard</h1>
      <LeaderboardView />
    </div>
  );
}

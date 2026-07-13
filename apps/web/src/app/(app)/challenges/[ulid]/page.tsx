import type { Metadata } from "next";

import { ChallengeView } from "./challenge-view";

export const metadata: Metadata = { title: "Challenge" };

export default async function ChallengePage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <ChallengeView ulid={ulid} />;
}

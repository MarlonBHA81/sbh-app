import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { CoachView } from "@/components/coach/coach-view";

export const metadata: Metadata = { title: "AI Coach" };

export default function CoachPage() {
  return (
    <FeatureGate feature="coach">
      <CoachView />
    </FeatureGate>
  );
}

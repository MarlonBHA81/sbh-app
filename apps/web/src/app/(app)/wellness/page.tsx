import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { WellnessView } from "@/components/wellness/wellness-view";

export const metadata: Metadata = { title: "A moment for you" };

export default function WellnessPage() {
  return (
    <FeatureGate feature="wellness">
      <WellnessView />
    </FeatureGate>
  );
}

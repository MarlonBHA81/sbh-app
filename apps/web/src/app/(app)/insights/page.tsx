import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { InsightsView } from "@/components/insights/insights-view";

export const metadata: Metadata = { title: "Insights" };

export default function InsightsPage() {
  return (
    <FeatureGate feature="business_tools">
      <InsightsView />
    </FeatureGate>
  );
}

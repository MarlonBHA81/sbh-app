import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { OpportunitiesView } from "@/components/opportunities/opportunities-view";

export const metadata: Metadata = { title: "Opportunities" };

export default function OpportunitiesPage() {
  return (
    <FeatureGate feature="opportunities">
      <OpportunitiesView />
    </FeatureGate>
  );
}

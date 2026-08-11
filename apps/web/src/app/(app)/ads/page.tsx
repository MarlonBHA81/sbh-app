import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { AdsView } from "@/components/ads/ads-view";

export const metadata: Metadata = { title: "Ad Center" };

export default function AdsPage() {
  return (
    <FeatureGate feature="ads">
      <AdsView />
    </FeatureGate>
  );
}

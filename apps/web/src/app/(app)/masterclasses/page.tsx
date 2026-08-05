import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { MasterclassesView } from "@/components/masterclasses/masterclasses-view";

export const metadata: Metadata = { title: "Masterclasses" };

export default function MasterclassesPage() {
  return (
    <FeatureGate feature="masterclasses">
      <MasterclassesView />
    </FeatureGate>
  );
}

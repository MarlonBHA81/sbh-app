import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { QuestionsView } from "@/components/questions/questions-view";

export const metadata: Metadata = { title: "Questions" };

export default function QuestionsPage() {
  return (
    <FeatureGate feature="community">
      <QuestionsView />
    </FeatureGate>
  );
}

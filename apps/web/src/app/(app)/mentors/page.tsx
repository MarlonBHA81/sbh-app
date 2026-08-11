import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { MentorsView } from "@/components/mentors/mentors-view";

export const metadata: Metadata = { title: "Mentors" };

export default function MentorsPage() {
  return (
    <FeatureGate feature="community">
      <MentorsView />
    </FeatureGate>
  );
}

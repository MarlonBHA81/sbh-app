import type { Metadata } from "next";

import { WellnessView } from "@/components/wellness/wellness-view";

export const metadata: Metadata = { title: "A moment for you" };

export default function WellnessPage() {
  return <WellnessView />;
}

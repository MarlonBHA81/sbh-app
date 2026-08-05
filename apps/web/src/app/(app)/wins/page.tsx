import type { Metadata } from "next";

import { WinsView } from "@/components/wins/wins-view";

export const metadata: Metadata = { title: "Wins" };

export default function WinsPage() {
  return <WinsView />;
}

import type { Metadata } from "next";

import { AdsView } from "@/components/ads/ads-view";

export const metadata: Metadata = { title: "Ad Center" };

export default function AdsPage() {
  return <AdsView />;
}

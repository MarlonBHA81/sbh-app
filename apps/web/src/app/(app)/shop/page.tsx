import type { Metadata } from "next";

import { FeatureGate } from "@/components/shell/feature-gate";
import { ShopView } from "@/components/shop/shop-view";

export const metadata: Metadata = { title: "Shop" };

export default function ShopPage() {
  return (
    <FeatureGate feature="shop">
      <ShopView />
    </FeatureGate>
  );
}

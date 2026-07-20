import type { Metadata } from "next";

import { PurchasesView } from "@/components/shop/purchases-view";

export const metadata: Metadata = { title: "My purchases" };

export default function PurchasesPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="font-heading text-xl font-semibold text-text-primary">
        My purchases
      </h1>
      <PurchasesView />
    </div>
  );
}

import type { Metadata } from "next";

import { CorporateDashboard } from "@/components/corporate/corporate-dashboard";
import { CorporateGuard } from "@/components/corporate/corporate-guard";

export const metadata: Metadata = { title: "ESD portal" };

export default function CorporatePage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Enterprise &amp; Supplier Development</h1>
      <CorporateGuard>
        <CorporateDashboard />
      </CorporateGuard>
    </div>
  );
}

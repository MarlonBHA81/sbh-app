import type { Metadata } from "next";

import { CohortDetailView } from "@/components/corporate/cohort-detail";
import { CorporateGuard } from "@/components/corporate/corporate-guard";

export const metadata: Metadata = { title: "Cohort · ESD" };

export default async function CohortPage({
  params,
}: {
  params: Promise<{ cohort: string }>;
}) {
  const { cohort } = await params;

  return (
    <CorporateGuard>
      <CohortDetailView ulid={cohort} />
    </CorporateGuard>
  );
}

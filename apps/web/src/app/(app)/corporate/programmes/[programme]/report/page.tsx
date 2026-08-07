import type { Metadata } from "next";

import { CorporateGuard } from "@/components/corporate/corporate-guard";
import { ReportView } from "@/components/corporate/report-view";

export const metadata: Metadata = { title: "Report · ESD" };

export default async function ProgrammeReportPage({
  params,
}: {
  params: Promise<{ programme: string }>;
}) {
  const { programme } = await params;

  return (
    <CorporateGuard>
      <ReportView ulid={programme} />
    </CorporateGuard>
  );
}

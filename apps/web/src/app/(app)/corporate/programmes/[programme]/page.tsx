import type { Metadata } from "next";

import { CorporateGuard } from "@/components/corporate/corporate-guard";
import { ProgrammeDetailView } from "@/components/corporate/programme-detail";

export const metadata: Metadata = { title: "Programme · ESD" };

export default async function ProgrammePage({
  params,
}: {
  params: Promise<{ programme: string }>;
}) {
  const { programme } = await params;

  return (
    <CorporateGuard>
      <ProgrammeDetailView ulid={programme} />
    </CorporateGuard>
  );
}

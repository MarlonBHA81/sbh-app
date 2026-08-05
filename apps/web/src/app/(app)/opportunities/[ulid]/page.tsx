import type { Metadata } from "next";

import { OpportunityDetail } from "@/components/opportunities/opportunity-detail";

export const metadata: Metadata = { title: "Opportunity" };

export default async function OpportunityPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <OpportunityDetail ulid={ulid} />;
}

"use client";

import { use } from "react";

import { CampaignDetailView } from "@/components/ads/campaign-detail-view";

export default function CampaignDetailPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = use(params);
  return <CampaignDetailView ulid={ulid} />;
}

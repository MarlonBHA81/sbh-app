"use client";

import { SponsorCard, useSponsorSlot } from "@/components/ads/sponsor-card";

/**
 * Desktop right-rail sponsor (Milestone 11). Renders nothing when there is no
 * slot to show (204 or error), so the rail simply stays empty.
 */
export function RightRail() {
  const { slot } = useSponsorSlot("right_rail");
  if (!slot) return null;
  return (
    <div className="sticky top-6">
      <SponsorCard slot={slot} variant="rail" />
    </div>
  );
}

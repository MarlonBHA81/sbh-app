"use client";

import { AdSpotPlaceholder } from "@/components/ads/ad-spot-placeholder";
import { SponsorCard, useSponsorSlot } from "@/components/ads/sponsor-card";

/**
 * Desktop right-rail ad unit (Milestone 11). Shows the sponsor when a
 * campaign fills the slot, a visible "ad spot" placeholder when the
 * inventory is unsold, and nothing while loading.
 */
export function RightRail() {
  const { slot, status } = useSponsorSlot("right_rail");
  if (status === "loading" || status === "dismissed") return null;
  return (
    <div className="sticky top-6">
      {slot ? (
        <SponsorCard slot={slot} variant="rail" />
      ) : (
        <AdSpotPlaceholder variant="rail" />
      )}
    </div>
  );
}

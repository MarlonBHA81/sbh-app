import * as api from "@/lib/api/client";

/**
 * Ad tracking (Milestone 11). Fire-and-forget POSTs to /ads/track for promoted
 * feed posts and sponsor slots. Impressions are deduped once per session per
 * subject (campaign or slot); clicks always fire.
 */

type TrackKind = "impression" | "click" | "link_click";

interface TrackTarget {
  campaign_ulid?: string;
  slot_key?: string;
  opportunity_ulid?: string;
  room_ulid?: string;
}

/** Subjects whose impression has already been counted this session. */
const firedImpressions = new Set<string>();

function send(kind: TrackKind, target: TrackTarget): void {
  api.post("/api/v1/ads/track", { kind, ...target }).catch(() => {
    // Fire-and-forget: tracking must never surface an error to the user.
  });
}

/** Track a campaign impression at most once per session. */
export function trackCampaignImpression(campaignUlid: string): void {
  const key = `campaign:${campaignUlid}`;
  if (firedImpressions.has(key)) return;
  firedImpressions.add(key);
  send("impression", { campaign_ulid: campaignUlid });
}

/** Track a post-open click on a promoted post's campaign (not deduped). */
export function trackCampaignClick(campaignUlid: string): void {
  send("click", { campaign_ulid: campaignUlid });
}

/** Track an outbound link click on a promoted post (not deduped). */
export function trackCampaignLinkClick(campaignUlid: string): void {
  send("link_click", { campaign_ulid: campaignUlid });
}

/** Track a sponsor-slot impression at most once per session. */
export function trackSlotImpression(slotKey: string): void {
  const key = `slot:${slotKey}`;
  if (firedImpressions.has(key)) return;
  firedImpressions.add(key);
  send("impression", { slot_key: slotKey });
}

/** Track a click on a sponsor slot's Visit action (not deduped). */
export function trackSlotClick(slotKey: string): void {
  send("click", { slot_key: slotKey });
}

/** Track a sponsored-opportunity impression at most once per session (V3). */
export function trackOpportunityImpression(opportunityUlid: string): void {
  const key = `opportunity:${opportunityUlid}`;
  if (firedImpressions.has(key)) return;
  firedImpressions.add(key);
  send("impression", { opportunity_ulid: opportunityUlid });
}

/** Track a click on a sponsored opportunity (not deduped) — V3. */
export function trackOpportunityClick(opportunityUlid: string): void {
  send("click", { opportunity_ulid: opportunityUlid });
}

/** Track a sponsored-room impression at most once per session (Shop P4). */
export function trackSponsoredRoomImpression(roomUlid: string): void {
  const key = `room:${roomUlid}`;
  if (firedImpressions.has(key)) return;
  firedImpressions.add(key);
  send("impression", { room_ulid: roomUlid });
}

/** Track a click on a sponsored room's sponsor link (not deduped) — Shop P4. */
export function trackSponsoredRoomClick(roomUlid: string): void {
  send("click", { room_ulid: roomUlid });
}

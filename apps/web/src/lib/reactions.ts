import type { Vote } from "@/lib/api/types";

/** Compact number for action-bar counts (e.g. 1.2K). Empty string for 0. */
export function formatCount(n: number): string {
  if (n <= 0) return "";
  return Intl.NumberFormat("en", { notation: "compact" }).format(n);
}

/** Compact signed net vote (e.g. "12", "-3", "" for 0). */
export function formatNetVotes(net: number): string {
  if (net === 0) return "0";
  const abs = Intl.NumberFormat("en", { notation: "compact" }).format(
    Math.abs(net),
  );
  return net < 0 ? `-${abs}` : abs;
}

export interface VoteCounts {
  upvotes_count: number;
  downvotes_count: number;
}

/**
 * Apply a vote transition to up/down counts. Tapping the active arrow again
 * clears the vote (toggle to 0); switching sides moves the count across.
 * Returns the next vote and the adjusted counts.
 */
export function applyVote(
  current: Vote,
  clicked: Exclude<Vote, 0>,
  counts: VoteCounts,
): { vote: Vote; counts: VoteCounts } {
  const next: Vote = current === clicked ? 0 : clicked;
  let up = counts.upvotes_count;
  let down = counts.downvotes_count;

  // Remove the previous vote's contribution.
  if (current === 1) up -= 1;
  else if (current === -1) down -= 1;
  // Add the new vote's contribution.
  if (next === 1) up += 1;
  else if (next === -1) down += 1;

  return {
    vote: next,
    counts: {
      upvotes_count: Math.max(0, up),
      downvotes_count: Math.max(0, down),
    },
  };
}

import * as api from "@/lib/api/client";

/**
 * Honest loss aversion (UX pattern 5). A streak is framed as something the user
 * *owns* and could lose — but only ever with true stakes. We never invent a
 * countdown, so the "ends tonight" copy shows only when the backend says the
 * streak genuinely lapses at end of day.
 */
export interface Streak {
  /** Consecutive active days, owned by the user. */
  current_days: number;
  /** True only when the streak really lapses at the end of today. */
  ends_today: boolean;
}

/**
 * The viewer's engagement streak, or null when there's nothing to show.
 *
 * Backed by `GET /api/v1/me/streak` (V1 · Daily Challenge + streak engine):
 * the daily action advances a real streak on the profile. Returns null when
 * the streak is zero/lapsed, so the StreakChip only appears with a live,
 * honest count and its "ends tonight" at-risk flag.
 */
export async function fetchStreak(): Promise<Streak | null> {
  try {
    const res = await api.get<{ data: Streak | null }>("/api/v1/me/streak");
    return res.data;
  } catch {
    return null;
  }
}

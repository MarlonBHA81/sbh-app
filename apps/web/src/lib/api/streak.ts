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
 * BACKEND-TODO: no streak feature exists yet — there is no streak model,
 * column, or `GET /api/v1/me/streak` endpoint. This resolves to null so the
 * StreakChip renders nothing until a real, honest streak signal exists. Wire
 * the endpoint here and the chip lights up with owned/at-risk copy.
 */
export async function fetchStreak(): Promise<Streak | null> {
  try {
    const res = await api.get<{ data: Streak | null }>("/api/v1/me/streak");
    return res.data;
  } catch {
    return null;
  }
}

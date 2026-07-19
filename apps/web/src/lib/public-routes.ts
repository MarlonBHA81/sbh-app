/** Top-level route segments that are NOT profile handles. */
export const RESERVED_SEGMENTS = new Set([
  "home", "feeds", "events", "discover", "business", "messages",
  "notifications", "leaderboard", "insights", "map", "search",
  "settings", "topics", "drafts", "ads", "p", "explore", "opportunities",
  "resources", "learn", "coach", "mentors", "questions",
]);

/**
 * Routes a logged-out visitor may view read-only:
 * the public Explore feed (/explore), post permalinks (/p/{ulid}) and profile
 * pages (/{handle}). Reciprocity (UX pattern 3): guests get real content
 * before any signup ask.
 */
export function isPublicRoute(pathname: string): boolean {
  const segments = pathname.split("/").filter(Boolean);
  if (segments.length === 0) return false;
  if (segments[0] === "explore") return segments.length === 1;
  if (segments[0] === "p") return segments.length === 2;
  return segments.length === 1 && !RESERVED_SEGMENTS.has(segments[0]);
}

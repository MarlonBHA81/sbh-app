/** Top-level route segments that are NOT profile handles. */
export const RESERVED_SEGMENTS = new Set([
  "home", "feeds", "events", "discover", "business", "messages",
  "notifications", "leaderboard", "insights", "map", "search",
  "settings", "topics", "drafts", "ads", "p",
]);

/**
 * Routes a logged-out visitor may view read-only (SEO-crawlable):
 * post permalinks (/p/{ulid}) and profile pages (/{handle}).
 */
export function isPublicRoute(pathname: string): boolean {
  const segments = pathname.split("/").filter(Boolean);
  if (segments.length === 0) return false;
  if (segments[0] === "p") return segments.length === 2;
  return segments.length === 1 && !RESERVED_SEGMENTS.has(segments[0]);
}

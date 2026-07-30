/**
 * Helpers for handling user- and vendor-supplied URLs safely.
 *
 * Arbitrary URLs (post links, profile websites, job apply links, sponsor URLs,
 * course/tool external links, etc.) must never be placed into an `href`
 * unvalidated: a stored `javascript:` or `data:` URL would execute in the app's
 * origin on click. `safeExternalHref` allows only web, mail and tel schemes and
 * lets app-relative paths through unchanged.
 */

const SAFE_SCHEMES = new Set(["http:", "https:", "mailto:", "tel:"]);

/** True for an app-relative path (e.g. "/shop") — not protocol-relative "//x". */
function isInternalPath(value: string): boolean {
  return value.startsWith("/") && !value.startsWith("//");
}

/**
 * Return a safe href for a user/vendor-supplied URL, or `null` when it uses a
 * disallowed scheme (`javascript:`, `data:`, …) or cannot be parsed. Internal
 * app paths pass through unchanged.
 */
export function safeExternalHref(raw: string | null | undefined): string | null {
  if (!raw) return null;
  const value = raw.trim();
  if (!value) return null;
  if (isInternalPath(value)) return value;

  try {
    const url = new URL(value);
    return SAFE_SCHEMES.has(url.protocol) ? url.toString() : null;
  } catch {
    return null;
  }
}

/** True when the URL points off-app over http(s) (so it opens externally). */
export function isExternalUrl(raw: string | null | undefined): boolean {
  if (!raw) return false;
  const value = raw.trim();
  if (isInternalPath(value)) return false;

  try {
    const url = new URL(value);
    return url.protocol === "http:" || url.protocol === "https:";
  } catch {
    return false;
  }
}

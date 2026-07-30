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

export type SocialAppLink = {
  /** Native app deep link (used by the app launcher and on Android). */
  app: string;
  /** The original https URL — the Universal/App Link and browser fallback. */
  web: string;
};

/**
 * Recognise a social-platform URL and return the native app deep link plus its
 * https fallback, or `null` for a plain website (which keeps the in-app browser
 * behaviour). Social links should open the *app*, not an in-app web view — so
 * on the web we navigate to `web` at the top level, letting the platform's
 * Universal/App Link open the installed app with no error dialog; the native
 * shell uses `app` via the OS app launcher.
 */
export function socialAppLink(
  raw: string | null | undefined,
): SocialAppLink | null {
  const web = safeExternalHref(raw);
  if (!web || !web.startsWith("http")) return null;

  let url: URL;
  try {
    url = new URL(web);
  } catch {
    return null;
  }

  const host = url.hostname.replace(/^www\./, "").toLowerCase();
  const seg = url.pathname.split("/").filter(Boolean);
  const path = seg.join("/");
  const handle = (seg[0] ?? "").replace(/^@/, "");

  switch (true) {
    case host === "instagram.com" || host === "instagr.am":
      return { app: handle ? `instagram://user?username=${handle}` : "instagram://", web };
    case host === "facebook.com" || host === "fb.com" || host === "fb.me" || host === "m.facebook.com":
      return { app: `fb://facewebmodal/f?href=${encodeURIComponent(web)}`, web };
    case host === "linkedin.com" || host === "lnkd.in":
      return { app: path ? `linkedin://${path}` : "linkedin://", web };
    case host === "twitter.com" || host === "x.com":
      return { app: handle ? `twitter://user?screen_name=${handle}` : "twitter://", web };
    case host === "tiktok.com" || host === "vm.tiktok.com":
      return { app: handle ? `tiktok://@${handle}` : "tiktok://", web };
    case host === "youtube.com" || host === "youtu.be" || host === "m.youtube.com":
      return { app: `vnd.youtube://${url.host}${url.pathname}${url.search}`, web };
    case host === "threads.net" || host === "threads.com":
      return { app: handle ? `barcelona://user?username=${handle}` : "barcelona://", web };
    case host === "pinterest.com" || host === "pin.it":
      return { app: path ? `pinterest://${path}` : "pinterest://", web };
    case host === "snapchat.com":
      return { app: seg[0] === "add" && seg[1] ? `snapchat://add/${seg[1]}` : "snapchat://", web };
    case host === "t.me" || host === "telegram.me":
      return { app: handle ? `tg://resolve?domain=${handle}` : "tg://", web };
    case host === "wa.me" || host === "api.whatsapp.com":
      return { app: `whatsapp://send?phone=${handle}`, web };
    case host === "reddit.com":
      return { app: path ? `reddit://${path}` : "reddit://", web };
    default:
      return null;
  }
}

/** Coarse pointer / touch heuristic — a phone or tablet, where apps live. */
export function isMobileLike(): boolean {
  if (typeof window === "undefined") return false;
  if (typeof navigator !== "undefined" && navigator.maxTouchPoints > 0) {
    return true;
  }
  return (
    typeof window.matchMedia === "function" &&
    window.matchMedia("(pointer: coarse)").matches
  );
}

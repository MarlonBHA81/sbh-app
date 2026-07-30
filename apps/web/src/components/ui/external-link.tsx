"use client";

import * as React from "react";

import {
  isMobileLike,
  safeExternalHref,
  socialAppLink,
} from "@/lib/links";

type NativeBrowser = { open: (options: { url: string }) => Promise<void> };
type NativeAppLauncher = {
  canOpenUrl: (options: { url: string }) => Promise<{ value: boolean }>;
  openUrl: (options: { url: string }) => Promise<{ completed: boolean }>;
};

/**
 * A Capacitor plugin, when running inside the native shell. Detected at runtime
 * (no static import) so it is a no-op in the plain PWA and lights up the moment
 * the native wrap ships — nothing here to change later.
 */
function capPlugin<T>(name: string): T | null {
  if (typeof window === "undefined") return null;
  const cap = (
    window as unknown as {
      Capacitor?: { Plugins?: Record<string, unknown> };
    }
  ).Capacitor;
  return (cap?.Plugins?.[name] as T | undefined) ?? null;
}

export interface ExternalLinkProps
  extends Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, "href"> {
  href: string | null | undefined;
  /** Stop the click bubbling — for links nested inside a clickable card. */
  stopPropagation?: boolean;
}

/**
 * Anchor for any user- or vendor-supplied URL. It:
 *  1. Validates the scheme so a `javascript:`/`data:` payload can never run,
 *     rendering inert text when the URL is unsafe.
 *  2. Sends recognised social-platform URLs (Instagram, Facebook, LinkedIn,
 *     TikTok, X, YouTube, …) straight to the native app: the OS app launcher
 *     when the Capacitor bridge is present, otherwise the platform's
 *     Universal/App Link via a top-level navigation, which opens the installed
 *     app with no error dialog and falls back to the site when it isn't.
 *  3. Opens every other website in an in-app browser — the native in-app
 *     browser when the Capacitor bridge is present, otherwise a new tab/overlay
 *     which an installed PWA renders as its own in-app browser — so the app is
 *     never navigated away from.
 */
export const ExternalLink = React.forwardRef<
  HTMLAnchorElement,
  ExternalLinkProps
>(function ExternalLink(
  { href, stopPropagation, onClick, children, ...props },
  ref,
) {
  const safe = safeExternalHref(href);

  if (!safe) {
    return (
      <span {...(props as React.HTMLAttributes<HTMLSpanElement>)}>
        {children}
      </span>
    );
  }

  const isWeb = safe.startsWith("http");

  return (
    <a
      ref={ref}
      href={safe}
      {...(isWeb ? { target: "_blank", rel: "noopener noreferrer" } : {})}
      onClick={(event) => {
        if (stopPropagation) event.stopPropagation();
        onClick?.(event);
        if (event.defaultPrevented || !isWeb) return;

        const social = socialAppLink(safe);
        const launcher = capPlugin<NativeAppLauncher>("AppLauncher");
        const browser = capPlugin<NativeBrowser>("Browser");

        // Native shell: hand to the OS app launcher (social) or in-app browser.
        if (launcher || browser) {
          event.preventDefault();
          void (async () => {
            if (social && launcher) {
              try {
                const { value } = await launcher.canOpenUrl({ url: social.app });
                if (value) {
                  await launcher.openUrl({ url: social.app });
                  return;
                }
              } catch {
                // fall through to the browser fallback below
              }
            }
            if (browser) await browser.open({ url: safe });
            else window.open(safe, "_blank", "noopener,noreferrer");
          })();
          return;
        }

        // Plain PWA on a phone/tablet + a social URL → hand off to the app via
        // the platform Universal/App Link (top-level nav, no scheme probing so
        // no "cannot open page" dialog when the app isn't installed).
        if (social && isMobileLike()) {
          event.preventDefault();
          window.location.href = safe;
          return;
        }

        // Everything else (websites, and social links on desktop) falls through
        // to the anchor's target="_blank" in-app browser / new tab.
      }}
      {...props}
    >
      {children}
    </a>
  );
});

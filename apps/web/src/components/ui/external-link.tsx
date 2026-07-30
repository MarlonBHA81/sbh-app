"use client";

import * as React from "react";

import { safeExternalHref } from "@/lib/links";

type NativeBrowser = { open: (options: { url: string }) => Promise<void> };

/**
 * The Capacitor in-app browser plugin, when the app is running inside the
 * native shell. Detected at runtime (no static import) so it is a no-op in the
 * plain PWA and starts routing links through the native in-app browser the
 * moment the native wrap ships — nothing here to change later.
 */
function nativeBrowser(): NativeBrowser | null {
  if (typeof window === "undefined") return null;
  const cap = (
    window as unknown as {
      Capacitor?: { Plugins?: { Browser?: NativeBrowser } };
    }
  ).Capacitor;
  return cap?.Plugins?.Browser ?? null;
}

export interface ExternalLinkProps
  extends Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, "href"> {
  href: string | null | undefined;
  /** Stop the click bubbling — for links nested inside a clickable card. */
  stopPropagation?: boolean;
}

/**
 * Anchor for any user- or vendor-supplied URL. It (1) validates the scheme so a
 * `javascript:`/`data:` payload can never run, and (2) forces the link to open
 * in an in-app browser: the native in-app browser when the Capacitor bridge is
 * present, otherwise a new tab/overlay — which an installed PWA renders as its
 * own in-app browser — so the app is never navigated away from. When the URL is
 * unsafe it renders inert text instead of a link.
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
        const browser = isWeb ? nativeBrowser() : null;
        if (browser) {
          event.preventDefault();
          void browser.open({ url: safe });
        }
        onClick?.(event);
      }}
      {...props}
    >
      {children}
    </a>
  );
});

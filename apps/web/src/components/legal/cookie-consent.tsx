"use client";

import Link from "next/link";

import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import { useConsentStore } from "@/lib/stores/consent-store";

/**
 * Cookie consent banner (GDPR/POPIA). Shows until the visitor accepts or
 * rejects non-essential cookies. Strictly-necessary cookies are unaffected.
 * Rendered globally; self-hides once a choice is stored.
 */
export function CookieConsent() {
  const choice = useConsentStore((s) => s.choice);
  const hydrated = useConsentStore((s) => s.hydrated);
  const setChoice = useConsentStore((s) => s.setChoice);

  // Store the choice locally (gates cookies immediately) and, for a signed-in
  // user, persist an auditable server-side record (POPIA). The POST is
  // best-effort: it 401s silently for anonymous visitors, who are covered by
  // the local choice until they authenticate.
  const record = (value: "accepted" | "rejected") => {
    setChoice(value);
    api.post("/api/v1/me/consent", { choice: value }).catch(() => {});
  };

  // Wait for the persisted choice to hydrate to avoid an SSR/first-paint flash.
  if (!hydrated || choice !== null) return null;

  return (
    <div
      role="dialog"
      aria-label="Cookie consent"
      className="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-xl rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-lg"
    >
      <p className="text-sm leading-relaxed text-text-primary">
        We use strictly-necessary cookies to keep you signed in, and — only with
        your consent — optional cookies to remember preferences and improve SBH.
        See our{" "}
        <Link href="/legal/cookies" className="text-teal-text hover:underline">
          Cookie Policy
        </Link>
        .
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <Button className="h-10 flex-1" onClick={() => record("accepted")}>
          Accept all
        </Button>
        <Button
          variant="outline"
          className="h-10 flex-1"
          onClick={() => record("rejected")}
        >
          Reject non-essential
        </Button>
      </div>
    </div>
  );
}

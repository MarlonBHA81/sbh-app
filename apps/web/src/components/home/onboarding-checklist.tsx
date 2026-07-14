"use client";

import { Check, X } from "lucide-react";
import Link from "next/link";
import { useCallback, useState, useSyncExternalStore } from "react";

import { Progress } from "@/components/ui/progress";
import { computeProfileStrength } from "@/lib/profile-strength";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

/**
 * Goal-gradient onboarding checklist (UX pattern 2). A new user lands here
 * already at 20% with "Create your account" checked — never at zero. Auto-hides
 * once every step is done, and can be dismissed (remembered per profile).
 */
export function OnboardingChecklist() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const storageKey = activeProfile
    ? `sbh:onboarding-dismissed:${activeProfile.ulid}`
    : null;

  // Read the persisted dismissal without a setState-in-effect: the server
  // snapshot assumes "dismissed" so nothing flashes before hydration, then the
  // client reads the real localStorage value. A local flag covers same-tab
  // dismissals (the "storage" event only fires in *other* tabs).
  const subscribe = useCallback((onChange: () => void) => {
    window.addEventListener("storage", onChange);
    return () => window.removeEventListener("storage", onChange);
  }, []);
  const persistedDismissed = useSyncExternalStore(
    subscribe,
    () => (storageKey ? window.localStorage.getItem(storageKey) === "1" : true),
    () => true,
  );
  const [locallyDismissed, setLocallyDismissed] = useState(false);
  const dismissed = persistedDismissed || locallyDismissed;

  if (!activeProfile || dismissed) return null;

  const { steps, pct, complete } = computeProfileStrength(activeProfile);
  if (complete) return null;

  const doneCount = steps.filter((s) => s.done).length;

  function dismiss() {
    if (storageKey) window.localStorage.setItem(storageKey, "1");
    setLocallyDismissed(true);
  }

  return (
    <section
      className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card"
      aria-label="Getting started"
    >
      <div className="flex items-start gap-3">
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <h2 className="font-heading text-[15px] font-semibold text-text-primary">
            Get your profile ready
          </h2>
          <p className="text-[13px] text-text-secondary">
            {doneCount} of {steps.length} done · you&apos;re {pct}% there
          </p>
        </div>
        <button
          type="button"
          onClick={dismiss}
          aria-label="Dismiss"
          className="flex size-8 shrink-0 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-accent active:scale-[0.98]"
        >
          <X className="size-4" aria-hidden />
        </button>
      </div>

      <Progress value={pct} />

      <ul className="flex flex-col">
        {steps.map((step) => (
          <li key={step.key}>
            {step.done ? (
              <div className="flex items-center gap-3 py-2">
                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-teal text-white">
                  <Check className="size-3.5" aria-hidden />
                </span>
                <span className="text-sm text-text-secondary line-through">
                  {step.label}
                </span>
              </div>
            ) : (
              <Link
                href={step.href}
                className="flex items-center gap-3 py-2 active:scale-[0.99]"
              >
                <span className="size-6 shrink-0 rounded-full border-2 border-warmgray" />
                <span className="flex-1 text-sm font-medium text-text-primary">
                  {step.label}
                </span>
                <span
                  className={cn(
                    "shrink-0 rounded-full bg-teal/12 px-2 py-0.5 text-[11px] font-medium text-teal-text",
                  )}
                >
                  +{step.points}%
                </span>
              </Link>
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}

"use client";

import { Building2, X } from "lucide-react";
import { useCallback, useState, useSyncExternalStore } from "react";

import { CreateBusinessProfileDialog } from "@/components/shell/create-business-profile-dialog";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

const STORAGE_KEY = "sbh:business-prompt-dismissed";

/**
 * First-login nudge to create a business profile. Shown once a member is on
 * their personal profile and has no business profile yet — most people sign up
 * personal and never discover the business side. Dismissible (remembered across
 * sessions) and opens the CIPC-gated create dialog.
 */
export function BusinessProfilePrompt() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const profiles = useAuthStore((s) => s.profiles);

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
    () => window.localStorage.getItem(STORAGE_KEY) === "1",
    () => true,
  );
  const [locallyDismissed, setLocallyDismissed] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const dismissed = persistedDismissed || locallyDismissed;

  const hasBusiness = profiles.some((p) => p.kind === "business");
  const show = activeProfile?.kind === "personal" && !hasBusiness && !dismissed;

  function dismiss() {
    try {
      window.localStorage.setItem(STORAGE_KEY, "1");
    } catch {
      // Storage unavailable (private mode etc.) — non-fatal.
    }
    setLocallyDismissed(true);
  }

  return (
    <>
      {show ? (
        <section
          className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card"
          aria-label="Create a business profile"
        >
          <div className="flex items-start gap-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-plum/12 text-plum">
              <Building2 className="size-4.5" aria-hidden />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
              <h2 className="font-heading text-[15px] font-semibold text-text-primary">
                Grow with a business profile
              </h2>
              <p className="text-[13px] text-text-secondary">
                Post as your business, get discovered, and earn a CIPC-verified
                badge.
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
          <Button
            type="button"
            className="h-11 w-full sm:w-auto sm:self-start"
            onClick={() => setCreateOpen(true)}
          >
            Create business profile
          </Button>
        </section>
      ) : null}
      <CreateBusinessProfileDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
      />
    </>
  );
}

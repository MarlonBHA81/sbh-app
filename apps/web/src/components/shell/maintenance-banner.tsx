"use client";

import { TriangleAlert, X } from "lucide-react";
import { useEffect, useState } from "react";

import * as api from "@/lib/api/client";
import type { AppStatus } from "@/lib/api/types";

const DISMISS_KEY = "sbh.maintenanceDismissed";

function dismissedMessage(): string | null {
  if (typeof window === "undefined") return null;
  try {
    return window.sessionStorage.getItem(DISMISS_KEY);
  } catch {
    return null;
  }
}

/**
 * Fetches the public /status endpoint on mount (fail-silent) and shows a
 * dismissible amber banner while a maintenance message is set. Dismissal is
 * remembered for the browsing session (keyed on the message text, so a new
 * message re-surfaces).
 */
export function MaintenanceBanner() {
  const [message, setMessage] = useState<string | null>(null);
  const [dismissed, setDismissed] = useState(true);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: AppStatus }>("/api/v1/status")
      .then((res) => {
        if (cancelled) return;
        const next = res.data.maintenance_message;
        setMessage(next);
        setDismissed(next != null && dismissedMessage() === next);
      })
      .catch(() => {
        // Fail silent — the banner is non-essential.
      });
    return () => {
      cancelled = true;
    };
  }, []);

  function dismiss() {
    setDismissed(true);
    try {
      if (message) window.sessionStorage.setItem(DISMISS_KEY, message);
    } catch {
      // Storage unavailable — non-fatal.
    }
  }

  if (!message || dismissed) return null;

  return (
    <div
      role="status"
      className="flex items-start gap-3 border-b border-amber-300 bg-amber-50 px-4 py-2.5 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
    >
      <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
      <p className="min-w-0 flex-1 text-sm">{message}</p>
      <button
        type="button"
        aria-label="Dismiss"
        onClick={dismiss}
        className="-my-1 shrink-0 rounded-md p-1 transition-colors hover:bg-amber-100 dark:hover:bg-amber-500/20"
      >
        <X className="size-4" aria-hidden />
      </button>
    </div>
  );
}

"use client";

import { Bug } from "lucide-react";
import { useState } from "react";

import { BugReportDialog } from "@/components/support/bug-report-dialog";
import { Button } from "@/components/ui/button";

/**
 * Always-visible "report a bug" button (like Claude Code's), floating in the
 * app shell so a member can flag a problem from any screen. Opens the existing
 * BugReportDialog, which captures page/context automatically.
 *
 * Placement avoids the other floating chrome: on mobile it sits bottom-left
 * above the nav (the composer FAB owns bottom-right); on desktop it moves to
 * the free bottom-right corner.
 */
export function BugReportFab() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <Button
        type="button"
        size="icon"
        variant="outline"
        aria-label="Report a bug"
        title="Report a bug"
        onClick={() => setOpen(true)}
        className="fixed bottom-[calc(6.25rem+env(safe-area-inset-bottom))] left-5 z-40 size-11 rounded-full bg-card text-muted-foreground shadow-lg md:right-5 md:bottom-5 md:left-auto"
      >
        <Bug className="size-5" aria-hidden />
      </Button>
      <BugReportDialog open={open} onOpenChange={setOpen} />
    </>
  );
}

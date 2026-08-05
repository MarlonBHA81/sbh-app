"use client";

import { CheckCircle2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import { BUG_DETAILS_MAX, BUG_SUMMARY_MAX, submitBugReport } from "@/lib/bugs";

/**
 * Lets a member report a bug they hit. The current page URL is captured
 * automatically (see submitBugReport), so they only describe the problem.
 */
export function BugReportDialog({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const [summary, setSummary] = useState("");
  const [details, setDetails] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);
  const [wasOpen, setWasOpen] = useState(open);

  // Reset the flow whenever the dialog (re)opens.
  if (open !== wasOpen) {
    setWasOpen(open);
    if (open) {
      setSummary("");
      setDetails("");
      setSubmitting(false);
      setDone(false);
    }
  }

  async function handleSubmit() {
    const trimmed = summary.trim();
    if (!trimmed || submitting) return;
    setSubmitting(true);
    try {
      await submitBugReport({
        summary: trimmed,
        ...(details.trim() ? { details: details.trim() } : {}),
      });
      setDone(true);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't send your report",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85dvh] overflow-y-auto">
        {done ? (
          <>
            <DialogHeader>
              <div className="mx-auto mb-1 flex size-11 items-center justify-center rounded-full bg-teal/15 text-teal-text">
                <CheckCircle2 className="size-6" aria-hidden />
              </div>
              <DialogTitle className="text-center">Thank you</DialogTitle>
              <DialogDescription className="text-center">
                Your report is in. Our team will take a look — we appreciate you
                flagging it.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button className="w-full" onClick={() => onOpenChange(false)}>
                Done
              </Button>
            </DialogFooter>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle>Report a bug</DialogTitle>
              <DialogDescription>
                Tell us what went wrong. The page you&apos;re on is included
                automatically.
              </DialogDescription>
            </DialogHeader>
            <div className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="bug-summary">What happened?</Label>
                <Textarea
                  id="bug-summary"
                  value={summary}
                  onChange={(event) =>
                    setSummary(event.target.value.slice(0, BUG_SUMMARY_MAX))
                  }
                  placeholder="e.g. Tapping Pay on a product does nothing"
                  rows={2}
                  autoFocus
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="bug-details">
                  Steps or extra detail{" "}
                  <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Textarea
                  id="bug-details"
                  value={details}
                  onChange={(event) =>
                    setDetails(event.target.value.slice(0, BUG_DETAILS_MAX))
                  }
                  placeholder="What were you doing? What did you expect to happen?"
                  rows={4}
                />
              </div>
            </div>
            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={submitting}
              >
                Cancel
              </Button>
              <Button
                onClick={() => void handleSubmit()}
                disabled={!summary.trim() || submitting}
              >
                {submitting ? "Sending…" : "Send report"}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

"use client";

import { CheckCircle2, ShieldCheck } from "lucide-react";
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
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import {
  isExistingReport,
  REPORT_CATEGORIES,
  REPORT_DETAILS_MAX,
  submitReport,
  type ReportableType,
  type ReportCategory,
} from "@/lib/safety";

type Step = "category" | "details" | "success" | "already";

export function ReportDialog({
  open,
  onOpenChange,
  reportableType,
  reportableUlid,
  /** Short line describing the target, e.g. "@alex's post" or "@alex". */
  contextLabel,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  reportableType: ReportableType;
  reportableUlid: string;
  contextLabel: string;
}) {
  const [step, setStep] = useState<Step>("category");
  const [category, setCategory] = useState<ReportCategory | null>(null);
  const [details, setDetails] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [wasOpen, setWasOpen] = useState(open);

  // Reset the flow while rendering whenever the dialog (re)opens.
  if (open !== wasOpen) {
    setWasOpen(open);
    if (open) {
      setStep("category");
      setCategory(null);
      setDetails("");
      setSubmitting(false);
    }
  }

  async function handleSubmit() {
    if (!category || submitting) return;
    setSubmitting(true);
    try {
      const result = await submitReport({
        reportable_type: reportableType,
        reportable_ulid: reportableUlid,
        category,
        ...(details.trim() ? { details: details.trim() } : {}),
      });
      setStep(isExistingReport(result) ? "already" : "success");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't submit your report",
      );
    } finally {
      setSubmitting(false);
    }
  }

  const done = step === "success" || step === "already";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[85dvh] overflow-y-auto"
        // The dialog is portaled; stop clicks bubbling to any parent card.
        onClick={(event) => event.stopPropagation()}
      >
        {step === "category" ? (
          <>
            <DialogHeader>
              <DialogTitle>Report {contextLabel}</DialogTitle>
              <DialogDescription>
                Why are you reporting this? Your report is anonymous.
              </DialogDescription>
            </DialogHeader>
            <RadioGroup
              value={category ?? ""}
              onValueChange={(value) => setCategory(value as ReportCategory)}
              className="gap-1"
            >
              {REPORT_CATEGORIES.map((option) => (
                <Label
                  key={option.value}
                  htmlFor={`report-${option.value}`}
                  className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-accent/40"
                >
                  <RadioGroupItem
                    id={`report-${option.value}`}
                    value={option.value}
                    className="mt-0.5"
                  />
                  <span className="flex flex-col gap-0.5">
                    <span className="text-sm font-medium">{option.label}</span>
                    <span className="text-xs text-muted-foreground">
                      {option.description}
                    </span>
                  </span>
                </Label>
              ))}
            </RadioGroup>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                className="h-11"
                onClick={() => onOpenChange(false)}
              >
                Cancel
              </Button>
              <Button
                type="button"
                className="h-11"
                disabled={!category}
                onClick={() => setStep("details")}
              >
                Next
              </Button>
            </DialogFooter>
          </>
        ) : null}

        {step === "details" ? (
          <>
            <DialogHeader>
              <DialogTitle>Add details</DialogTitle>
              <DialogDescription>
                Optional — tell our moderators anything that helps.
              </DialogDescription>
            </DialogHeader>
            <div className="flex flex-col gap-2">
              <p className="rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                Reporting {contextLabel} for{" "}
                <span className="font-medium text-foreground">
                  {
                    REPORT_CATEGORIES.find((c) => c.value === category)?.label
                  }
                </span>
              </p>
              <Textarea
                rows={4}
                maxLength={REPORT_DETAILS_MAX}
                placeholder="Add context (optional)"
                value={details}
                onChange={(event) => setDetails(event.target.value)}
              />
              <span className="self-end text-xs text-muted-foreground tabular-nums">
                {details.length}/{REPORT_DETAILS_MAX}
              </span>
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                className="h-11"
                onClick={() => setStep("category")}
              >
                Back
              </Button>
              <Button
                type="button"
                className="h-11"
                disabled={submitting}
                onClick={() => void handleSubmit()}
              >
                {submitting ? "Submitting…" : "Submit report"}
              </Button>
            </DialogFooter>
          </>
        ) : null}

        {done ? (
          <>
            <DialogHeader className="items-center text-center sm:items-center sm:text-center">
              <div className="mb-1 flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15">
                {step === "already" ? (
                  <ShieldCheck className="size-6" aria-hidden />
                ) : (
                  <CheckCircle2 className="size-6" aria-hidden />
                )}
              </div>
              <DialogTitle>
                {step === "already"
                  ? "Already reported"
                  : "Thanks for letting us know"}
              </DialogTitle>
              <DialogDescription>
                {step === "already"
                  ? "You've already reported this — it's under review by our moderators."
                  : "Our moderators will review this report and take action if it breaks our rules."}
              </DialogDescription>
            </DialogHeader>
            <DialogFooter className="sm:justify-center">
              <Button
                type="button"
                className="h-11 min-w-24"
                onClick={() => onOpenChange(false)}
              >
                Done
              </Button>
            </DialogFooter>
          </>
        ) : null}
      </DialogContent>
    </Dialog>
  );
}

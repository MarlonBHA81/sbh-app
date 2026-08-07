"use client";

import { BadgeCheck, Clock, ShieldCheck, Upload } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import * as api from "@/lib/api/client";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

type VerificationStatus = "pending" | "reviewing" | "approved" | "rejected";

interface Verification {
  status: VerificationStatus;
  legal_name: string | null;
  decision_note: string | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  documents: { type: string; original_name: string | null }[];
}

const ACCEPT = ".pdf,.png,.jpg,.jpeg,.webp";

/**
 * Business verification (Phase 3): submit ID / CIPC / B-BBEE documents and see
 * the review status. Shown to business profiles on the settings page. The API
 * stores documents on private storage; a reviewer approves or rejects them.
 */
export function BusinessVerificationSettings() {
  const activeProfile = useAuthStore((s) => s.activeProfile);

  const [verification, setVerification] = useState<
    Verification | null | undefined
  >(undefined);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Verification | null }>("/api/v1/me/verification")
      .then((res) => {
        if (!cancelled) setVerification(res.data);
      })
      .catch(() => {
        if (!cancelled) setVerification(null);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  async function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting) return;
    const form = new FormData(event.currentTarget);
    setSubmitting(true);
    try {
      const res = await api.postMultipart<{ data: Verification }>(
        "/api/v1/me/verification",
        form,
      );
      setVerification(res.data);
      toast.success("Verification submitted for review.");
    } catch (error) {
      const message =
        error instanceof api.ApiError
          ? (Object.values(error.errors ?? {})[0]?.[0] ?? error.message)
          : "Could not submit. Please try again.";
      toast.error(message);
    } finally {
      setSubmitting(false);
    }
  }

  if (activeProfile?.kind !== "business") return null;
  if (verification === undefined) return null; // still loading

  const verified =
    verification?.status === "approved" || activeProfile.is_verified;
  const inReview =
    verification?.status === "pending" || verification?.status === "reviewing";
  // A rejected (or never-submitted) business can submit; keep the form visible.
  const canSubmit = !verified && !inReview;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ShieldCheck className="size-4 text-teal-text" />
          Business verification
        </CardTitle>
        <CardDescription>
          Verify your business with ID, CIPC registration and (optionally) a
          B-BBEE certificate to earn a verified badge. Documents are stored
          securely and only seen by our review team.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {verified ? (
          <div className="flex items-center gap-2 rounded-(--radius-card) bg-teal/10 px-3 py-2.5 text-sm text-teal-text">
            <BadgeCheck className="size-4" />
            Your business is verified.
          </div>
        ) : inReview ? (
          <div className="flex items-center gap-2 rounded-(--radius-card) bg-muted px-3 py-2.5 text-sm text-text-secondary">
            <Clock className="size-4" />
            Your documents are under review. We&rsquo;ll update your badge once a
            reviewer has checked them.
          </div>
        ) : null}

        {verification?.status === "rejected" && verification.decision_note ? (
          <div className="rounded-(--radius-card) border border-destructive/40 bg-destructive/5 px-3 py-2.5 text-sm text-destructive">
            Your previous submission was declined:{" "}
            <span className="font-medium">{verification.decision_note}</span>.
            You can resubmit below.
          </div>
        ) : null}

        {canSubmit ? (
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="legal_name">Registered legal name</Label>
              <Input id="legal_name" name="legal_name" required maxLength={200} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="registration_number">
                Registration number{" "}
                <span className="text-text-secondary">(optional)</span>
              </Label>
              <Input
                id="registration_number"
                name="registration_number"
                maxLength={100}
                placeholder="e.g. 2019/123456/07"
              />
            </div>

            <FileField
              id="id_document"
              label="ID document"
              hint="Owner's ID or passport"
              required
            />
            <FileField
              id="cipc_document"
              label="CIPC registration"
              hint="Company registration document"
              required
            />
            <FileField
              id="bbee_document"
              label="B-BBEE certificate"
              hint="Optional, if you have one"
            />

            <Button type="submit" disabled={submitting} className="w-full">
              <Upload className="size-4" />
              {submitting ? "Submitting…" : "Submit for verification"}
            </Button>
            <p className="text-xs text-text-secondary">
              Accepted formats: PDF, PNG or JPG, up to 10&nbsp;MB each.
            </p>
          </form>
        ) : null}
      </CardContent>
    </Card>
  );

  function FileField({
    id,
    label,
    hint,
    required,
  }: {
    id: string;
    label: string;
    hint: string;
    required?: boolean;
  }) {
    return (
      <div className="space-y-1.5">
        <Label htmlFor={id}>
          {label}{" "}
          {!required ? (
            <span className="text-text-secondary">(optional)</span>
          ) : null}
        </Label>
        <Input
          id={id}
          name={id}
          type="file"
          accept={ACCEPT}
          required={required}
          className="file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1 file:text-sm"
        />
        <p className="text-xs text-text-secondary">{hint}</p>
      </div>
    );
  }
}

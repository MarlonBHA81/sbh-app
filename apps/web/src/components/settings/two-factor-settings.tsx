"use client";

import { KeyRound, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
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

type IdentityAction = "disable" | "regenerate";

/**
 * Member TOTP two-factor: enrol (scan a QR, confirm a code), view/regenerate
 * single-use recovery codes, and turn it off. Sensitive changes re-confirm the
 * account password (or a current code for password-less social accounts).
 */
export function TwoFactorSettings() {
  const user = useAuthStore((s) => s.user);
  const fetchMe = useAuthStore((s) => s.fetchMe);
  const enabled = Boolean(user?.two_factor_enabled);
  const hasPassword = Boolean(user?.has_password);

  const [setup, setSetup] = useState<{ secret: string; qr: string } | null>(
    null,
  );
  const [confirmCode, setConfirmCode] = useState("");
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [busy, setBusy] = useState(false);

  const [identity, setIdentity] = useState<IdentityAction | null>(null);
  const [identityValue, setIdentityValue] = useState("");

  function reportError(error: unknown, fallback: string) {
    toast.error(error instanceof api.ApiError ? error.message : fallback);
  }

  async function beginEnroll() {
    if (busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ secret: string; qr: string }>(
        "/api/v1/me/2fa/enroll",
      );
      setSetup(res);
      setConfirmCode("");
      setRecoveryCodes(null);
    } catch (error) {
      reportError(error, "Couldn't start two-factor setup.");
    } finally {
      setBusy(false);
    }
  }

  async function confirmEnroll() {
    if (busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ recovery_codes: string[] }>(
        "/api/v1/me/2fa/confirm",
        { code: confirmCode },
      );
      setSetup(null);
      setConfirmCode("");
      setRecoveryCodes(res.recovery_codes);
      await fetchMe();
      toast.success("Two-factor authentication is on.");
    } catch (error) {
      reportError(error, "That code is incorrect or has expired.");
    } finally {
      setBusy(false);
    }
  }

  async function submitIdentity() {
    if (busy || !identity) return;
    setBusy(true);
    const payload = hasPassword
      ? { password: identityValue }
      : { code: identityValue };
    try {
      if (identity === "disable") {
        await api.del("/api/v1/me/2fa", payload);
        await fetchMe();
        setRecoveryCodes(null);
        toast.success("Two-factor authentication is off.");
      } else {
        const res = await api.post<{ recovery_codes: string[] }>(
          "/api/v1/me/2fa/recovery-codes",
          payload,
        );
        setRecoveryCodes(res.recovery_codes);
        toast.success("New recovery codes generated.");
      }
      setIdentity(null);
      setIdentityValue("");
    } catch (error) {
      reportError(error, "Couldn't verify your identity.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <ShieldCheck className="size-4 text-teal-text" aria-hidden />
          Two-factor authentication
        </CardTitle>
        <CardDescription>
          Add a second step at sign-in using an authenticator app (Google
          Authenticator, Authy, 1Password…).
          {enabled ? " It's currently on." : " It's currently off."}
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {/* Freshly generated recovery codes — shown once. */}
        {recoveryCodes ? (
          <div className="rounded-lg border border-border bg-muted/40 p-4">
            <p className="mb-2 flex items-center gap-2 text-sm font-medium">
              <KeyRound className="size-4" aria-hidden />
              Save your recovery codes
            </p>
            <p className="mb-3 text-xs text-muted-foreground">
              Each code works once if you lose access to your app. Store them
              somewhere safe — you won&apos;t see them again.
            </p>
            <ul className="grid grid-cols-2 gap-1 font-mono text-sm">
              {recoveryCodes.map((c) => (
                <li key={c}>{c}</li>
              ))}
            </ul>
            <Button
              type="button"
              variant="outline"
              className="mt-3 h-9"
              onClick={() => {
                void navigator.clipboard
                  ?.writeText(recoveryCodes.join("\n"))
                  .then(() => toast.success("Copied to clipboard."));
              }}
            >
              Copy codes
            </Button>
          </div>
        ) : null}

        {/* Enrolment: QR + secret + confirm. */}
        {setup ? (
          <div className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">
              Scan this QR code with your authenticator app, then enter the
              6-digit code it shows to finish.
            </p>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={setup.qr}
              alt="Two-factor QR code"
              className="size-44 self-center rounded-lg border border-border bg-white p-2"
            />
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">
                Or enter this key manually
              </Label>
              <code className="rounded bg-muted px-2 py-1 font-mono text-xs break-all">
                {setup.secret}
              </code>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="2fa-confirm">Verification code</Label>
              <Input
                id="2fa-confirm"
                value={confirmCode}
                onChange={(e) => setConfirmCode(e.target.value)}
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="123456"
                className="h-11"
              />
            </div>
            <div className="flex gap-2">
              <Button
                type="button"
                className="h-11 flex-1"
                disabled={busy || confirmCode.trim() === ""}
                onClick={() => void confirmEnroll()}
              >
                {busy ? "Verifying…" : "Turn on"}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="h-11"
                onClick={() => {
                  setSetup(null);
                  setConfirmCode("");
                }}
              >
                Cancel
              </Button>
            </div>
          </div>
        ) : null}

        {/* Idle controls. */}
        {!setup ? (
          enabled ? (
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                className="h-11"
                onClick={() => {
                  setIdentityValue("");
                  setIdentity("regenerate");
                }}
              >
                Regenerate recovery codes
              </Button>
              <Button
                type="button"
                variant="outline"
                className="h-11 border-destructive/40 text-destructive hover:text-destructive"
                onClick={() => {
                  setIdentityValue("");
                  setIdentity("disable");
                }}
              >
                Turn off
              </Button>
            </div>
          ) : (
            <Button
              type="button"
              className="h-11 self-start"
              disabled={busy}
              onClick={() => void beginEnroll()}
            >
              {busy ? "Starting…" : "Set up two-factor"}
            </Button>
          )
        ) : null}
      </CardContent>

      <AlertDialog
        open={identity !== null}
        onOpenChange={(open) => {
          if (!open) {
            setIdentity(null);
            setIdentityValue("");
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {identity === "disable"
                ? "Turn off two-factor?"
                : "Regenerate recovery codes?"}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {identity === "disable"
                ? "Your account will no longer ask for a code at sign-in."
                : "Your previous recovery codes will stop working."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="flex flex-col gap-2">
            <Label htmlFor="2fa-identity" className="text-sm">
              {hasPassword
                ? "Enter your password to confirm"
                : "Enter a current code from your app"}
            </Label>
            <Input
              id="2fa-identity"
              type={hasPassword ? "password" : "text"}
              autoComplete={hasPassword ? "current-password" : "one-time-code"}
              value={identityValue}
              onChange={(e) => setIdentityValue(e.target.value)}
              className="h-11"
            />
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11"
              disabled={busy || identityValue.trim() === ""}
              onClick={(event) => {
                event.preventDefault();
                void submitIdentity();
              }}
            >
              {busy ? "Confirming…" : "Confirm"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}

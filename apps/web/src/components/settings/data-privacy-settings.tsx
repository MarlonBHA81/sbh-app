"use client";

import { Download, ShieldAlert } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
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

/**
 * GDPR/POPIA data-subject rights: export a copy of your data (right of
 * access) and permanently delete your account (right to erasure).
 */
export function DataPrivacySettings() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const activeHandle = useAuthStore((s) => s.activeProfile?.handle ?? "");
  const logout = useAuthStore((s) => s.logout);
  const hasPassword = Boolean(user?.has_password);

  const [exporting, setExporting] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [confirmValue, setConfirmValue] = useState("");
  const [deleting, setDeleting] = useState(false);

  async function exportData() {
    if (exporting) return;
    setExporting(true);
    try {
      const res = await api.get<{ data: unknown }>("/api/v1/me/export");
      const blob = new Blob([JSON.stringify(res.data, null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "sbh-my-data.json";
      a.click();
      URL.revokeObjectURL(url);
      toast.success("Your data has been downloaded.");
    } catch {
      toast.error("Couldn't export your data. Please try again.");
    } finally {
      setExporting(false);
    }
  }

  async function deleteAccount() {
    if (deleting) return;
    setDeleting(true);
    try {
      await api.del(
        "/api/v1/me/account",
        hasPassword
          ? { password: confirmValue }
          : { confirm_handle: confirmValue },
      );
      toast.success("Your account has been deleted.");
      await logout();
      router.replace("/register");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't delete your account.",
      );
    } finally {
      setDeleting(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Privacy &amp; data</CardTitle>
        <CardDescription>
          Download a copy of your data or delete your account. See our{" "}
          <Link href="/legal/privacy" className="text-teal-text hover:underline">
            Privacy Policy
          </Link>
          .
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        <Button
          type="button"
          variant="outline"
          className="h-11 justify-start gap-2"
          disabled={exporting}
          onClick={() => void exportData()}
        >
          <Download className="size-4" aria-hidden />
          {exporting ? "Preparing…" : "Download my data"}
        </Button>

        <Button
          type="button"
          variant="outline"
          className="h-11 justify-start gap-2 border-destructive/40 text-destructive hover:text-destructive"
          onClick={() => {
            setConfirmValue("");
            setDeleteOpen(true);
          }}
        >
          <ShieldAlert className="size-4" aria-hidden />
          Delete account
        </Button>
      </CardContent>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete your account?</AlertDialogTitle>
            <AlertDialogDescription>
              This permanently deletes your account, profiles, posts and media.
              It cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="flex flex-col gap-2">
            <Label htmlFor="delete-confirm" className="text-sm">
              {hasPassword
                ? "Enter your password to confirm"
                : `Type @${activeHandle} to confirm`}
            </Label>
            <Input
              id="delete-confirm"
              type={hasPassword ? "password" : "text"}
              autoComplete="off"
              value={confirmValue}
              onChange={(event) => setConfirmValue(event.target.value)}
              className="h-11"
            />
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={deleting || confirmValue.trim() === ""}
              onClick={(event) => {
                event.preventDefault();
                void deleteAccount();
              }}
            >
              {deleting ? "Deleting…" : "Delete forever"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}

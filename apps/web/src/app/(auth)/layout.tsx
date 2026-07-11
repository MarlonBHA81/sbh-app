"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { useAuthStore } from "@/lib/stores/auth-store-provider";

export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const status = useAuthStore((s) => s.status);
  const router = useRouter();

  useEffect(() => {
    if (status === "authed") router.replace("/home");
  }, [status, router]);

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-8 p-4">
      <div className="flex items-center gap-3">
        <div className="flex size-11 items-center justify-center rounded-xl bg-primary text-sm font-bold text-primary-foreground">
          SBH
        </div>
        <span className="text-xl font-semibold tracking-tight">SBH</span>
      </div>
      {children}
    </div>
  );
}

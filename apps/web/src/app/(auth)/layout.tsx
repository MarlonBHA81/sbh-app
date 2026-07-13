"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { SbhLogo } from "@/components/brand/sbh-logo";
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
      <SbhLogo markClassName="size-14" textClassName="text-xl" />

      {children}
    </div>
  );
}

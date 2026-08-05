"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import Link from "next/link";
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
      <footer className="flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-text-secondary">
        <Link href="/legal/privacy" className="hover:underline">
          Privacy
        </Link>
        <Link href="/legal/cookies" className="hover:underline">
          Cookies
        </Link>
        <Link href="/legal/terms" className="hover:underline">
          Terms
        </Link>
      </footer>
    </div>
  );
}

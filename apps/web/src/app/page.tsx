"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { SplashScreen } from "@/components/splash-screen";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

export default function RootPage() {
  const status = useAuthStore((s) => s.status);
  const router = useRouter();

  useEffect(() => {
    if (status === "authed") router.replace("/home");
    else if (status === "guest") router.replace("/login");
  }, [status, router]);

  return <SplashScreen />;
}

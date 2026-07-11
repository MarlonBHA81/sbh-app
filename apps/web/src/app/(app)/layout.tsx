"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { ComposerFab } from "@/components/composer/composer-fab";
import { ComposerProvider } from "@/components/composer/composer-provider";
import { NotificationsProvider } from "@/components/notifications/notifications-provider";
import { BottomNav } from "@/components/shell/bottom-nav";
import { MaintenanceBanner } from "@/components/shell/maintenance-banner";
import { SidebarNav } from "@/components/shell/sidebar-nav";
import { TopBar } from "@/components/shell/top-bar";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

function ShellSkeleton() {
  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-6xl">
      <div className="sticky top-0 hidden h-dvh w-60 shrink-0 flex-col gap-3 border-r p-4 md:flex">
        <Skeleton className="mb-4 h-9 w-32" />
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-11 w-full rounded-lg" />
        ))}
      </div>
      <div className="flex min-h-dvh w-full flex-1 flex-col">
        <div className="flex h-14 items-center justify-between border-b px-4 md:hidden">
          <Skeleton className="h-8 w-24" />
          <Skeleton className="size-9 rounded-full" />
        </div>
        <div className="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 px-4 py-6">
          <Skeleton className="h-8 w-40" />
          <Skeleton className="h-40 w-full rounded-xl" />
          <Skeleton className="h-40 w-full rounded-xl" />
        </div>
      </div>
      <div className="hidden w-72 shrink-0 p-4 lg:block">
        <Skeleton className="h-48 w-full rounded-xl" />
      </div>
    </div>
  );
}

export default function AppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const status = useAuthStore((s) => s.status);
  const router = useRouter();

  useEffect(() => {
    if (status === "guest") router.replace("/login");
  }, [status, router]);

  if (status !== "authed") {
    return <ShellSkeleton />;
  }

  return (
    <ComposerProvider>
      <NotificationsProvider />
      <MaintenanceBanner />
      <div className="mx-auto flex min-h-dvh w-full max-w-6xl">
        <SidebarNav />
        <div className="flex min-h-dvh w-full min-w-0 flex-1 flex-col">
          <TopBar />
          <main className="mx-auto w-full max-w-xl flex-1 px-4 py-4 pb-24 md:py-6 md:pb-8">
            {children}
          </main>
        </div>
        <aside
          aria-hidden
          className="hidden w-72 shrink-0 p-4 lg:block"
          data-slot="right-rail"
        />
        <BottomNav />
        <ComposerFab />
      </div>
    </ComposerProvider>
  );
}

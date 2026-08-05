"use client";

import { CloudOff, RefreshCw } from "lucide-react";
import { useTranslations } from "next-intl";

import { Button } from "@/components/ui/button";

// Serwist serves this page as the navigation fallback when the network is
// unreachable and the requested route isn't in the cache. Registered in
// `src/sw.ts` via `fallbacks.entries`.
export default function OfflinePage() {
  const t = useTranslations("offline");

  return (
    <main className="flex min-h-dvh flex-col items-center justify-center gap-5 p-6 text-center">
      <div className="flex size-16 items-center justify-center rounded-full bg-muted">
        <CloudOff className="size-8 text-muted-foreground" aria-hidden />
      </div>
      <div className="flex flex-col gap-2">
        <h1 className="text-xl font-semibold tracking-tight">{t("title")}</h1>
        <p className="max-w-sm text-sm text-muted-foreground">{t("body")}</p>
        <p className="text-xs text-muted-foreground">{t("hint")}</p>
      </div>
      <Button
        type="button"
        className="h-11 gap-2"
        onClick={() => window.location.reload()}
      >
        <RefreshCw className="size-4" aria-hidden />
        {t("retry")}
      </Button>
    </main>
  );
}

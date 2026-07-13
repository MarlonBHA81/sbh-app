"use client";

import { Megaphone } from "lucide-react";
import Link from "next/link";
import { useTranslations } from "next-intl";

import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { cn } from "@/lib/utils";

/**
 * Unsold ad inventory, made visible: rendered wherever a sponsor unit would
 * appear (inline in feeds, desktop right rail) when no campaign fills the
 * slot. Admins — the only accounts with Ad Center access — get a direct CTA.
 */
export function AdSpotPlaceholder({
  variant = "inline",
  className,
}: {
  variant?: "inline" | "rail";
  className?: string;
}) {
  const t = useTranslations("feed");
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));

  return (
    <aside
      aria-label={t("adSpotLabel")}
      className={cn(
        "flex flex-col gap-1.5 rounded-xl border border-dashed border-primary/40 bg-accent/40 p-4",
        variant === "rail" && "p-3",
        className,
      )}
    >
      <div className="flex items-center gap-1.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
        <Megaphone className="size-3.5 text-primary" aria-hidden />
        {t("adSpotLabel")}
      </div>
      <p className="text-sm font-semibold">{t("adSpotTitle")}</p>
      <p className="text-sm text-muted-foreground">{t("adSpotBody")}</p>
      {isAdmin ? (
        <Button asChild variant="outline" size="sm" className="mt-1.5 self-start">
          <Link href="/ads">{t("adSpotCta")}</Link>
        </Button>
      ) : null}
    </aside>
  );
}

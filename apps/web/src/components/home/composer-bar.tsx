"use client";

import { SendHorizontal } from "lucide-react";
import { useTranslations } from "next-intl";

import { useComposer } from "@/components/composer/composer-provider";

/**
 * Faux-input pill that opens the real compose flow (reskin spec). The whole
 * bar is one tap target; the inner "Post now" pill is decorative affordance.
 */
export function ComposerBar() {
  const t = useTranslations("home");
  const { openComposer } = useComposer();

  return (
    <button
      type="button"
      onClick={() => openComposer()}
      className="flex h-13 w-full items-center rounded-full bg-warmgray/50 ps-5 pe-1.5 text-start transition-colors hover:bg-warmgray/70 active:scale-[0.98]"
    >
      <span className="flex-1 truncate text-sm text-text-muted">
        {t("writeHere")}
      </span>
      <span className="flex h-10 shrink-0 items-center gap-2 rounded-full bg-slate px-4 font-heading text-[13px] font-medium text-white transition-colors group-hover:bg-slate-tint">
        {t("postNow")}
        <SendHorizontal className="size-4" aria-hidden />
      </span>
    </button>
  );
}

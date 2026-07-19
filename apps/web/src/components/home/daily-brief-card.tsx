"use client";

import { ExternalLink, Newspaper } from "lucide-react";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";

import * as api from "@/lib/api/client";
import type { BriefItem, DailyBriefData } from "@/lib/api/types";

/**
 * Daily Business Brief (V2 · Feature 7): a short personalised headline plus a
 * few admin-curated items matched to the member's industry. Self-hides when
 * there are no items so it never shows an empty shell. Works with no AI key —
 * the headline falls back to a canned line on the backend.
 */
export function DailyBriefCard() {
  const t = useTranslations("home");
  const [brief, setBrief] = useState<DailyBriefData | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const data = (
          await api.get<{ data: DailyBriefData }>("/api/v1/me/brief")
        ).data;
        if (!cancelled) setBrief(data);
      } catch {
        if (!cancelled) setBrief(null);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  if (!brief || brief.items.length === 0) return null;

  return (
    <section className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-2">
        <span className="flex size-8 items-center justify-center rounded-full bg-teal/12 text-teal-text">
          <Newspaper className="size-4" aria-hidden />
        </span>
        <h2 className="font-heading text-[15px] font-semibold text-text-primary">
          {t("brief.title")}
        </h2>
      </div>

      {brief.headline ? (
        <p className="text-sm text-text-secondary">{brief.headline}</p>
      ) : null}

      <ul className="flex flex-col gap-3">
        {brief.items.map((item) => (
          <BriefRow key={item.ulid} item={item} kindLabel={t(`brief.kinds.${item.kind}`)} />
        ))}
      </ul>
    </section>
  );
}

function BriefRow({ item, kindLabel }: { item: BriefItem; kindLabel: string }) {
  const isExternal = item.url?.startsWith("http") ?? false;

  const body = (
    <>
      <span className="inline-flex w-fit items-center rounded-full bg-sage/15 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-sage-text uppercase">
        {kindLabel}
      </span>
      <span className="flex items-center gap-1 text-sm font-medium text-text-primary">
        {item.title}
        {isExternal ? (
          <ExternalLink className="size-3 text-teal-text" aria-hidden />
        ) : null}
      </span>
      <span className="text-[13px] leading-snug text-text-secondary">
        {item.body}
      </span>
    </>
  );

  if (item.url) {
    return (
      <li>
        <Link
          href={item.url}
          className="flex flex-col gap-1 active:scale-[0.99]"
          {...(isExternal
            ? { target: "_blank", rel: "noopener noreferrer" }
            : {})}
        >
          {body}
        </Link>
      </li>
    );
  }

  return <li className="flex flex-col gap-1">{body}</li>;
}

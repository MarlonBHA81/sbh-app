"use client";

import { ArrowLeft, CalendarClock, ExternalLink, Users } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { LiveSection } from "@/components/live/live-section";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import {
  trackSponsoredRoomClick,
  trackSponsoredRoomImpression,
} from "@/lib/ads/track";
import type { Masterclass } from "@/lib/api/types";

/** A masterclass room (ask #3/#4): branding, enrolment and the live session. */
export function MasterclassRoom({ ulid }: { ulid: string }) {
  const [m, setM] = useState<Masterclass | null>(null);
  const [missing, setMissing] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Masterclass }>(`/api/v1/masterclasses/${ulid}`)
      .then((res) => {
        if (cancelled) return;
        setM(res.data);
        if (res.data.is_sponsored) trackSponsoredRoomImpression(res.data.ulid);
      })
      .catch(() => {
        if (!cancelled) setMissing(true);
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  async function toggle() {
    if (!m || busy) return;
    setBusy(true);
    try {
      if (m.enrolled) {
        await api.del(`/api/v1/masterclasses/${ulid}/enrol`);
        setM({ ...m, enrolled: false, participants_count: m.participants_count - 1 });
        toast.success("Withdrawn");
      } else {
        const res = await api.post<{ data: Masterclass }>(
          `/api/v1/masterclasses/${ulid}/enrol`,
        );
        setM(res.data);
        toast.success("Enrolled — see you there!");
      }
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't update enrolment",
      );
    } finally {
      setBusy(false);
    }
  }

  if (missing) {
    return (
      <div className="flex flex-col gap-3">
        <BackLink />
        <p className="text-sm text-text-secondary">This room isn&apos;t available.</p>
      </div>
    );
  }

  if (!m) {
    return (
      <div className="flex flex-col gap-3">
        <BackLink />
        <Skeleton className="h-64 w-full rounded-xl" />
      </div>
    );
  }

  const accent = m.brand_color ?? undefined;

  return (
    <div className="flex flex-col gap-4">
      <BackLink />

      <section className="overflow-hidden rounded-(--radius-card) border border-warmgray bg-card shadow-card">
        {m.banner_url ? (
          <div
            className="h-32 w-full"
            style={{ background: `center/cover url(${m.banner_url})` }}
          />
        ) : (
          <div
            className="h-20 w-full"
            style={{
              background: accent
                ? `linear-gradient(135deg, ${accent}, ${m.accent_color ?? accent})`
                : "var(--color-plum)",
            }}
          />
        )}
        <div className="flex flex-col gap-2 p-4">
          <h1 className="font-heading text-xl font-semibold text-text-primary">
            {m.title}
          </h1>
          {m.facilitator_name ? (
            <span className="text-[13px] text-text-secondary">
              with {m.facilitator_name}
            </span>
          ) : null}
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-text-secondary">
            <span className="flex items-center gap-1">
              <CalendarClock className="size-3" aria-hidden />
              {new Date(m.starts_at).toLocaleDateString()} –{" "}
              {new Date(m.ends_at).toLocaleDateString()}
            </span>
            <span className="flex items-center gap-1">
              <Users className="size-3" aria-hidden />
              {m.participants_count} enrolled
              {m.seats_left !== null ? ` · ${m.seats_left} seats left` : ""}
            </span>
          </div>
          <p className="text-sm leading-relaxed whitespace-pre-wrap text-text-secondary">
            {m.description}
          </p>

          {m.is_sponsored && m.sponsor_name ? (
            <div className="flex items-center gap-2 rounded-lg bg-sage/10 px-3 py-2">
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="text-[10px] font-medium tracking-wide text-text-secondary uppercase">
                  Sponsored by
                </span>
                <span className="truncate text-[13px] font-semibold text-text-primary">
                  {m.sponsor_name}
                </span>
                {m.sponsor_blurb ? (
                  <span className="line-clamp-1 text-[11px] text-text-secondary">
                    {m.sponsor_blurb}
                  </span>
                ) : null}
              </div>
              {m.sponsor_url ? (
                <a
                  href={m.sponsor_url}
                  target="_blank"
                  rel="sponsored noopener noreferrer"
                  onClick={() => trackSponsoredRoomClick(m.ulid)}
                  className="flex shrink-0 items-center gap-1 text-[12px] font-medium text-teal-text hover:underline"
                >
                  Visit
                  <ExternalLink className="size-3.5" aria-hidden />
                </a>
              ) : null}
            </div>
          ) : null}

          <Button
            type="button"
            variant={m.enrolled ? "outline" : "default"}
            className="h-11 sm:self-start"
            disabled={busy || (!m.enrolled && m.seats_left === 0)}
            onClick={() => void toggle()}
          >
            {m.enrolled ? "Withdraw" : m.seats_left === 0 ? "Full" : "Enrol"}
          </Button>
        </div>
      </section>

      <LiveSection ulid={ulid} />
    </div>
  );
}

function BackLink() {
  return (
    <Link
      href="/masterclasses"
      className="flex w-fit items-center gap-1 text-[13px] font-medium text-teal-text hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden />
      All masterclasses
    </Link>
  );
}

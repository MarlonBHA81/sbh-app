"use client";

import {
  CalendarClock,
  ExternalLink,
  GraduationCap,
  Users,
} from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { ExternalLink as OutboundLink } from "@/components/ui/external-link";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import {
  trackSponsoredRoomClick,
  trackSponsoredRoomImpression,
} from "@/lib/ads/track";
import type { Masterclass } from "@/lib/api/types";

/** Masterclasses & cohorts (V3 · LEARN): longer programmes members enrol in. */
export function MasterclassesView() {
  const [classes, setClasses] = useState<Masterclass[] | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Masterclass[] }>("/api/v1/masterclasses")
      .then((res) => {
        if (!cancelled) setClasses(res.data);
      })
      .catch(() => {
        if (!cancelled) setClasses([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  async function toggle(m: Masterclass) {
    if (busy) return;
    setBusy(m.ulid);
    try {
      if (m.enrolled) {
        await api.del(`/api/v1/masterclasses/${m.ulid}/enrol`);
        update(m.ulid, { enrolled: false, participants_count: m.participants_count - 1 });
        toast.success("Withdrawn");
      } else {
        const res = await api.post<{ data: Masterclass }>(
          `/api/v1/masterclasses/${m.ulid}/enrol`,
        );
        update(m.ulid, res.data);
        toast.success("Enrolled — see you there!");
      }
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't update enrolment",
      );
    } finally {
      setBusy(null);
    }
  }

  function update(ulid: string, patch: Partial<Masterclass>) {
    setClasses((prev) =>
      (prev ?? []).map((c) => (c.ulid === ulid ? { ...c, ...patch } : c)),
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Masterclasses" />
      <p className="text-sm text-text-secondary">
        Longer, cohort-based programmes — learn alongside other founders.
      </p>

      {classes === null ? (
        <div className="flex flex-col gap-3">
          <Skeleton className="h-40 w-full rounded-xl" />
          <Skeleton className="h-40 w-full rounded-xl" />
        </div>
      ) : classes.length === 0 ? (
        <div className="rounded-(--radius-card) border border-warmgray bg-card p-6 text-center text-sm text-text-secondary shadow-card">
          No masterclasses are scheduled right now — check back soon.
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {classes.map((m) => (
            <RoomCard
              key={m.ulid}
              m={m}
              busy={busy === m.ulid}
              onToggle={() => void toggle(m)}
            />
          ))}
        </ul>
      )}
    </div>
  );
}

/** A masterclass card — branded when the room carries branding, with a tracked
 * sponsor strip for sponsored rooms. */
function RoomCard({
  m,
  busy,
  onToggle,
}: {
  m: Masterclass;
  busy: boolean;
  onToggle: () => void;
}) {
  const ref = useRef<HTMLLIElement | null>(null);

  // Sponsored-room impression: fire once the card is on screen.
  useEffect(() => {
    if (!m.is_sponsored) return;
    const node = ref.current;
    if (!node || typeof IntersectionObserver === "undefined") {
      trackSponsoredRoomImpression(m.ulid);
      return;
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          trackSponsoredRoomImpression(m.ulid);
          observer.disconnect();
        }
      },
      { threshold: 0.5 },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [m.is_sponsored, m.ulid]);

  const accent = m.brand_color ?? undefined;

  return (
    <li
      ref={ref}
      className="flex flex-col overflow-hidden rounded-(--radius-card) border border-warmgray bg-card shadow-card"
      style={accent ? { borderTopColor: accent, borderTopWidth: 3 } : undefined}
    >
      {m.banner_url ? (
        <div
          className="h-28 w-full"
          style={{ background: `center/cover url(${m.banner_url})` }}
        />
      ) : null}

      <div className="flex flex-col gap-3 p-4">
        <div className="flex items-start gap-2">
          <span
            className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-plum/12 text-plum"
            style={accent ? { backgroundColor: `${accent}1f`, color: accent } : undefined}
          >
            {m.logo_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={m.logo_url} alt="" className="size-full object-cover" />
            ) : (
              <GraduationCap className="size-4" aria-hidden />
            )}
          </span>
          <div className="flex flex-1 flex-col gap-1">
            <Link
              href={`/masterclasses/${m.ulid}`}
              className="font-heading text-[15px] font-semibold text-text-primary hover:underline"
            >
              {m.title}
            </Link>
            {m.facilitator_name ? (
              <span className="text-[12px] text-text-secondary">
                with {m.facilitator_name}
              </span>
            ) : null}
          </div>
          {m.status === "active" ? (
            <span className="shrink-0 rounded-full bg-sage/15 px-2 py-0.5 text-[10px] font-semibold text-sage-ink uppercase">
              Running
            </span>
          ) : null}
        </div>

        <p className="text-[13px] leading-snug text-text-secondary">
          {m.description}
        </p>

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
              <OutboundLink
                href={m.sponsor_url}
                rel="sponsored noopener noreferrer"
                onClick={() => trackSponsoredRoomClick(m.ulid)}
                className="flex shrink-0 items-center gap-1 text-[12px] font-medium text-teal-text hover:underline"
              >
                Visit
                <ExternalLink className="size-3.5" aria-hidden />
              </OutboundLink>
            ) : null}
          </div>
        ) : null}

        <Button
          type="button"
          variant={m.enrolled ? "outline" : "default"}
          className="h-11 sm:self-start"
          disabled={busy || (!m.enrolled && m.seats_left === 0)}
          onClick={onToggle}
        >
          {m.enrolled ? "Withdraw" : m.seats_left === 0 ? "Full" : "Enrol"}
        </Button>
      </div>
    </li>
  );
}

"use client";

import { CalendarClock, GraduationCap, Users } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
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
            <li
              key={m.ulid}
              className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card"
            >
              <div className="flex items-start gap-2">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-plum/12 text-plum">
                  <GraduationCap className="size-4" aria-hidden />
                </span>
                <div className="flex flex-1 flex-col gap-1">
                  <h2 className="font-heading text-[15px] font-semibold text-text-primary">
                    {m.title}
                  </h2>
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

              <Button
                type="button"
                variant={m.enrolled ? "outline" : "default"}
                className="h-11 sm:self-start"
                disabled={
                  busy === m.ulid ||
                  (!m.enrolled && m.seats_left === 0)
                }
                onClick={() => void toggle(m)}
              >
                {m.enrolled
                  ? "Withdraw"
                  : m.seats_left === 0
                    ? "Full"
                    : "Enrol"}
              </Button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

"use client";

import { CalendarDays, Clock, MapPin } from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { EventRsvp, Post } from "@/lib/api/types";

function monthDay(iso: string): { month: string; day: string } {
  const date = new Date(iso);
  return {
    month: new Intl.DateTimeFormat("en", { month: "short" }).format(date),
    day: new Intl.DateTimeFormat("en", { day: "numeric" }).format(date),
  };
}

function timeRange(startIso: string, endIso: string | null): string {
  const fmt = new Intl.DateTimeFormat("en", {
    weekday: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
  const start = fmt.format(new Date(startIso));
  if (!endIso) return start;
  const endTime = new Intl.DateTimeFormat("en", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(endIso));
  return `${start} – ${endTime}`;
}

export function EventPost({ post }: { post: Post }) {
  const event = post.event ?? null;
  const [rsvp, setRsvp] = useState<EventRsvp | null>(event?.viewer_rsvp ?? null);
  const [going, setGoing] = useState(event?.going_count ?? 0);
  const [interested, setInterested] = useState(event?.interested_count ?? 0);
  const busy = useRef(false);

  if (!event) return null;

  const { month, day } = monthDay(event.starts_at);
  const cover = post.media[0];

  async function toggle(target: EventRsvp) {
    if (busy.current) return;
    busy.current = true;
    const next: EventRsvp | null = rsvp === target ? null : target;
    const prev = { rsvp, going, interested };

    // Recompute both buckets from the transition.
    let g = going;
    let i = interested;
    if (rsvp === "going") g -= 1;
    if (rsvp === "interested") i -= 1;
    if (next === "going") g += 1;
    if (next === "interested") i += 1;
    setRsvp(next);
    setGoing(Math.max(0, g));
    setInterested(Math.max(0, i));

    try {
      await api.post(`/api/v1/posts/${post.ulid}/rsvp`, {
        status: next ?? "none",
      });
    } catch (error) {
      setRsvp(prev.rsvp);
      setGoing(prev.going);
      setInterested(prev.interested);
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't update RSVP",
      );
    } finally {
      busy.current = false;
    }
  }

  return (
    <div className="flex flex-col gap-3 overflow-hidden rounded-xl border" data-no-nav>
      {cover ? (
        <div className="bg-muted" style={{ aspectRatio: "16 / 9" }}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={cover.thumb_url}
            alt=""
            loading="lazy"
            className="size-full object-cover"
          />
        </div>
      ) : null}

      <div className="flex gap-3 px-4 pt-1">
        <div className="flex size-14 shrink-0 flex-col items-center justify-center rounded-lg border bg-card text-center">
          <span className="text-[11px] font-medium text-primary uppercase">
            {month}
          </span>
          <span className="text-xl font-bold leading-none tabular-nums">
            {day}
          </span>
        </div>
        <div className="flex min-w-0 flex-col gap-1">
          <h3 className="font-semibold leading-tight">{event.title}</h3>
          <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Clock className="size-3.5 shrink-0" aria-hidden />
            {timeRange(event.starts_at, event.ends_at)}
          </span>
          {event.venue ? (
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <MapPin className="size-3.5 shrink-0" aria-hidden />
              {event.venue}
            </span>
          ) : null}
        </div>
      </div>

      <div className="flex gap-2 px-4 pb-4">
        <Button
          type="button"
          variant={rsvp === "going" ? "default" : "outline"}
          className="h-10 flex-1 gap-1.5"
          onClick={(e) => {
            e.stopPropagation();
            void toggle("going");
          }}
        >
          <CalendarDays className="size-4" aria-hidden />
          Going
          <span className="tabular-nums opacity-70">{going}</span>
        </Button>
        <Button
          type="button"
          variant={rsvp === "interested" ? "default" : "outline"}
          className="h-10 flex-1 gap-1.5"
          onClick={(e) => {
            e.stopPropagation();
            void toggle("interested");
          }}
        >
          Interested
          <span className="tabular-nums opacity-70">{interested}</span>
        </Button>
      </div>
    </div>
  );
}

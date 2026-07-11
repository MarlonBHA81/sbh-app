"use client";

import { CalendarDays, Clock, LocateFixed, MapPin, MapPinOff } from "lucide-react";
import Link from "next/link";
import { useRef, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { PostList, type PostListHandle } from "@/components/posts/post-list";
import { PullToRefresh } from "@/components/posts/pull-to-refresh";
import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { EventRsvp, Post } from "@/lib/api/types";
import { useGeoStore } from "@/lib/stores/geo-store";
import { cn, withParam } from "@/lib/utils";

type EventFilter = "upcoming" | "past";

const FILTERS: { value: EventFilter; label: string }[] = [
  { value: "upcoming", label: "Upcoming" },
  { value: "past", label: "Past" },
];

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

/** Compact event row with optimistic RSVP; taps through to the post detail. */
function CompactEventCard({ post }: { post: Post }) {
  const event = post.event ?? null;
  const [rsvp, setRsvp] = useState<EventRsvp | null>(event?.viewer_rsvp ?? null);
  const [going, setGoing] = useState(event?.going_count ?? 0);
  const [interested, setInterested] = useState(event?.interested_count ?? 0);
  const busy = useRef(false);

  if (!event) return null;

  const { month, day } = monthDay(event.starts_at);

  async function toggle(target: EventRsvp) {
    if (busy.current) return;
    busy.current = true;
    const next: EventRsvp | null = rsvp === target ? null : target;
    const prev = { rsvp, going, interested };

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
    <Link
      href={`/p/${post.ulid}`}
      className="flex flex-col gap-3 rounded-xl border p-4 transition-colors hover:bg-accent/40"
    >
      <div className="flex gap-3">
        <div className="flex size-14 shrink-0 flex-col items-center justify-center rounded-lg border bg-card text-center">
          <span className="text-[11px] font-medium text-primary uppercase">
            {month}
          </span>
          <span className="text-xl font-bold leading-none tabular-nums">
            {day}
          </span>
        </div>
        <div className="flex min-w-0 flex-1 flex-col gap-1">
          <h3 className="font-semibold leading-tight">{event.title}</h3>
          {event.venue ? (
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <MapPin className="size-3.5 shrink-0" aria-hidden />
              <span className="truncate">{event.venue}</span>
            </span>
          ) : null}
          <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Clock className="size-3.5 shrink-0" aria-hidden />
            {timeRange(event.starts_at, event.ends_at)}
          </span>
          <span className="text-xs text-muted-foreground tabular-nums">
            {going} going · {interested} interested
          </span>
        </div>
      </div>
      <div className="flex gap-2">
        <Button
          type="button"
          variant={rsvp === "going" ? "default" : "outline"}
          size="sm"
          className="h-9 flex-1 gap-1.5"
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            void toggle("going");
          }}
        >
          <CalendarDays className="size-4" aria-hidden />
          Going
        </Button>
        <Button
          type="button"
          variant={rsvp === "interested" ? "default" : "outline"}
          size="sm"
          className="h-9 flex-1"
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            void toggle("interested");
          }}
        >
          Interested
        </Button>
      </div>
    </Link>
  );
}

export function EventsTab() {
  const [filter, setFilter] = useState<EventFilter>("upcoming");
  const [nearMe, setNearMe] = useState(false);

  const coords = useGeoStore((s) => s.coords);
  const status = useGeoStore((s) => s.status);
  const radiusKm = useGeoStore((s) => s.radiusKm);
  const requestLocation = useGeoStore((s) => s.requestLocation);

  const listRef = useRef<PostListHandle>(null);

  const geoActive = nearMe && Boolean(coords);
  let endpoint = `/api/v1/business/events?filter=${filter}`;
  if (geoActive && coords) {
    endpoint += `&lat=${coords.lat.toFixed(5)}&lng=${coords.lng.toFixed(5)}&radius_km=${radiusKm}`;
  }

  function toggleNearMe() {
    if (nearMe) {
      setNearMe(false);
      return;
    }
    if (coords) {
      setNearMe(true);
    } else {
      requestLocation();
      // Turn on so it applies once coordinates arrive.
      setNearMe(true);
    }
  }

  const awaitingLocation = nearMe && !coords && status === "requesting";
  const locationFailed =
    nearMe && !coords && (status === "denied" || status === "unavailable");

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <div
          role="tablist"
          aria-label="Event filter"
          className="flex flex-1 gap-1.5"
        >
          {FILTERS.map(({ value, label }) => {
            const active = filter === value;
            return (
              <button
                key={value}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setFilter(value)}
                className={cn(
                  "flex h-9 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors",
                  active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
                )}
              >
                {label}
              </button>
            );
          })}
        </div>
        <Button
          type="button"
          variant={geoActive ? "default" : "outline"}
          size="sm"
          className="h-9 shrink-0 gap-1.5"
          aria-pressed={geoActive}
          onClick={toggleNearMe}
        >
          <LocateFixed className="size-4" aria-hidden />
          Near me
        </Button>
      </div>

      {locationFailed ? (
        <div className="flex items-center gap-2 rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground">
          <MapPinOff className="size-4 shrink-0" aria-hidden />
          <span className="flex-1">
            Couldn&apos;t get your location. Showing all events.
          </span>
          <button
            type="button"
            className="font-medium text-foreground underline-offset-4 hover:underline"
            onClick={requestLocation}
          >
            Retry
          </button>
        </div>
      ) : null}

      <PullToRefresh
        onRefresh={() => listRef.current?.refresh() ?? Promise.resolve()}
      >
        <PostList
          ref={listRef}
          refreshKey={awaitingLocation ? "awaiting" : endpoint}
          buildUrl={(cursor) =>
            cursor ? withParam(endpoint, "cursor", cursor) : endpoint
          }
          renderItem={(post) => <CompactEventCard post={post} />}
          emptyState={
            <EmptyState
              icon={CalendarDays}
              title={
                filter === "upcoming"
                  ? "No upcoming events"
                  : "No past events"
              }
              description={
                geoActive
                  ? `No events within ${radiusKm} km. Try turning off “Near me”.`
                  : filter === "upcoming"
                    ? "Check back soon — new business events show up here."
                    : "Past events will appear here once they've happened."
              }
            />
          }
        />
      </PullToRefresh>
    </div>
  );
}

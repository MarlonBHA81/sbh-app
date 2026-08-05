"use client";

import { Lock } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import * as api from "@/lib/api/client";
import type { WellnessCheckin } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** 1..5 mood scale — supportive, non-judgemental wording. */
const MOODS: { value: number; emoji: string; label: string }[] = [
  { value: 1, emoji: "😔", label: "Finding it hard" },
  { value: 2, emoji: "😕", label: "A bit low" },
  { value: 3, emoji: "😐", label: "Okay" },
  { value: 4, emoji: "🙂", label: "Pretty good" },
  { value: 5, emoji: "😄", label: "Doing well" },
];

function moodLabel(value: number): string {
  return MOODS.find((m) => m.value === value)?.label ?? "";
}

function formatWhen(iso: string | null): string {
  if (!iso) return "";
  return new Date(iso).toLocaleDateString("en", {
    day: "numeric",
    month: "short",
  });
}

/**
 * The private "how are you doing?" check-in (V3 · BELONG). A member picks a
 * mood, optionally jots a note, and logs it — visible only to them. No reward,
 * no streak; the most recent few are shown back as a gentle record.
 */
export function CheckinPanel() {
  const [recent, setRecent] = useState<WellnessCheckin[] | null>(null);
  const [mood, setMood] = useState<number | null>(null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: WellnessCheckin[] }>("/api/v1/me/wellness/checkins")
      .then((res) => {
        if (!cancelled) setRecent(res.data);
      })
      .catch(() => {
        if (!cancelled) setRecent([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  async function submit() {
    if (mood === null || busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ data: WellnessCheckin }>(
        "/api/v1/me/wellness/checkins",
        { mood, note: note.trim() || null },
      );
      setRecent((prev) => [res.data, ...(prev ?? [])]);
      setMood(null);
      setNote("");
      toast.success("Logged — just for you.");
    } catch {
      toast.error("Couldn't save — try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-2">
        <h2 className="font-heading text-[15px] font-semibold text-text-primary">
          How are you doing today?
        </h2>
        <span className="ms-auto flex items-center gap-1 text-[11px] text-text-secondary">
          <Lock className="size-3" aria-hidden />
          Private
        </span>
      </div>

      <div className="flex justify-between gap-1">
        {MOODS.map((m) => (
          <button
            key={m.value}
            type="button"
            onClick={() => setMood(m.value)}
            aria-pressed={mood === m.value}
            aria-label={m.label}
            className={cn(
              "flex flex-1 flex-col items-center gap-1 rounded-(--radius-card) border px-1 py-2 transition-colors",
              mood === m.value
                ? "border-sage bg-sage/10"
                : "border-transparent hover:bg-accent",
            )}
          >
            <span className="text-2xl" aria-hidden>
              {m.emoji}
            </span>
          </button>
        ))}
      </div>

      {mood !== null ? (
        <div className="flex flex-col gap-2">
          <p className="text-xs text-text-secondary">{moodLabel(mood)}</p>
          <textarea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Anything you'd like to note? (optional, only you see this)"
            maxLength={2000}
            rows={3}
            className="w-full rounded-(--radius-card) border border-warmgray bg-card p-3 text-sm text-text-primary placeholder:text-text-secondary"
          />
          <Button
            type="button"
            className="h-10"
            disabled={busy}
            onClick={() => void submit()}
          >
            {busy ? "Saving…" : "Log how I'm doing"}
          </Button>
        </div>
      ) : null}

      {recent && recent.length > 0 ? (
        <div className="flex flex-col gap-2 border-t border-warmgray pt-3">
          <p className="text-xs font-medium text-text-secondary">
            Your recent check-ins
          </p>
          <ul className="flex flex-col gap-1.5">
            {recent.slice(0, 5).map((c) => (
              <li
                key={c.ulid}
                className="flex items-center gap-2 text-sm text-text-primary"
              >
                <span aria-hidden>
                  {MOODS.find((m) => m.value === c.mood)?.emoji ?? "•"}
                </span>
                <span className="text-text-secondary">{moodLabel(c.mood)}</span>
                {c.note ? (
                  <span className="truncate text-text-secondary">
                    — {c.note}
                  </span>
                ) : null}
                <span className="ms-auto shrink-0 text-xs text-text-secondary">
                  {formatWhen(c.created_at)}
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  );
}

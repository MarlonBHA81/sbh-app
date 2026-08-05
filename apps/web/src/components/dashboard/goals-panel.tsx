"use client";

import { Check, Flag, Plus, Trash2 } from "lucide-react";
import { useState } from "react";

import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as api from "@/lib/api/client";
import type { Goal } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Honest due-date framing — no invented urgency. */
function dueLabel(dueOn: string | null): string | null {
  if (!dueOn) return null;
  const due = new Date(`${dueOn}T23:59:59`);
  const today = new Date();
  const days = Math.ceil(
    (due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
  );
  if (days < 0) return "Past your date";
  if (days === 0) return "Due today";
  if (days === 1) return "Due tomorrow";
  if (days <= 30) return `Due in ${days} days`;
  return `Due ${due.toLocaleDateString("en", { day: "numeric", month: "short" })}`;
}

function GoalRow({
  goal,
  onToggle,
  onDelete,
}: {
  goal: Goal;
  onToggle: (goal: Goal) => void;
  onDelete: (goal: Goal) => void;
}) {
  const due = dueLabel(goal.due_on);
  return (
    <li className="flex items-start gap-3 py-3">
      <button
        type="button"
        onClick={() => onToggle(goal)}
        aria-pressed={goal.is_done}
        aria-label={goal.is_done ? "Mark as not done" : "Mark as reached"}
        className={cn(
          "mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border transition-colors",
          goal.is_done
            ? "border-teal bg-teal text-white"
            : "border-warmgray text-transparent hover:border-teal",
        )}
      >
        <Check className="size-3.5" aria-hidden />
      </button>
      <div className="min-w-0 flex-1">
        <p
          className={cn(
            "text-sm font-medium text-text-primary",
            goal.is_done && "text-text-secondary line-through",
          )}
        >
          {goal.title}
        </p>
        <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-text-secondary">
          {goal.target ? <span>{goal.target}</span> : null}
          {due ? <span>{due}</span> : null}
        </div>
      </div>
      <button
        type="button"
        onClick={() => onDelete(goal)}
        aria-label="Remove goal"
        className="flex size-8 shrink-0 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-accent hover:text-plum"
      >
        <Trash2 className="size-4" aria-hidden />
      </button>
    </li>
  );
}

/**
 * The goals list on the dashboard (V3 · PROGRESS). Add a goal, mark it reached
 * (which the backend rewards with XP once), or remove it. Optimistic updates;
 * the parent owns the goal array so the stat tiles stay in sync.
 */
export function GoalsPanel({
  goals,
  onGoalsChange,
  onError,
}: {
  goals: Goal[];
  onGoalsChange: (goals: Goal[]) => void;
  onError: () => void;
}) {
  const [title, setTitle] = useState("");
  const [target, setTarget] = useState("");
  const [dueOn, setDueOn] = useState("");
  const [adding, setAdding] = useState(false);
  const [busy, setBusy] = useState(false);

  async function addGoal() {
    const trimmed = title.trim();
    if (!trimmed || busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ data: Goal }>("/api/v1/me/goals", {
        title: trimmed,
        target: target.trim() || null,
        due_on: dueOn || null,
      });
      onGoalsChange([res.data, ...goals]);
      setTitle("");
      setTarget("");
      setDueOn("");
      setAdding(false);
    } catch {
      onError();
    } finally {
      setBusy(false);
    }
  }

  async function toggle(goal: Goal) {
    const next = !goal.is_done;
    // Optimistic.
    onGoalsChange(
      goals.map((g) => (g.ulid === goal.ulid ? { ...g, is_done: next } : g)),
    );
    try {
      const res = await api.patch<{ data: Goal }>(
        `/api/v1/goals/${goal.ulid}`,
        { is_done: next },
      );
      onGoalsChange(goals.map((g) => (g.ulid === goal.ulid ? res.data : g)));
    } catch {
      onGoalsChange(goals); // revert
      onError();
    }
  }

  async function remove(goal: Goal) {
    const previous = goals;
    onGoalsChange(goals.filter((g) => g.ulid !== goal.ulid));
    try {
      await api.del(`/api/v1/goals/${goal.ulid}`);
    } catch {
      onGoalsChange(previous); // revert
      onError();
    }
  }

  return (
    <section className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-2">
        <span className="flex size-8 items-center justify-center rounded-full bg-teal/12 text-teal-text">
          <Flag className="size-4" aria-hidden />
        </span>
        <h2 className="font-heading text-[15px] font-semibold text-text-primary">
          Your goals
        </h2>
        {!adding ? (
          <button
            type="button"
            onClick={() => setAdding(true)}
            className="ms-auto flex items-center gap-1 rounded-full bg-teal px-3 py-1.5 text-[13px] font-medium text-white transition-colors hover:bg-teal/90"
          >
            <Plus className="size-4" aria-hidden />
            Add a goal
          </button>
        ) : null}
      </div>

      {adding ? (
        <div className="flex flex-col gap-2 rounded-(--radius-card) bg-accent/40 p-3">
          <Input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="What do you want to reach?"
            maxLength={255}
            autoFocus
          />
          <Input
            value={target}
            onChange={(e) => setTarget(e.target.value)}
            placeholder="Optional detail (e.g. 20 new customers)"
            maxLength={255}
          />
          <label className="flex items-center gap-2 text-xs text-text-secondary">
            Target date (optional)
            <input
              type="date"
              value={dueOn}
              onChange={(e) => setDueOn(e.target.value)}
              className="rounded-md border border-warmgray bg-card px-2 py-1 text-sm text-text-primary"
            />
          </label>
          <div className="flex gap-2">
            <Button
              type="button"
              className="h-10 flex-1"
              disabled={busy || !title.trim()}
              onClick={() => void addGoal()}
            >
              {busy ? "Saving…" : "Add this goal"}
            </Button>
            <Button
              type="button"
              variant="outline"
              className="h-10"
              onClick={() => {
                setAdding(false);
                setTitle("");
                setTarget("");
                setDueOn("");
              }}
            >
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      {goals.length === 0 && !adding ? (
        <EmptyState
          icon={Flag}
          title="Set your first goal"
          description="Name something you're working toward. You'll see it here and can tick it off when you reach it."
        />
      ) : (
        <ul className="flex flex-col divide-y divide-warmgray">
          {goals.map((goal) => (
            <GoalRow
              key={goal.ulid}
              goal={goal}
              onToggle={(g) => void toggle(g)}
              onDelete={(g) => void remove(g)}
            />
          ))}
        </ul>
      )}
    </section>
  );
}

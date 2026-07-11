"use client";

import {
  ChevronDown,
  Pencil,
  Plus,
  Sparkles,
  Trash2,
} from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { BusinessNeedDialog } from "@/components/business/business-need-dialog";
import { CategoryChip } from "@/components/business/category-chip";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import * as api from "@/lib/api/client";
import type { BusinessNeed, BusinessNeedKind } from "@/lib/api/types";
import { cn } from "@/lib/utils";

const MAX_ACTIVE = 10;

const GROUPS: { kind: BusinessNeedKind; label: string }[] = [
  { kind: "offering", label: "Offering" },
  { kind: "seeking", label: "Seeking" },
];

function NeedRow({
  need,
  onToggleActive,
  onEdit,
  onDelete,
}: {
  need: BusinessNeed;
  onToggleActive: (need: BusinessNeed) => void;
  onEdit: (need: BusinessNeed) => void;
  onDelete: (need: BusinessNeed) => void;
}) {
  return (
    <div
      className={cn(
        "flex flex-col gap-2 rounded-lg border p-3",
        !need.active && "opacity-60",
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <CategoryChip category={need.category} />
        <div className="flex items-center gap-1">
          <Switch
            checked={need.active}
            onCheckedChange={() => onToggleActive(need)}
            aria-label={need.active ? "Deactivate need" : "Activate need"}
          />
        </div>
      </div>
      <p className="text-sm leading-snug">{need.description}</p>
      <div className="flex justify-end gap-1">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 gap-1.5 text-xs text-muted-foreground"
          onClick={() => onEdit(need)}
        >
          <Pencil className="size-3.5" aria-hidden />
          Edit
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-destructive"
          onClick={() => onDelete(need)}
        >
          <Trash2 className="size-3.5" aria-hidden />
          Delete
        </Button>
      </div>
    </div>
  );
}

/**
 * Collapsible "Your needs" manager. Notifies the parent (via onChanged) after
 * any mutation so match results can be re-fetched.
 */
export function NeedsManager({ onChanged }: { onChanged?: () => void }) {
  const [needs, setNeeds] = useState<BusinessNeed[]>([]);
  const [phase, setPhase] = useState<"loading" | "loaded" | "error">("loading");
  const [attempt, setAttempt] = useState(0);
  const [open, setOpen] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<BusinessNeed | null>(null);
  const [pendingDelete, setPendingDelete] = useState<BusinessNeed | null>(null);
  const [deleteBusy, setDeleteBusy] = useState(false);
  const busyToggle = useState(() => new Set<string>())[0];

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: BusinessNeed[] }>("/api/v1/me/business-needs")
      .then((res) => {
        if (!cancelled) {
          setNeeds(res.data);
          setPhase("loaded");
        }
      })
      .catch(() => {
        if (!cancelled) setPhase("error");
      });
    return () => {
      cancelled = true;
    };
  }, [attempt]);

  const activeCount = needs.filter((n) => n.active).length;

  function upsertNeed(next: BusinessNeed) {
    setNeeds((prev) => {
      const exists = prev.some((n) => n.ulid === next.ulid);
      return exists
        ? prev.map((n) => (n.ulid === next.ulid ? next : n))
        : [next, ...prev];
    });
    onChanged?.();
  }

  async function toggleActive(need: BusinessNeed) {
    if (busyToggle.has(need.ulid)) return;
    busyToggle.add(need.ulid);
    const next = !need.active;
    setNeeds((prev) =>
      prev.map((n) => (n.ulid === need.ulid ? { ...n, active: next } : n)),
    );
    try {
      const res = await api.patch<{ data: BusinessNeed }>(
        `/api/v1/me/business-needs/${need.ulid}`,
        { active: next },
      );
      setNeeds((prev) =>
        prev.map((n) => (n.ulid === need.ulid ? res.data : n)),
      );
      onChanged?.();
    } catch (error) {
      setNeeds((prev) =>
        prev.map((n) =>
          n.ulid === need.ulid ? { ...n, active: need.active } : n,
        ),
      );
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update the need",
      );
    } finally {
      busyToggle.delete(need.ulid);
    }
  }

  async function confirmDelete() {
    if (!pendingDelete || deleteBusy) return;
    setDeleteBusy(true);
    const target = pendingDelete;
    try {
      await api.del(`/api/v1/me/business-needs/${target.ulid}`);
      setNeeds((prev) => prev.filter((n) => n.ulid !== target.ulid));
      setPendingDelete(null);
      onChanged?.();
      toast.success("Need deleted");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't delete",
      );
    } finally {
      setDeleteBusy(false);
    }
  }

  return (
    <section className="rounded-xl border">
      <Collapsible open={open} onOpenChange={setOpen}>
        <CollapsibleTrigger asChild>
          <button
            type="button"
            className="flex w-full items-center gap-2 px-4 py-3 text-left"
          >
            <Sparkles className="size-4 shrink-0 text-primary" aria-hidden />
            <span className="flex-1 text-sm font-semibold">Your needs</span>
            {phase === "loaded" ? (
              <span className="text-xs text-muted-foreground tabular-nums">
                {activeCount}/{MAX_ACTIVE} active
              </span>
            ) : null}
            <ChevronDown
              className={cn(
                "size-4 shrink-0 text-muted-foreground transition-transform",
                open && "rotate-180",
              )}
              aria-hidden
            />
          </button>
        </CollapsibleTrigger>
        <CollapsibleContent className="flex flex-col gap-3 border-t px-4 py-3">
          {phase === "loading" ? (
            <div className="flex flex-col gap-2">
              <Skeleton className="h-20 w-full rounded-lg" />
              <Skeleton className="h-20 w-full rounded-lg" />
            </div>
          ) : phase === "error" ? (
            <div className="py-4 text-center text-sm text-muted-foreground">
              Couldn&apos;t load your needs.{" "}
              <button
                type="button"
                className="font-medium text-foreground underline-offset-4 hover:underline"
                onClick={() => {
                  setPhase("loading");
                  setAttempt((n) => n + 1);
                }}
              >
                Try again
              </button>
            </div>
          ) : needs.length === 0 ? (
            <p className="py-2 text-sm text-muted-foreground">
              Add what you offer and what you&apos;re looking for to start
              getting matched.
            </p>
          ) : (
            GROUPS.map(({ kind, label }) => {
              const group = needs.filter((n) => n.kind === kind);
              if (group.length === 0) return null;
              return (
                <div key={kind} className="flex flex-col gap-2">
                  <h3 className="text-xs font-semibold text-muted-foreground uppercase">
                    {label}
                  </h3>
                  {group.map((need) => (
                    <NeedRow
                      key={need.ulid}
                      need={need}
                      onToggleActive={toggleActive}
                      onEdit={(n) => {
                        setEditing(n);
                        setDialogOpen(true);
                      }}
                      onDelete={setPendingDelete}
                    />
                  ))}
                </div>
              );
            })
          )}

          <div className="flex items-center justify-between gap-2 pt-1">
            <span className="text-xs text-muted-foreground">
              Up to {MAX_ACTIVE} active needs.
            </span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="h-9 gap-1.5"
              onClick={() => {
                setEditing(null);
                setDialogOpen(true);
              }}
            >
              <Plus className="size-4" aria-hidden />
              Add need
            </Button>
          </div>
        </CollapsibleContent>
      </Collapsible>

      <BusinessNeedDialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        need={editing}
        onSaved={upsertNeed}
      />

      <AlertDialog
        open={pendingDelete !== null}
        onOpenChange={(next) => {
          if (!next) setPendingDelete(null);
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this need?</AlertDialogTitle>
            <AlertDialogDescription>
              This need will be removed and no longer used for matchmaking. You
              can add it again later.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11">Cancel</AlertDialogCancel>
            <AlertDialogAction
              className="h-11 bg-destructive text-white hover:bg-destructive/90"
              disabled={deleteBusy}
              onClick={(event) => {
                event.preventDefault();
                void confirmDelete();
              }}
            >
              {deleteBusy ? "Deleting…" : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  );
}

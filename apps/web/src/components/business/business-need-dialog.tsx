"use client";

import { CircleAlert } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useBusinessCategories } from "@/hooks/use-business-categories";
import * as api from "@/lib/api/client";
import type { BusinessNeed, BusinessNeedKind } from "@/lib/api/types";
import { cn } from "@/lib/utils";

const KINDS: { value: BusinessNeedKind; label: string; hint: string }[] = [
  { value: "offering", label: "Offering", hint: "Something you provide" },
  { value: "seeking", label: "Seeking", hint: "Something you're looking for" },
];

const MAX_DESCRIPTION = 500;

export function BusinessNeedDialog({
  open,
  onOpenChange,
  need,
  onSaved,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present when editing an existing need; absent when adding. */
  need?: BusinessNeed | null;
  onSaved: (need: BusinessNeed) => void;
}) {
  const { categories, phase: catPhase } = useBusinessCategories();
  const [kind, setKind] = useState<BusinessNeedKind>("offering");
  const [categoryId, setCategoryId] = useState<string>("");
  const [description, setDescription] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const editing = Boolean(need);

  // Reset the form to the target need (or blank) when the dialog opens or its
  // target changes — done during render (React's endorsed pattern) rather than
  // in an effect to avoid a cascading render.
  const targetKey = open ? (need ? `edit:${need.ulid}` : "new") : "closed";
  const [syncedKey, setSyncedKey] = useState<string | null>(null);
  if (open && syncedKey !== targetKey) {
    setSyncedKey(targetKey);
    setKind(need?.kind ?? "offering");
    setCategoryId(need ? String(need.category.id) : "");
    setDescription(need?.description ?? "");
    setError(null);
    setBusy(false);
  }

  async function onSubmit() {
    if (busy) return;
    setError(null);
    if (!categoryId) {
      setError("Pick a category.");
      return;
    }
    if (!description.trim()) {
      setError("Add a short description.");
      return;
    }
    setBusy(true);
    const body = {
      kind,
      business_category_id: Number(categoryId),
      description: description.trim(),
    };
    try {
      const res = need
        ? await api.patch<{ data: BusinessNeed }>(
            `/api/v1/me/business-needs/${need.ulid}`,
            body,
          )
        : await api.post<{ data: BusinessNeed }>(
            "/api/v1/me/business-needs",
            body,
          );
      onSaved(res.data);
      toast.success(editing ? "Need updated" : "Need added");
      onOpenChange(false);
    } catch (err) {
      setBusy(false);
      if (err instanceof api.ApiError) {
        const fieldError = err.errors
          ? Object.values(err.errors)[0]?.[0]
          : undefined;
        setError(fieldError ?? err.message);
      } else {
        setError("Couldn't save your need. Please try again.");
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{editing ? "Edit need" : "Add a need"}</DialogTitle>
          <DialogDescription>
            Tell us what you offer or what you&apos;re looking for so we can
            match you with other businesses.
          </DialogDescription>
        </DialogHeader>

        {error ? (
          <Alert variant="destructive">
            <CircleAlert />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        <div className="flex flex-col gap-4">
          {/* Kind segmented control */}
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium">Type</span>
            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Need type">
              {KINDS.map((k) => {
                const active = kind === k.value;
                return (
                  <button
                    key={k.value}
                    type="button"
                    role="radio"
                    aria-checked={active}
                    onClick={() => setKind(k.value)}
                    className={cn(
                      "flex flex-col items-start gap-0.5 rounded-lg border p-3 text-left transition-colors",
                      active
                        ? "border-primary bg-accent/50"
                        : "hover:bg-accent/40",
                    )}
                  >
                    <span className="text-sm font-medium">{k.label}</span>
                    <span className="text-xs text-muted-foreground">
                      {k.hint}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Category */}
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium">Category</span>
            <Select value={categoryId} onValueChange={setCategoryId}>
              <SelectTrigger className="h-11 w-full">
                <SelectValue
                  placeholder={
                    catPhase === "loading"
                      ? "Loading categories…"
                      : "Pick a category"
                  }
                />
              </SelectTrigger>
              <SelectContent>
                {categories.map((cat) => (
                  <SelectItem key={cat.id} value={String(cat.id)}>
                    <span className="flex items-center gap-1.5">
                      <span aria-hidden>{cat.icon ?? "🏢"}</span>
                      {cat.name}
                    </span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Description */}
          <div className="flex flex-col gap-1.5">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium">Description</span>
              <span className="text-xs text-muted-foreground tabular-nums">
                {description.length}/{MAX_DESCRIPTION}
              </span>
            </div>
            <Textarea
              value={description}
              onChange={(e) =>
                setDescription(e.target.value.slice(0, MAX_DESCRIPTION))
              }
              maxLength={MAX_DESCRIPTION}
              rows={4}
              placeholder={
                kind === "offering"
                  ? "e.g. We roast and wholesale specialty coffee beans."
                  : "e.g. Looking for a local supplier of compostable cups."
              }
            />
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            className="h-11 w-full sm:w-auto"
            disabled={busy}
            onClick={() => void onSubmit()}
          >
            {busy ? "Saving…" : editing ? "Save changes" : "Add need"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

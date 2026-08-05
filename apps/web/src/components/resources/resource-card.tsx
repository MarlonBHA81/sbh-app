"use client";

import { Bookmark, ExternalLink, Tag } from "lucide-react";

import { ExternalLink as OutboundLink } from "@/components/ui/external-link";
import { useState } from "react";
import { toast } from "sonner";

import * as api from "@/lib/api/client";
import type { LibraryResource } from "@/lib/api/types";
import { resourceCategoryLabel, resourceTypeLabel } from "@/lib/resources";
import { cn } from "@/lib/utils";

/**
 * A single resource in the library (V2 · LEARN). The card opens the external
 * link; the bookmark toggle is optimistic and mirrors the opportunities save.
 */
export function ResourceCard({
  resource,
  onSavedChange,
}: {
  resource: LibraryResource;
  /** Notify the list (e.g. so the Saved tab can drop an unsaved item). */
  onSavedChange?: (ulid: string, saved: boolean) => void;
}) {
  const [saved, setSaved] = useState(resource.is_saved);
  const [busy, setBusy] = useState(false);

  async function toggleSave() {
    if (busy) return;
    setBusy(true);
    const next = !saved;
    setSaved(next); // optimistic
    try {
      if (next) {
        await api.post(`/api/v1/resources/${resource.ulid}/save`);
      } else {
        await api.del(`/api/v1/resources/${resource.ulid}/save`);
      }
      onSavedChange?.(resource.ulid, next);
    } catch {
      setSaved(!next); // revert
      toast.error("Couldn't update — try again.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <article className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-start gap-2">
        <span className="rounded-full bg-teal/12 px-2.5 py-0.5 text-[11px] font-medium text-teal-text">
          {resourceTypeLabel(resource.type)}
        </span>
        <span className="flex items-center gap-1 text-[11px] text-text-secondary">
          <Tag className="size-3" aria-hidden />
          {resourceCategoryLabel(resource.category)}
        </span>
        <button
          type="button"
          onClick={toggleSave}
          disabled={busy}
          aria-pressed={saved}
          aria-label={saved ? "Saved — tap to remove" : "Save"}
          className="ms-auto flex size-9 shrink-0 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-accent active:scale-[0.98]"
        >
          <Bookmark
            className={cn("size-5", saved && "fill-teal text-teal")}
            aria-hidden
          />
        </button>
      </div>

      <OutboundLink href={resource.url} className="flex flex-col gap-2">
        <h3 className="font-heading text-[16px] leading-snug font-semibold text-text-primary">
          {resource.title}
        </h3>
        <p className="line-clamp-3 text-sm text-text-secondary">
          {resource.description}
        </p>
        <span className="flex items-center gap-1 text-[13px] font-medium text-teal-text">
          Open resource
          <ExternalLink className="size-3.5" aria-hidden />
        </span>
      </OutboundLink>
    </article>
  );
}

"use client";

import { Search } from "lucide-react";

import { Button } from "@/components/ui/button";
import { useSearchStore } from "@/lib/stores/search-store";

/**
 * Opens the global search palette. `variant="icon"` is a round icon button for
 * the mobile top bar; `variant="bar"` is a faux search input for the desktop
 * sidebar (with a ⌘K hint).
 */
export function SearchTrigger({ variant = "icon" }: { variant?: "icon" | "bar" }) {
  const setOpen = useSearchStore((s) => s.setOpen);

  if (variant === "bar") {
    return (
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="flex h-10 w-full items-center gap-2 rounded-lg border bg-muted/40 px-3 text-sm text-muted-foreground transition-colors hover:bg-accent/60"
      >
        <Search className="size-4 shrink-0" aria-hidden />
        <span className="flex-1 text-left">Search</span>
        <kbd className="pointer-events-none hidden rounded border bg-background px-1.5 font-mono text-[10px] font-medium text-muted-foreground lg:inline-block">
          ⌘K
        </kbd>
      </button>
    );
  }

  return (
    <Button
      variant="ghost"
      size="icon"
      className="size-11 rounded-full"
      aria-label="Search"
      onClick={() => setOpen(true)}
    >
      <Search className="size-5" aria-hidden />
    </Button>
  );
}

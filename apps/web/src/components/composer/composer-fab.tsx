"use client";

import { Plus } from "lucide-react";

import { Button } from "@/components/ui/button";

import { useComposer } from "./composer-provider";

/** Mobile floating action button, above the bottom nav. */
export function ComposerFab() {
  const { openComposer } = useComposer();

  return (
    <Button
      type="button"
      size="icon"
      aria-label="New post"
      onClick={() => openComposer()}
      className="fixed right-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-40 size-14 rounded-full shadow-lg md:hidden"
    >
      <Plus className="size-6" aria-hidden />
    </Button>
  );
}

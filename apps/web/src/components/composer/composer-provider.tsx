"use client";

import dynamic from "next/dynamic";
import { createContext, useCallback, useContext, useMemo, useState } from "react";

import type { Post } from "@/lib/api/types";

// Loaded on demand so the composer (and the cropper it pulls in) stays out
// of the initial bundle.
const Composer = dynamic(
  () => import("./composer").then((mod) => mod.Composer),
  { ssr: false },
);

export interface ComposerOpenOptions {
  /** Open prefilled as a quote of this post. */
  quoteParent?: Post;
  /** Open prefilled to edit this draft/scheduled post. */
  post?: Post;
}

interface ComposerContextValue {
  openComposer: (options?: ComposerOpenOptions) => void;
  /** Bumps after any successful post create/update — use as a refresh key. */
  mutationCount: number;
  /** Notify lists that posts changed outside the composer (e.g. repost). */
  notifyPostsMutated: () => void;
}

const ComposerContext = createContext<ComposerContextValue | null>(null);

export function useComposer(): ComposerContextValue {
  const context = useContext(ComposerContext);
  if (!context) {
    throw new Error("useComposer must be used within <ComposerProvider>");
  }
  return context;
}

export function ComposerProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const [options, setOptions] = useState<ComposerOpenOptions>({});
  // Remount the composer each time it opens so state resets cleanly.
  const [instance, setInstance] = useState(0);
  const [mutationCount, setMutationCount] = useState(0);

  const openComposer = useCallback((next: ComposerOpenOptions = {}) => {
    setOptions(next);
    setInstance((i) => i + 1);
    setOpen(true);
  }, []);

  const notifyPostsMutated = useCallback(() => {
    setMutationCount((count) => count + 1);
  }, []);

  const value = useMemo(
    () => ({ openComposer, mutationCount, notifyPostsMutated }),
    [openComposer, mutationCount, notifyPostsMutated],
  );

  return (
    <ComposerContext.Provider value={value}>
      {children}
      {instance > 0 ? (
        <Composer
          key={instance}
          open={open}
          onOpenChange={setOpen}
          quoteParent={options.quoteParent ?? null}
          editPost={options.post ?? null}
          onSaved={notifyPostsMutated}
        />
      ) : null}
    </ComposerContext.Provider>
  );
}

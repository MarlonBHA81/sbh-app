"use client";

import { useEffect, useState } from "react";

import * as api from "@/lib/api/client";
import type { BusinessCategory } from "@/lib/api/types";

/** Module-level cache — categories are a small, rarely-changing dataset. */
let cache: BusinessCategory[] | null = null;
let inflight: Promise<BusinessCategory[]> | null = null;

function load(): Promise<BusinessCategory[]> {
  if (cache) return Promise.resolve(cache);
  inflight ??= api
    .get<{ data: BusinessCategory[] }>("/api/v1/business/categories")
    .then((res) => {
      cache = res.data;
      return res.data;
    })
    .finally(() => {
      inflight = null;
    });
  return inflight;
}

export interface BusinessCategoriesState {
  categories: BusinessCategory[];
  phase: "loading" | "loaded" | "error";
  retry: () => void;
}

/** Fetches (and caches) the business category list. */
export function useBusinessCategories(): BusinessCategoriesState {
  const [categories, setCategories] = useState<BusinessCategory[]>(
    cache ?? [],
  );
  const [phase, setPhase] = useState<"loading" | "loaded" | "error">(
    cache ? "loaded" : "loading",
  );
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let cancelled = false;
    // load() resolves on a microtask (even for the cached case), so these
    // setState calls stay out of the synchronous effect body.
    load()
      .then((data) => {
        if (!cancelled) {
          setCategories(data);
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

  return {
    categories,
    phase,
    retry: () => {
      setPhase("loading");
      setAttempt((n) => n + 1);
    },
  };
}

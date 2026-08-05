"use client";

import { Sparkles } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useRef, useState } from "react";

import * as api from "@/lib/api/client";
import type { PostTopic, Topic } from "@/lib/api/types";

const MIN_CHARS = 50;
const DEBOUNCE_MS = 2000;

function flatten(topics: Topic[]): PostTopic[] {
  return topics.flatMap((topic) => [
    { id: topic.id, slug: topic.slug, name: topic.name, icon: topic.icon },
    ...flatten(topic.children ?? []),
  ]);
}

/**
 * AI-assisted topic suggestions. When the post has enough text and no topics
 * are picked yet, debounces a call to `/posts/suggest-topics` and renders a
 * subtle chip row of matched topics. Stays silently absent when the AI driver
 * is disabled (the endpoint returns an empty slug list).
 */
export function SuggestedTopics({
  text,
  selected,
  onSelect,
}: {
  text: string;
  selected: PostTopic[];
  onSelect: (topic: PostTopic) => void;
}) {
  const t = useTranslations("composer");
  const [suggestions, setSuggestions] = useState<PostTopic[]>([]);
  // Cache the flattened topic tree so slugs can be resolved to full topics.
  const treeRef = useRef<PostTopic[] | null>(null);

  const trimmed = text.trim();
  const active = selected.length === 0 && trimmed.length >= MIN_CHARS;

  useEffect(() => {
    // When inactive, nothing is fetched and the render guard below hides any
    // previously-fetched chips — no synchronous state update needed here.
    if (!active) return;

    let cancelled = false;
    const handle = window.setTimeout(async () => {
      try {
        const res = await api.post<{ data: { slugs: string[] } }>(
          "/api/v1/posts/suggest-topics",
          { text: trimmed },
        );
        const slugs = res.data?.slugs ?? [];
        if (cancelled || slugs.length === 0) {
          if (!cancelled) setSuggestions([]);
          return;
        }
        // Resolve slugs against the topic tree (loaded lazily, once).
        if (!treeRef.current) {
          const tree = await api.get<{ data: Topic[] }>("/api/v1/topics/tree");
          if (cancelled) return;
          treeRef.current = flatten(tree.data);
        }
        const bySlug = new Map(treeRef.current.map((topic) => [topic.slug, topic]));
        const matched = slugs
          .map((slug) => bySlug.get(slug))
          .filter((topic): topic is PostTopic => Boolean(topic));
        if (!cancelled) setSuggestions(matched);
      } catch {
        // Fail silent — suggestions are a non-essential enhancement.
        if (!cancelled) setSuggestions([]);
      }
    }, DEBOUNCE_MS);

    return () => {
      cancelled = true;
      window.clearTimeout(handle);
    };
  }, [active, trimmed]);

  if (!active || suggestions.length === 0) return null;

  return (
    <div className="flex flex-col gap-1.5">
      <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Sparkles className="size-3.5" aria-hidden />
        {t("suggestedTopics")}
      </p>
      <div className="flex flex-wrap gap-1.5">
        {suggestions.map((topic) => (
          <button
            key={topic.id}
            type="button"
            onClick={() => {
              onSelect(topic);
              setSuggestions((prev) => prev.filter((s) => s.id !== topic.id));
            }}
            className="inline-flex items-center gap-1 rounded-full border border-dashed bg-muted/40 px-2.5 py-1 text-xs transition-colors hover:border-solid hover:bg-accent"
          >
            {topic.icon ? <span aria-hidden>{topic.icon}</span> : null}
            {topic.name}
          </button>
        ))}
      </div>
    </div>
  );
}

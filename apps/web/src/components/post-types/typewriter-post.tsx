"use client";

import { useEffect, useRef, useState } from "react";

import type { Post, TypewriterPayload, TypewriterSpeed } from "@/lib/api/types";

const SPEED_MS: Record<TypewriterSpeed, number> = {
  slow: 110,
  normal: 55,
  fast: 22,
};

function prefersReducedMotion(): boolean {
  return (
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  );
}

/**
 * Character-by-character reveal. Replays each time the post scrolls into
 * view; renders instantly when the user prefers reduced motion. The full
 * text is always laid out (unrevealed chars at opacity 0) so there's no CLS.
 */
export function TypewriterPost({ post }: { post: Post }) {
  const payload = (post.payload ?? {}) as unknown as TypewriterPayload;
  const text = payload.text ?? "";
  const intervalMs = SPEED_MS[payload.speed ?? "normal"] ?? SPEED_MS.normal;

  const containerRef = useRef<HTMLDivElement | null>(null);
  const [visibleCount, setVisibleCount] = useState(() =>
    prefersReducedMotion() ? text.length : 0,
  );

  const chars = Array.from(text);

  useEffect(() => {
    if (prefersReducedMotion() || chars.length === 0) return;

    const node = containerRef.current;
    if (!node) return;

    let timer: ReturnType<typeof setInterval> | null = null;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          // Replay from the start every time the post enters the viewport.
          if (timer) clearInterval(timer);
          setVisibleCount(0);
          timer = setInterval(() => {
            setVisibleCount((count) => {
              if (count >= chars.length) {
                if (timer) clearInterval(timer);
                return count;
              }
              return count + 1;
            });
          }, intervalMs);
        } else if (timer) {
          clearInterval(timer);
          timer = null;
        }
      },
      { threshold: 0.4 },
    );

    observer.observe(node);
    return () => {
      observer.disconnect();
      if (timer) clearInterval(timer);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [text, intervalMs]);

  return (
    <div ref={containerRef}>
      <p
        className="font-mono text-[15px] leading-relaxed break-words whitespace-pre-wrap"
        aria-label={text}
      >
        {chars.map((char, i) => (
          <span
            key={i}
            aria-hidden
            className={i < visibleCount ? "opacity-100" : "opacity-0"}
          >
            {char}
          </span>
        ))}
        <span
          aria-hidden
          className={
            visibleCount < chars.length
              ? "animate-pulse text-primary"
              : "opacity-0"
          }
        >
          ▌
        </span>
      </p>
    </div>
  );
}

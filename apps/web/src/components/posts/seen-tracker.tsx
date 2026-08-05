"use client";

import { useEffect, useRef } from "react";

import { markSeen } from "@/lib/views";

/**
 * Wraps a feed item and counts it as viewed once it has been ≥50% visible for
 * a continuous second (Milestone 11). One IntersectionObserver per card; it
 * disconnects after firing so it costs nothing for the rest of the session.
 */
export function SeenTracker({
  ulid,
  children,
}: {
  ulid: string;
  children: React.ReactNode;
}) {
  const ref = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const node = ref.current;
    if (!node || typeof IntersectionObserver === "undefined") return;

    let dwell: ReturnType<typeof setTimeout> | null = null;
    const observer = new IntersectionObserver(
      ([entry]) => {
        const visible = entry.isIntersecting && entry.intersectionRatio >= 0.5;
        if (visible && !dwell) {
          dwell = setTimeout(() => {
            markSeen(ulid);
            observer.disconnect();
          }, 1000);
        } else if (!visible && dwell) {
          clearTimeout(dwell);
          dwell = null;
        }
      },
      { threshold: [0, 0.5, 1] },
    );
    observer.observe(node);

    return () => {
      if (dwell) clearTimeout(dwell);
      observer.disconnect();
    };
  }, [ulid]);

  return <div ref={ref}>{children}</div>;
}

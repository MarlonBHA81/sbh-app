import { cn } from "@/lib/utils";

/**
 * The SBH swirl rings as a decorative watermark (brand manual texture).
 * Rendered in white at low opacity — the parent controls placement/size
 * and must be `overflow-hidden`. Decorative only, hidden from a11y tree.
 */
export function SwirlWatermark({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 100 100"
      aria-hidden
      className={cn("pointer-events-none absolute text-white", className)}
    >
      <g fill="none" stroke="currentColor" strokeLinecap="round">
        <circle
          cx="50"
          cy="50"
          r="46"
          strokeWidth="3"
          strokeDasharray="231 58"
          transform="rotate(-70 50 50)"
        />
        <circle
          cx="50"
          cy="50"
          r="40"
          strokeWidth="6"
          strokeDasharray="211 40"
          transform="rotate(105 50 50)"
          opacity="0.8"
        />
        <circle
          cx="50"
          cy="50"
          r="33"
          strokeWidth="2.5"
          strokeDasharray="162 45"
          transform="rotate(20 50 50)"
        />
      </g>
    </svg>
  );
}

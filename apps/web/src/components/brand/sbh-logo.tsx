import { cn } from "@/lib/utils";

/**
 * SBH Community brand mark — the enso-style swirl with the lowercase
 * "sbh" wordmark inside, recreated as SVG from the brand manual.
 *
 * variant "mark": transparent background, teal strokes (header/nav use).
 * variant "badge": filled teal disc with cream text (splash/launcher use).
 */
export function SbhMark({
  variant = "mark",
  className,
}: {
  variant?: "mark" | "badge";
  className?: string;
}) {
  const badge = variant === "badge";
  const ink = badge ? "#f5f1e8" : "#4e8a88";
  const swirlOuter = badge ? "#a9c6c4" : "#a9c6c4";
  const swirlInner = badge ? "#f5f1e8" : "#4e8a88";

  return (
    <svg
      viewBox="0 0 100 100"
      role="img"
      aria-label="SBH Community"
      className={cn("shrink-0", className)}
    >
      {badge ? <circle cx="50" cy="50" r="48" fill="#4e8a88" /> : null}
      <g fill="none" strokeLinecap="round">
        {/* Outer brush swirl — two offset incomplete rings. */}
        <circle
          cx="50"
          cy="50"
          r={badge ? 40 : 46}
          stroke={swirlOuter}
          strokeWidth={badge ? 3 : 3.5}
          strokeDasharray={badge ? "215 36" : "245 44"}
          transform="rotate(-55 50 50)"
        />
        <circle
          cx="50"
          cy="50"
          r={badge ? 35 : 40}
          stroke={swirlOuter}
          strokeWidth={badge ? 5.5 : 6}
          strokeDasharray={badge ? "180 40" : "205 46"}
          transform="rotate(115 50 50)"
          opacity="0.75"
        />
        {/* Inner accent arc. */}
        <circle
          cx="50"
          cy="50"
          r={badge ? 29 : 33}
          stroke={swirlInner}
          strokeWidth={badge ? 2.5 : 3}
          strokeDasharray={badge ? "150 32" : "170 37"}
          transform="rotate(40 50 50)"
          opacity={badge ? 0.9 : 0.8}
        />
      </g>
      <text
        x="50"
        y="51.5"
        textAnchor="middle"
        dominantBaseline="central"
        fontSize="27"
        fontWeight="600"
        letterSpacing="-0.5"
        fill={ink}
        style={{ fontFamily: "var(--font-geist-sans), system-ui, sans-serif" }}
      >
        sbh
      </text>
    </svg>
  );
}

/**
 * Horizontal lockup mirroring the primary logo from the brand manual:
 * swirl mark (which carries "sbh") followed by the italic "Community App"
 * brand text, so the lockup reads "SBH Community App".
 */
export function SbhLogo({
  markClassName,
  textClassName,
  className,
}: {
  markClassName?: string;
  textClassName?: string;
  className?: string;
}) {
  return (
    <span className={cn("flex items-center gap-2", className)}>
      <SbhMark className={cn("size-9", markClassName)} />
      <span
        className={cn(
          "font-semibold italic tracking-tight text-primary",
          textClassName,
        )}
      >
        Community App
      </span>
    </span>
  );
}

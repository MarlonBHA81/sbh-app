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

  return (
    <svg
      viewBox="0 0 100 100"
      role="img"
      aria-label="SBH Community"
      className={cn("shrink-0", className)}
    >
      {badge ? <circle cx="50" cy="50" r="48" fill="#4e8a88" /> : null}
      <g fill="none" strokeLinecap="round">
        {badge ? (
          <>
            {/* Badge: thin pale swirls over the teal disc (brand badge). */}
            <circle
              cx="50"
              cy="50"
              r="44"
              stroke="#dce9e8"
              strokeWidth="1.5"
              strokeDasharray="221 55"
              transform="rotate(-70 50 50)"
              opacity="0.85"
            />
            <circle
              cx="50"
              cy="50"
              r="38.5"
              stroke="#a9c6c4"
              strokeWidth="3.6"
              strokeDasharray="203 39"
              transform="rotate(105 50 50)"
              opacity="0.75"
            />
            <circle
              cx="50"
              cy="50"
              r="31.5"
              stroke="#e9f0ef"
              strokeWidth="1.4"
              strokeDasharray="154 44"
              transform="rotate(20 50 50)"
              opacity="0.9"
            />
          </>
        ) : (
          <>
            {/* Transparent mark: sage swirls with a teal accent arc. */}
            <circle
              cx="50"
              cy="50"
              r="46"
              stroke="#a9c6c4"
              strokeWidth="3.5"
              strokeDasharray="245 44"
              transform="rotate(-55 50 50)"
            />
            <circle
              cx="50"
              cy="50"
              r="40"
              stroke="#a9c6c4"
              strokeWidth="6"
              strokeDasharray="205 46"
              transform="rotate(115 50 50)"
              opacity="0.75"
            />
            <circle
              cx="50"
              cy="50"
              r="33"
              stroke="#4e8a88"
              strokeWidth="3"
              strokeDasharray="170 37"
              transform="rotate(40 50 50)"
              opacity="0.8"
            />
          </>
        )}
      </g>
      <text
        x="50"
        y="51.5"
        textAnchor="middle"
        dominantBaseline="central"
        fontSize={badge ? 25 : 27}
        fontWeight={badge ? 500 : 600}
        letterSpacing="-0.5"
        fill={badge ? "#f7f4ec" : "#4e8a88"}
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

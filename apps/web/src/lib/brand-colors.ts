/**
 * SBH brand palette offered when a new member builds their profile
 * (UX pattern 4 — IKEA effect). Hex values mirror the design tokens
 * (design-tokens.css) so the picker and live preview match the app.
 */
export interface BrandColor {
  key: string;
  label: string;
  hex: string;
}

export const BRAND_COLORS: BrandColor[] = [
  { key: "teal", label: "Teal", hex: "#4e8a88" },
  { key: "plum", label: "Plum", hex: "#683f59" },
  { key: "sage", label: "Sage", hex: "#5d7868" },
  { key: "slate", label: "Slate", hex: "#484851" },
];

export const DEFAULT_BRAND_COLOR = BRAND_COLORS[0];

export function brandColorByKey(key: string | undefined): BrandColor {
  return BRAND_COLORS.find((c) => c.key === key) ?? DEFAULT_BRAND_COLOR;
}

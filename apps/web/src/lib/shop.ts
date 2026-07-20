import type { ProductType } from "@/lib/api/types";

/** Format a minor-unit price (cents) as a currency string, or "Free". */
export function formatPrice(
  cents: number | null | undefined,
  currency = "ZAR",
): string {
  if (cents == null) return "Free";
  const amount = (cents / 100).toFixed(2);
  return `${currency} ${amount}`;
}

const PRODUCT_TYPE_LABELS: Record<ProductType, string> = {
  digital_download: "Download",
  course: "Course",
  service: "Service",
};

export function productTypeLabel(type: ProductType): string {
  return PRODUCT_TYPE_LABELS[type] ?? type;
}

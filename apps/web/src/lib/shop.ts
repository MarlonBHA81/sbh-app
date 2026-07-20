import * as api from "@/lib/api/client";
import type { CheckoutRedirect, ProductType } from "@/lib/api/types";

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

/**
 * Start a checkout: create a pending order, then redirect the browser to
 * PayFast by POSTing the signed field set as a self-submitting form (PayFast
 * requires a form POST, so we can't just navigate).
 */
export async function startCheckout(
  productUlid: string,
  bumpUlids: string[] = [],
): Promise<void> {
  const res = await api.post<{ data: CheckoutRedirect }>(
    "/api/v1/shop/checkout",
    { product_ulid: productUlid, bump_ulids: bumpUlids },
  );
  submitPayFast(res.data.process_url, res.data.fields);
}

/**
 * Download a purchased product's file. Fetched (not a plain anchor) so the
 * active-profile header matches the buying context, then saved via a blob URL.
 */
export async function downloadPurchase(
  productUlid: string,
  filename: string,
): Promise<void> {
  const headers: Record<string, string> = { Accept: "application/octet-stream" };
  const profileId = api.getActiveProfileId();
  if (profileId) headers["X-Profile-Id"] = profileId;

  const res = await fetch(
    `${api.API_URL}/api/v1/me/purchases/${productUlid}/download`,
    { credentials: "include", headers },
  );
  if (!res.ok) throw new Error("Download failed");

  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

/** Build a hidden form for the PayFast fields and submit it (full redirect). */
function submitPayFast(
  processUrl: string,
  fields: Record<string, string>,
): void {
  const form = document.createElement("form");
  form.method = "POST";
  form.action = processUrl;
  form.style.display = "none";

  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }

  document.body.appendChild(form);
  form.submit();
}

import * as api from "@/lib/api/client";
import type {
  CheckoutQuote,
  CheckoutRedirect,
  ProductType,
} from "@/lib/api/types";

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

/** Fire-and-forget shop view tracking (store + products) for vendor analytics. */
export function trackShopView(target: {
  store?: string;
  products?: string[];
}): void {
  api.post("/api/v1/shop/seen", target).catch(() => {
    // View tracking must never surface an error to the shopper.
  });
}

/** Enrol free in a R0 product (grants the entitlement without PayFast). */
export async function enrolFree(productUlid: string): Promise<void> {
  await api.post(`/api/v1/shop/products/${productUlid}/enrol`);
}

/**
 * Fetch a gated deliverable's raw text (e.g. an HTML tool) with the active
 * profile header, so it can be rendered in a sandboxed iframe.
 */
export async function fetchOwnedFile(productUlid: string): Promise<string> {
  const headers: Record<string, string> = {};
  const profileId = api.getActiveProfileId();
  if (profileId) headers["X-Profile-Id"] = profileId;

  const res = await fetch(
    `${api.API_URL}/api/v1/me/purchases/${productUlid}/download`,
    { credentials: "include", headers },
  );
  if (!res.ok) throw new Error("Could not load this tool");
  return res.text();
}

/**
 * Start a checkout: create a pending order, then redirect the browser to
 * PayFast by POSTing the signed field set as a self-submitting form (PayFast
 * requires a form POST, so we can't just navigate).
 */
export async function startCheckout(
  productUlid: string,
  bumpUlids: string[] = [],
  couponCode?: string,
): Promise<void> {
  const res = await api.post<{ data: CheckoutRedirect }>(
    "/api/v1/shop/checkout",
    {
      product_ulid: productUlid,
      bump_ulids: bumpUlids,
      ...(couponCode ? { coupon_code: couponCode } : {}),
    },
  );
  submitPayFast(res.data.process_url, res.data.fields);
}

/** Preview a checkout total (sale prices, coupon, VAT) without ordering. */
export async function quoteCheckout(input: {
  productUlid: string;
  bumpUlids?: string[];
  couponCode?: string;
}): Promise<CheckoutQuote> {
  const res = await api.post<{ data: CheckoutQuote }>(
    "/api/v1/shop/checkout/quote",
    {
      product_ulid: input.productUlid,
      bump_ulids: input.bumpUlids ?? [],
      ...(input.couponCode ? { coupon_code: input.couponCode } : {}),
    },
  );
  return res.data;
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

/** Download a course lesson's gated attachment via an authenticated fetch. */
export async function downloadLessonAttachment(
  lessonUlid: string,
  filename: string,
): Promise<void> {
  const headers: Record<string, string> = { Accept: "application/octet-stream" };
  const profileId = api.getActiveProfileId();
  if (profileId) headers["X-Profile-Id"] = profileId;

  const res = await fetch(
    `${api.API_URL}/api/v1/me/courses/lessons/${lessonUlid}/attachment`,
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

/** Turn a YouTube/Vimeo watch URL into an embeddable src, else null. */
export function videoEmbedUrl(url: string | null): string | null {
  if (!url) return null;
  const yt = url.match(
    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{11})/,
  );
  if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
  const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`;
  return null;
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

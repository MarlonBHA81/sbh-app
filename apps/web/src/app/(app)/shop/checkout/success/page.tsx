import type { Metadata } from "next";

import { CheckoutResultView } from "@/components/shop/checkout-result-view";

export const metadata: Metadata = { title: "Payment" };

export default async function CheckoutSuccessPage({
  searchParams,
}: {
  searchParams: Promise<{ order?: string }>;
}) {
  const { order } = await searchParams;
  return <CheckoutResultView outcome="success" orderUlid={order ?? null} />;
}

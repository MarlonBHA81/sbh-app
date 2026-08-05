import type { Metadata } from "next";

import { CheckoutResultView } from "@/components/shop/checkout-result-view";

export const metadata: Metadata = { title: "Checkout cancelled" };

export default function CheckoutCancelPage() {
  return <CheckoutResultView outcome="cancel" orderUlid={null} />;
}

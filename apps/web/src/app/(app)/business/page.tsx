import type { Metadata } from "next";
import { Suspense } from "react";

import { BusinessHub } from "./business-hub";

export const metadata: Metadata = { title: "Business" };

export default function BusinessPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Business hub</h1>
      <Suspense fallback={null}>
        <BusinessHub />
      </Suspense>
    </div>
  );
}

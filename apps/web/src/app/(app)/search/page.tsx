import type { Metadata } from "next";
import { Suspense } from "react";

import { SearchView } from "./search-view";

export const metadata: Metadata = { title: "Search" };

export default function SearchPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="sr-only">Search</h1>
      <Suspense fallback={null}>
        <SearchView />
      </Suspense>
    </div>
  );
}

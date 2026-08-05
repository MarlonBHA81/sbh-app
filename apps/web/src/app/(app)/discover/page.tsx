import type { Metadata } from "next";

import { DiscoverView } from "./discover-view";

export const metadata: Metadata = { title: "Discover" };

export default function DiscoverPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Discover</h1>
      <DiscoverView />
    </div>
  );
}

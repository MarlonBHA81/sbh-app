import type { Metadata } from "next";

import { DraftsView } from "./drafts-view";

export const metadata: Metadata = { title: "Drafts & scheduled" };

export default function DraftsPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">
        Drafts &amp; scheduled
      </h1>
      <DraftsView />
    </div>
  );
}

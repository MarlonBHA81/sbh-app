import type { Metadata } from "next";

import { FeedsView } from "./feeds-view";

export const metadata: Metadata = { title: "Feeds" };

export default function FeedsPage() {
  return <FeedsView />;
}

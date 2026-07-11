import { Compass } from "lucide-react";
import type { Metadata } from "next";

import { EmptyState } from "@/components/empty-state";

export const metadata: Metadata = { title: "Discover" };

export default function DiscoverPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Discover</h1>
      <EmptyState
        icon={Compass}
        title="Discover is coming soon"
        description="Search and explore businesses and creators near you."
      />
    </div>
  );
}

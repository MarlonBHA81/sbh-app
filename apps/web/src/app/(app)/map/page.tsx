import type { Metadata } from "next";

import { MapView } from "./map-view";

export const metadata: Metadata = { title: "Nearby map" };

export default function MapPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Nearby map</h1>
      <MapView />
    </div>
  );
}

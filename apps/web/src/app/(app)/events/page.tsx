import type { Metadata } from "next";

import { EventsView } from "./events-view";

export const metadata: Metadata = { title: "Events" };

export default function EventsPage() {
  return <EventsView />;
}

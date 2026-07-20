import type { Metadata } from "next";

import { MasterclassesView } from "@/components/masterclasses/masterclasses-view";

export const metadata: Metadata = { title: "Masterclasses" };

export default function MasterclassesPage() {
  return <MasterclassesView />;
}

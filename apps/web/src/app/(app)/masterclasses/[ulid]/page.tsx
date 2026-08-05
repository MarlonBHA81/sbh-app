import type { Metadata } from "next";

import { MasterclassRoom } from "@/components/masterclasses/masterclass-room";

export const metadata: Metadata = { title: "Masterclass room" };

export default async function MasterclassRoomPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <MasterclassRoom ulid={ulid} />;
}

import type { Metadata } from "next";

import { ChatView } from "@/components/messages/chat-view";

export const metadata: Metadata = { title: "Conversation" };

export default async function ConversationPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  // Key by ulid so navigating between conversations remounts with fresh state.
  return <ChatView key={ulid} ulid={ulid} />;
}

import type { Metadata } from "next";

import { ConversationList } from "./conversation-list";

export const metadata: Metadata = { title: "Messages" };

export default function MessagesPage() {
  return <ConversationList />;
}

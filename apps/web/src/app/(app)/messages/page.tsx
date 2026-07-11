import { MessageCircle } from "lucide-react";
import type { Metadata } from "next";

import { EmptyState } from "@/components/empty-state";

export const metadata: Metadata = { title: "Messages" };

export default function MessagesPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Messages</h1>
      <EmptyState
        icon={MessageCircle}
        title="Messages are coming soon"
        description="Direct conversations with customers and other businesses."
      />
    </div>
  );
}

import { Bell } from "lucide-react";
import type { Metadata } from "next";

import { EmptyState } from "@/components/empty-state";

export const metadata: Metadata = { title: "Notifications" };

export default function NotificationsPage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Notifications</h1>
      <EmptyState
        icon={Bell}
        title="No notifications yet"
        description="Follows, mentions and activity on your posts will show up here."
      />
    </div>
  );
}

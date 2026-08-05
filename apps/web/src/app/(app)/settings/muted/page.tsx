"use client";

import { ArrowLeft } from "lucide-react";
import Link from "next/link";

import { ProfileModerationList } from "@/components/safety/profile-moderation-list";
import { Button } from "@/components/ui/button";

export default function MutedAccountsPage() {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Button
          variant="ghost"
          size="icon"
          className="size-10 rounded-full"
          asChild
        >
          <Link href="/settings/profile" aria-label="Back to settings">
            <ArrowLeft className="size-5" aria-hidden />
          </Link>
        </Button>
        <h1 className="text-xl font-semibold tracking-tight">Muted accounts</h1>
      </div>
      <ProfileModerationList kind="mute" />
    </div>
  );
}

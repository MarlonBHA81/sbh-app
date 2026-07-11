"use client";

import { useEffect, useState } from "react";
import { toast } from "sonner";

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { useClientValue } from "@/hooks/use-client-value";
import {
  isPushSubscribed,
  isPushSupported,
  pushPermission,
  subscribeToPush,
  unsubscribeFromPush,
} from "@/lib/push";

/** Push notification opt-in switch for the profile settings page. */
export function PushSettings() {
  const supported = useClientValue(isPushSupported, true);
  const [blocked, setBlocked] = useState(false);
  const permissionBlocked = useClientValue(
    () => pushPermission() === "denied",
    false,
  );
  const [enabled, setEnabled] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void isPushSubscribed().then(setEnabled);
  }, []);

  const isBlocked = blocked || permissionBlocked;

  async function toggle(next: boolean) {
    setBusy(true);
    try {
      if (next) {
        const result = await subscribeToPush();
        if (result === "subscribed") {
          setEnabled(true);
          toast.success("Push notifications enabled");
        } else if (result === "denied") {
          setBlocked(true);
          toast.error("Notifications are blocked in your browser settings");
        } else if (result === "disabled") {
          toast.error("Push notifications aren't available right now");
        } else if (result !== "unsupported") {
          toast.error("Couldn't enable push notifications");
        }
      } else {
        await unsubscribeFromPush();
        setEnabled(false);
        toast.success("Push notifications turned off");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Notifications</CardTitle>
        <CardDescription>
          Get push alerts for follows, mentions and activity on your posts.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="flex min-h-11 items-center justify-between gap-4 rounded-lg border p-4">
          <div className="space-y-0.5">
            <p className="text-sm font-medium">Push notifications</p>
            <p className="text-sm text-muted-foreground">
              {!supported
                ? "This browser doesn't support push notifications."
                : isBlocked
                  ? "Blocked — enable notifications in your browser settings."
                  : "Delivered to this device, even when SBH is closed."}
            </p>
          </div>
          <Switch
            checked={enabled}
            disabled={!supported || isBlocked || busy}
            onCheckedChange={(next) => void toggle(next)}
            aria-label="Push notifications"
          />
        </div>
      </CardContent>
    </Card>
  );
}

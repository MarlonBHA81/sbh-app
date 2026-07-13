"use client";

import { ArrowLeft, Bell } from "lucide-react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";

import { HeaderIconButton } from "@/components/home/home-header";
import { useNotificationsStore } from "@/lib/stores/notifications-store";

/**
 * Reskin screen header: 40px circular back button, centered 18px Poppins
 * SemiBold title, and a right-side slot (defaults to the bell button with
 * an unread dot).
 */
export function ScreenHeader({
  title,
  right,
}: {
  title: string;
  right?: React.ReactNode;
}) {
  const router = useRouter();
  const tn = useTranslations("nav");
  const unread = useNotificationsStore((s) => s.unreadCount);

  return (
    <div className="relative flex items-center justify-between gap-3">
      <HeaderIconButton label="Back" onClick={() => router.back()}>
        <ArrowLeft className="size-5" aria-hidden />
      </HeaderIconButton>
      <h1 className="absolute inset-x-12 text-center font-heading text-lg font-semibold text-text-primary">
        <span className="mx-auto block truncate">{title}</span>
      </h1>
      {right ?? (
        <HeaderIconButton
          label={tn("notifications")}
          href="/notifications"
          showDot={unread > 0}
        >
          <Bell className="size-5" aria-hidden />
        </HeaderIconButton>
      )}
    </div>
  );
}

"use client";

import {
  Check,
  LogOut,
  Moon,
  Plus,
  Settings,
  Sun,
} from "lucide-react";
import { useRouter } from "next/navigation";
import { useTheme } from "next-themes";
import { useState } from "react";
import { toast } from "sonner";

import { ProfileAvatar } from "@/components/profile-avatar";
import { CreateBusinessProfileDialog } from "@/components/shell/create-business-profile-dialog";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

export function AccountSwitcher({
  children,
  align = "end",
}: {
  children: React.ReactNode;
  align?: "start" | "center" | "end";
}) {
  const profiles = useAuthStore((s) => s.profiles);
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const switchProfile = useAuthStore((s) => s.switchProfile);
  const logout = useAuthStore((s) => s.logout);
  const router = useRouter();
  const { resolvedTheme, setTheme } = useTheme();
  const [createOpen, setCreateOpen] = useState(false);

  function handleSwitch(ulid: string) {
    if (ulid === activeProfile?.ulid) return;
    switchProfile(ulid);
    const next = profiles.find((p) => p.ulid === ulid);
    if (next) toast.success(`Switched to ${next.name}`);
  }

  async function handleLogout() {
    await logout();
    router.replace("/login");
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>{children}</DropdownMenuTrigger>
        <DropdownMenuContent align={align} className="w-72">
          <DropdownMenuLabel>Accounts</DropdownMenuLabel>
          {profiles.map((profile) => (
            <DropdownMenuItem
              key={profile.ulid}
              className="min-h-11 gap-3"
              onSelect={() => handleSwitch(profile.ulid)}
            >
              <ProfileAvatar profile={profile} />
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">
                  {profile.name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                  @{profile.handle}
                </span>
              </span>
              <Badge
                variant={profile.kind === "business" ? "default" : "secondary"}
                className="shrink-0 text-[10px]"
              >
                {profile.kind === "business" ? "Business" : "Personal"}
              </Badge>
              {profile.ulid === activeProfile?.ulid ? (
                <Check className="size-4 shrink-0" aria-hidden />
              ) : null}
            </DropdownMenuItem>
          ))}
          <DropdownMenuSeparator />
          <DropdownMenuItem
            className="min-h-11 gap-3"
            onSelect={() => setCreateOpen(true)}
          >
            <Plus className="size-4" aria-hidden />
            Create business profile
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            className="min-h-11 gap-3"
            onSelect={() => router.push("/settings/profile")}
          >
            <Settings className="size-4" aria-hidden />
            Profile settings
          </DropdownMenuItem>
          <DropdownMenuItem
            className="min-h-11 gap-3"
            onSelect={(event) => {
              event.preventDefault();
              setTheme(resolvedTheme === "dark" ? "light" : "dark");
            }}
          >
            {resolvedTheme === "dark" ? (
              <Sun className="size-4" aria-hidden />
            ) : (
              <Moon className="size-4" aria-hidden />
            )}
            {resolvedTheme === "dark" ? "Light mode" : "Dark mode"}
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            variant="destructive"
            className="min-h-11 gap-3"
            onSelect={() => void handleLogout()}
          >
            <LogOut className="size-4" aria-hidden />
            Log out
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <CreateBusinessProfileDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
      />
    </>
  );
}

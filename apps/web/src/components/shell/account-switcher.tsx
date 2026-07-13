"use client";

import {
  Check,
  FileClock,
  Gauge,
  LogOut,
  Monitor,
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
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Switch } from "@/components/ui/switch";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useSettingsStore } from "@/lib/stores/settings-store";

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
  const { theme, resolvedTheme, setTheme } = useTheme();
  const lowData = useSettingsStore((s) => s.lowData);
  const setLowData = useSettingsStore((s) => s.setLowData);
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
            onSelect={() => router.push("/drafts")}
          >
            <FileClock className="size-4" aria-hidden />
            Drafts &amp; scheduled
          </DropdownMenuItem>
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
              setLowData(!lowData);
            }}
          >
            <Gauge className="size-4" aria-hidden />
            Low data mode
            <Switch
              checked={lowData}
              aria-hidden
              tabIndex={-1}
              className="pointer-events-none ms-auto"
            />
          </DropdownMenuItem>
          <DropdownMenuSub>
            <DropdownMenuSubTrigger className="min-h-11 gap-3">
              {resolvedTheme === "dark" ? (
                <Moon className="size-4 text-muted-foreground" aria-hidden />
              ) : (
                <Sun className="size-4 text-muted-foreground" aria-hidden />
              )}
              Appearance
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
              <DropdownMenuRadioGroup
                value={theme ?? "system"}
                onValueChange={setTheme}
              >
                <DropdownMenuRadioItem value="light" className="min-h-11 gap-3">
                  <Sun className="size-4" aria-hidden />
                  Light
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="dark" className="min-h-11 gap-3">
                  <Moon className="size-4" aria-hidden />
                  Dark
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem
                  value="system"
                  className="min-h-11 gap-3"
                >
                  <Monitor className="size-4" aria-hidden />
                  System
                </DropdownMenuRadioItem>
              </DropdownMenuRadioGroup>
            </DropdownMenuSubContent>
          </DropdownMenuSub>
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

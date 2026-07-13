import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import type { Profile } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

/**
 * Kind-colored ring so the active identity reads at a glance across the app:
 * teal = personal, plum = business. Used on the avatar of whichever profile
 * the user is currently acting as.
 */
const RING: Record<NonNullable<Profile["kind"]>, string> = {
  personal: "ring-2 ring-teal ring-offset-2 ring-offset-background",
  business: "ring-2 ring-plum ring-offset-2 ring-offset-background",
};

export function ProfileAvatar({
  profile,
  className,
  ring,
}: {
  profile: (Pick<Profile, "name" | "avatar_url"> & { kind?: Profile["kind"] }) | null;
  className?: string;
  /** Show the kind-colored active-identity ring. */
  ring?: boolean;
}) {
  return (
    <Avatar
      className={cn(
        "size-8",
        ring && profile?.kind ? RING[profile.kind] : undefined,
        className,
      )}
    >
      {profile?.avatar_url ? (
        <AvatarImage src={profile.avatar_url} alt={profile.name} />
      ) : null}
      <AvatarFallback className="text-xs font-medium">
        {profile ? initials(profile.name) : "?"}
      </AvatarFallback>
    </Avatar>
  );
}

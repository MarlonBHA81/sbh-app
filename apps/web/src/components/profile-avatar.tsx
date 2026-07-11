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

export function ProfileAvatar({
  profile,
  className,
}: {
  profile: Pick<Profile, "name" | "avatar_url"> | null;
  className?: string;
}) {
  return (
    <Avatar className={cn("size-8", className)}>
      {profile?.avatar_url ? (
        <AvatarImage src={profile.avatar_url} alt={profile.name} />
      ) : null}
      <AvatarFallback className="text-xs font-medium">
        {profile ? initials(profile.name) : "?"}
      </AvatarFallback>
    </Avatar>
  );
}

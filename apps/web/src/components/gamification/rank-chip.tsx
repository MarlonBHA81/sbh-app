import { Badge } from "@/components/ui/badge";
import type { Profile, ProfileBadge } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** The profile's rank badge, if it has one. */
export function findRankBadge(profile: Profile): ProfileBadge | null {
  return profile.badges?.find((badge) => badge.kind === "rank") ?? null;
}

/** A compact rank chip: rank icon + name. */
export function RankChip({
  badge,
  className,
}: {
  badge: ProfileBadge;
  className?: string;
}) {
  return (
    <Badge variant="secondary" className={cn("gap-1", className)}>
      {badge.icon ? (
        <span aria-hidden className="text-sm leading-none">
          {badge.icon}
        </span>
      ) : null}
      {badge.name}
    </Badge>
  );
}

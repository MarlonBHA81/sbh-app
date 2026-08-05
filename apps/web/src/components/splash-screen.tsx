import { SbhMark } from "@/components/brand/sbh-logo";
import { Skeleton } from "@/components/ui/skeleton";

export function SplashScreen() {
  return (
    <div className="fixed inset-0 flex flex-col items-center justify-center gap-6 bg-background">
      <SbhMark variant="badge" className="size-20" />
      <div className="flex w-40 flex-col items-center gap-2">
        <Skeleton className="h-2 w-full rounded-full" />
        <Skeleton className="h-2 w-24 rounded-full" />
      </div>
    </div>
  );
}

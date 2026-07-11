import { Skeleton } from "@/components/ui/skeleton";

export function SplashScreen() {
  return (
    <div className="fixed inset-0 flex flex-col items-center justify-center gap-6 bg-background">
      <div className="flex size-16 items-center justify-center rounded-2xl bg-primary text-2xl font-bold text-primary-foreground">
        SBH
      </div>
      <div className="flex w-40 flex-col items-center gap-2">
        <Skeleton className="h-2 w-full rounded-full" />
        <Skeleton className="h-2 w-24 rounded-full" />
      </div>
    </div>
  );
}

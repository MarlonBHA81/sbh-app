import type { BusinessCategory } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Small icon + name chip for a business category. */
export function CategoryChip({
  category,
  className,
}: {
  category: BusinessCategory;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex w-fit max-w-full items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground",
        className,
      )}
    >
      <span aria-hidden>{category.icon ?? "🏢"}</span>
      <span className="truncate">{category.name}</span>
    </span>
  );
}

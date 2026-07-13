import Link from "next/link";

/** "Quick Access ······ View all" section header row (reskin spec). */
export function SectionHeader({
  title,
  viewAllHref,
  viewAllLabel,
}: {
  title: string;
  viewAllHref?: string;
  viewAllLabel?: string;
}) {
  return (
    <div className="flex items-baseline justify-between gap-3">
      <h2 className="font-heading text-base font-semibold text-text-primary">
        {title}
      </h2>
      {viewAllHref && viewAllLabel ? (
        <Link
          href={viewAllHref}
          className="font-heading text-[13px] font-medium text-teal-text transition-opacity hover:opacity-80"
        >
          {viewAllLabel}
        </Link>
      ) : null}
    </div>
  );
}

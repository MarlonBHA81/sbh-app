/** Shared typographic primitives for the policy pages. */

export function PolicyTitle({ children, updated }: { children: React.ReactNode; updated: string }) {
  return (
    <header className="flex flex-col gap-1">
      <h1 className="font-heading text-2xl font-semibold text-text-primary">{children}</h1>
      <p className="text-xs text-text-secondary">Last updated: {updated}</p>
    </header>
  );
}

export function H2({ children }: { children: React.ReactNode }) {
  return <h2 className="mt-4 font-heading text-lg font-semibold text-text-primary">{children}</h2>;
}

export function P({ children }: { children: React.ReactNode }) {
  return <p className="text-sm leading-relaxed text-text-primary">{children}</p>;
}

export function UL({ children }: { children: React.ReactNode }) {
  return <ul className="flex list-disc flex-col gap-1 ps-5 text-sm leading-relaxed text-text-primary">{children}</ul>;
}

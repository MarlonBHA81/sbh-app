import Link from "next/link";

import { SbhLogo } from "@/components/brand/sbh-logo";

/** Public chrome for the legal/policy pages (privacy, cookies, terms). */
export default function LegalLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-warmgray bg-background/90 px-5 backdrop-blur">
        <Link href="/" aria-label="SBH Community — home">
          <SbhLogo markClassName="size-8" textClassName="text-base" />
        </Link>
        <Link
          href="/login"
          className="flex h-10 items-center rounded-full bg-teal px-4 font-heading text-[13px] font-medium text-white transition-colors hover:bg-teal-tint"
        >
          Sign in
        </Link>
      </header>
      <main className="mx-auto w-full max-w-2xl flex-1 px-5 py-8">
        <article className="prose-legal flex flex-col gap-4">{children}</article>
      </main>
      <footer className="border-t border-warmgray px-5 py-6 text-center text-xs text-text-secondary">
        <nav className="flex flex-wrap justify-center gap-x-4 gap-y-1">
          <Link href="/legal/privacy" className="hover:underline">Privacy Policy</Link>
          <Link href="/legal/cookies" className="hover:underline">Cookie Policy</Link>
          <Link href="/legal/terms" className="hover:underline">Terms of Service</Link>
        </nav>
        <p className="mt-3">© {new Date().getFullYear()} SBH Community App</p>
      </footer>
    </div>
  );
}

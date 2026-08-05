"use client";

import Link from "next/link";

import { SbhLogo } from "@/components/brand/sbh-logo";
import { ComposerProvider } from "@/components/composer/composer-provider";

/**
 * Chrome for logged-out visitors on public (crawlable) pages: brand header
 * with sign-in/join actions, no app navigation.
 */
export function GuestShell({ children }: { children: React.ReactNode }) {
  // ComposerProvider satisfies useComposer() in shared page components; the
  // composer itself is dynamically imported and never loads for guests.
  return (
    <ComposerProvider>
    <div className="flex min-h-dvh flex-col">
      <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-warmgray bg-background/90 px-5 backdrop-blur">
        <Link href="/" aria-label="SBH Community — home">
          <SbhLogo markClassName="size-8" textClassName="text-base" />
        </Link>
        <div className="flex items-center gap-2">
          <Link
            href="/login"
            className="flex h-10 items-center rounded-full px-4 font-heading text-[13px] font-medium text-text-primary transition-colors hover:bg-accent active:scale-[0.98]"
          >
            Sign in
          </Link>
          <Link
            href="/register"
            className="flex h-10 items-center rounded-full bg-teal px-4 font-heading text-[13px] font-medium text-white transition-colors hover:bg-teal-tint active:scale-[0.98]"
          >
            Join
          </Link>
        </div>
      </header>
      <main className="mx-auto w-full max-w-xl flex-1 px-5 py-4 md:py-6">
        {children}
      </main>
    </div>
    </ComposerProvider>
  );
}

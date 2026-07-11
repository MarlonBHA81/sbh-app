import type { Metadata } from "next";

import { HomeFeed } from "@/components/posts/home-feed";

export const metadata: Metadata = { title: "Home" };

export default function HomePage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Home</h1>
      <HomeFeed />
    </div>
  );
}

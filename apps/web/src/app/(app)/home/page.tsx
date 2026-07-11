import type { Metadata } from "next";

import { HomeFeed } from "@/components/posts/home-feed";

export const metadata: Metadata = { title: "Home" };

export default function HomePage() {
  return (
    <div className="flex flex-col gap-2">
      <h1 className="sr-only">Home</h1>
      <HomeFeed />
    </div>
  );
}

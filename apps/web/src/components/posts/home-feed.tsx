"use client";

import { Newspaper } from "lucide-react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { Button } from "@/components/ui/button";

import { PostList } from "./post-list";

/**
 * Interim home feed: the viewer's own published posts.
 * Follower feeds arrive in a later milestone.
 */
export function HomeFeed() {
  const { openComposer, mutationCount } = useComposer();

  return (
    <PostList
      buildUrl={(cursor) =>
        `/api/v1/me/posts?status=published${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ""}`
      }
      refreshKey={mutationCount}
      emptyState={
        <EmptyState
          icon={Newspaper}
          title="Nothing here yet"
          description="Follow people to see more — feeds arrive soon. In the meantime, your own posts show up here."
        >
          <Button className="mt-2 h-11" onClick={() => openComposer()}>
            Write your first post
          </Button>
        </EmptyState>
      }
    />
  );
}

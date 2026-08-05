"use client";

import { PartyPopper } from "lucide-react";
import { useRef } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { PostList, type PostListHandle } from "@/components/posts/post-list";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { withParam } from "@/lib/utils";

/** Wins / success stories (V2 · BELONG): a feed of celebrated wins. */
export function WinsView() {
  const { mutationCount, openComposer } = useComposer();
  const listRef = useRef<PostListHandle>(null);
  const endpoint = "/api/v1/feeds/wins";

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Wins" />
      <p className="text-sm text-text-secondary">
        Real milestones from the community — cheer someone on.
      </p>
      <PostList
        ref={listRef}
        buildUrl={(cursor) =>
          cursor ? withParam(endpoint, "cursor", cursor) : endpoint
        }
        refreshKey={mutationCount}
        emptyState={
          <EmptyState
            icon={PartyPopper}
            title="No wins yet"
            description="Landed a customer, hit a goal, learned something big? Share it and inspire the community."
          >
            <Button className="mt-2 h-11" onClick={() => openComposer()}>
              Celebrate a win
            </Button>
          </EmptyState>
        }
      />
    </div>
  );
}

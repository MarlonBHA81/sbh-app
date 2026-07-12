"use client";

import { CalendarClock, FileText } from "lucide-react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { DraftCard } from "@/components/posts/draft-card";
import { PostList } from "@/components/posts/post-list";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

function StatusList({ status }: { status: "draft" | "scheduled" }) {
  const { mutationCount } = useComposer();

  return (
    <PostList
      trackViews={false}
      buildUrl={(cursor) =>
        `/api/v1/me/posts?status=${status}${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ""}`
      }
      refreshKey={mutationCount}
      renderItem={(post, helpers) => (
        <DraftCard post={post} onDeleted={helpers.remove} />
      )}
      emptyState={
        status === "draft" ? (
          <EmptyState
            icon={FileText}
            title="No drafts"
            description="Posts you save as drafts will show up here."
          />
        ) : (
          <EmptyState
            icon={CalendarClock}
            title="Nothing scheduled"
            description="Pick a date and time in the composer to schedule a post."
          />
        )
      }
    />
  );
}

export function DraftsView() {
  return (
    <Tabs defaultValue="drafts">
      <TabsList className="w-full">
        <TabsTrigger value="drafts" className="h-10 flex-1">
          Drafts
        </TabsTrigger>
        <TabsTrigger value="scheduled" className="h-10 flex-1">
          Scheduled
        </TabsTrigger>
      </TabsList>
      <TabsContent value="drafts" className="pt-2">
        <StatusList status="draft" />
      </TabsContent>
      <TabsContent value="scheduled" className="pt-2">
        <StatusList status="scheduled" />
      </TabsContent>
    </Tabs>
  );
}

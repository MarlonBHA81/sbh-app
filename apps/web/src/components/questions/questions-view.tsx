"use client";

import { HelpCircle } from "lucide-react";
import { useRef, useState } from "react";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { PostList, type PostListHandle } from "@/components/posts/post-list";
import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { withParam } from "@/lib/utils";

type Tab = "all" | "open";

function QuestionsPane({ endpoint }: { endpoint: string }) {
  const { mutationCount, openComposer } = useComposer();
  const listRef = useRef<PostListHandle>(null);

  return (
    <PostList
      ref={listRef}
      buildUrl={(cursor) =>
        cursor ? withParam(endpoint, "cursor", cursor) : endpoint
      }
      refreshKey={mutationCount}
      emptyState={
        <EmptyState
          icon={HelpCircle}
          title="No questions yet"
          description="Ask the community anything about running your business — someone here has been there."
        >
          <Button className="mt-2 h-11" onClick={() => openComposer()}>
            Ask a question
          </Button>
        </EmptyState>
      }
    />
  );
}

/** Ask-the-Community Q&A (V2 · CONNECT): all questions and open ones. */
export function QuestionsView() {
  const [tab, setTab] = useState<Tab>("all");

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title="Questions" />
      <p className="text-sm text-text-secondary">
        Ask the community and mark the reply that helped you.
      </p>
      <Tabs value={tab} onValueChange={(v) => setTab(v as Tab)}>
        <TabsList className="w-full">
          <TabsTrigger value="all" className="h-10 flex-1">
            All
          </TabsTrigger>
          <TabsTrigger value="open" className="h-10 flex-1">
            Open
          </TabsTrigger>
        </TabsList>
        <TabsContent value="all" className="pt-3">
          <QuestionsPane endpoint="/api/v1/feeds/questions" />
        </TabsContent>
        <TabsContent value="open" className="pt-3">
          <QuestionsPane endpoint="/api/v1/feeds/questions?answered=0" />
        </TabsContent>
      </Tabs>
    </div>
  );
}

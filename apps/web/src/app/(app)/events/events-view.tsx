"use client";

import { CalendarDays } from "lucide-react";
import { useTranslations } from "next-intl";

import { useComposer } from "@/components/composer/composer-provider";
import { EmptyState } from "@/components/empty-state";
import { PostList } from "@/components/posts/post-list";
import { ScreenHeader } from "@/components/shell/screen-header";
import { withParam } from "@/lib/utils";

const ENDPOINT = "/api/v1/business/events?filter=upcoming";

/** Events screen (reskin spec): the Feeds layout filtered to event posts. */
export function EventsView() {
  const tn = useTranslations("nav");
  const { mutationCount } = useComposer();

  return (
    <div className="flex flex-col gap-4">
      <ScreenHeader title={tn("events")} />
      <PostList
        buildUrl={(cursor) =>
          cursor ? withParam(ENDPOINT, "cursor", cursor) : ENDPOINT
        }
        refreshKey={mutationCount}
        emptyState={
          <EmptyState
            icon={CalendarDays}
            title="No upcoming events"
            description="Events from businesses in the community will show up here."
          />
        }
      />
    </div>
  );
}

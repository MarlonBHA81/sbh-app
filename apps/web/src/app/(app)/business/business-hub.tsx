"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";

import { DirectoryTab } from "@/components/business/directory-tab";
import { EventsTab } from "@/components/business/events-tab";
import { MatchesTab } from "@/components/business/matches-tab";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

const TABS = ["directory", "events", "matches"] as const;
type Tab = (typeof TABS)[number];

function isTab(value: string | null): value is Tab {
  return value !== null && (TABS as readonly string[]).includes(value);
}

export function BusinessHub() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const param = searchParams.get("tab");
  const tab: Tab = isTab(param) ? param : "directory";

  function setTab(next: string) {
    const params = new URLSearchParams(searchParams.toString());
    if (next === "directory") params.delete("tab");
    else params.set("tab", next);
    const query = params.toString();
    router.replace(query ? `${pathname}?${query}` : pathname, {
      scroll: false,
    });
  }

  return (
    <Tabs value={tab} onValueChange={setTab}>
      <TabsList className="w-full">
        <TabsTrigger value="directory" className="h-9 flex-1">
          Directory
        </TabsTrigger>
        <TabsTrigger value="events" className="h-9 flex-1">
          Events
        </TabsTrigger>
        <TabsTrigger value="matches" className="h-9 flex-1">
          Matches
        </TabsTrigger>
      </TabsList>
      <TabsContent value="directory" className="pt-2">
        <DirectoryTab />
      </TabsContent>
      <TabsContent value="events" className="pt-2">
        <EventsTab />
      </TabsContent>
      <TabsContent value="matches" className="pt-2">
        <MatchesTab />
      </TabsContent>
    </Tabs>
  );
}

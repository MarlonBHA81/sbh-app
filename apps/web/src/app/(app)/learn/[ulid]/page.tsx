import type { Metadata } from "next";

import { LessonView } from "@/components/learn/lesson-view";

export const metadata: Metadata = { title: "Lesson" };

export default async function LessonPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <LessonView ulid={ulid} />;
}

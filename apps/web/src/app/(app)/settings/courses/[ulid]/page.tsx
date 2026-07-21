import type { Metadata } from "next";

import { CourseBuilder } from "@/components/settings/course-builder";

export const metadata: Metadata = { title: "Course builder" };

export default async function CourseBuilderPage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <CourseBuilder ulid={ulid} />;
}

import type { Metadata } from "next";

import { CoursePlayer } from "@/components/course/course-player";

export const metadata: Metadata = { title: "Course" };

export default async function CoursePage({
  params,
}: {
  params: Promise<{ ulid: string }>;
}) {
  const { ulid } = await params;
  return <CoursePlayer ulid={ulid} />;
}

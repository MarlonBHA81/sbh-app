import type { Metadata } from "next";

import { QuestionsView } from "@/components/questions/questions-view";

export const metadata: Metadata = { title: "Questions" };

export default function QuestionsPage() {
  return <QuestionsView />;
}

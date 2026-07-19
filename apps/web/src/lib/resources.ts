import type { ResourceCategory, ResourceType } from "@/lib/api/types";

/** Member-facing label for each resource category (V2 · LEARN). */
export const RESOURCE_CATEGORIES: {
  key: ResourceCategory;
  label: string;
}[] = [
  { key: "marketing", label: "Marketing" },
  { key: "finance", label: "Finance" },
  { key: "operations", label: "Operations" },
  { key: "sales", label: "Sales" },
  { key: "legal", label: "Legal" },
  { key: "people", label: "People" },
];

/** Singular label for a single resource's type badge. */
const TYPE_LABEL: Record<ResourceType, string> = {
  template: "Template",
  checklist: "Checklist",
  toolkit: "Toolkit",
  ai_prompt: "AI prompt",
};

export function resourceTypeLabel(type: ResourceType): string {
  return TYPE_LABEL[type] ?? type;
}

export function resourceCategoryLabel(category: ResourceCategory): string {
  return (
    RESOURCE_CATEGORIES.find((c) => c.key === category)?.label ?? category
  );
}

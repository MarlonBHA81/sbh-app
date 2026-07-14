/**
 * A single in-progress compose draft, persisted client-side (UX pattern 4).
 * "Losing a draft at the signup wall is the single worst moment we can create"
 * — so the composer autosaves the body text here and restores it when reopened,
 * and the draft survives the signup flow to land the user back in compose with
 * their words intact.
 */
const KEY = "sbh:compose-draft";

export function readComposeDraft(): string {
  if (typeof window === "undefined") return "";
  try {
    return window.localStorage.getItem(KEY) ?? "";
  } catch {
    return "";
  }
}

export function writeComposeDraft(text: string): void {
  if (typeof window === "undefined") return;
  try {
    if (text.trim().length > 0) window.localStorage.setItem(KEY, text);
    else window.localStorage.removeItem(KEY);
  } catch {
    // ignore
  }
}

export function clearComposeDraft(): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.removeItem(KEY);
  } catch {
    // ignore
  }
}

export function hasComposeDraft(): boolean {
  return readComposeDraft().trim().length > 0;
}

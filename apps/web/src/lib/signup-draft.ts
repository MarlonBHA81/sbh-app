/**
 * Pre-signup choices persisted client-side (UX pattern 4 — IKEA effect).
 * The build-first signup wizard writes each personalizing choice here as it's
 * made, so nothing the user built is lost on reload, and the values hydrate
 * into the account on completion.
 */
export interface SignupDraft {
  industry?: string;
  name?: string;
  color?: string;
}

const KEY = "sbh:signup-draft";

export function readSignupDraft(): SignupDraft {
  if (typeof window === "undefined") return {};
  try {
    const raw = window.localStorage.getItem(KEY);
    return raw ? (JSON.parse(raw) as SignupDraft) : {};
  } catch {
    return {};
  }
}

export function writeSignupDraft(draft: SignupDraft): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(KEY, JSON.stringify(draft));
  } catch {
    // Best-effort; a private-mode storage failure just means no persistence.
  }
}

export function clearSignupDraft(): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.removeItem(KEY);
  } catch {
    // ignore
  }
}

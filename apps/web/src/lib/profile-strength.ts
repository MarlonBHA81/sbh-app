import type { Profile } from "@/lib/api/types";

/**
 * Goal-gradient model (UX pattern 2 — never show a user at zero).
 *
 * A profile's completeness is a set of equally-weighted steps whose points sum
 * to 100. Step one — "Create your account" — is always done, so a brand-new
 * user never starts at 0%. The step list is profile-kind-aware: personal
 * profiles get four steps (25% each) and skip the business-only "industry"
 * step, while business profiles get five (20% each). Both the onboarding
 * checklist and the slim profile-strength meter are computed from this single
 * source so they never disagree.
 */

export interface StrengthStep {
  key: "account" | "photo" | "industry" | "follow" | "post";
  label: string;
  /** Copy for the "next action" chip, e.g. "Add your logo". */
  action: string;
  done: boolean;
  /** Percentage this step contributes (steps sum to 100). */
  points: number;
  /** Where tapping the step / chip takes the user to complete it. */
  href: string;
}

export interface ProfileStrength {
  steps: StrengthStep[];
  /** 0–100, floored at 20 — account creation is step one and always counts. */
  pct: number;
  /** The first incomplete step, or null once every step is done. */
  next: StrengthStep | null;
  complete: boolean;
}

export function computeProfileStrength(profile: Profile): ProfileStrength {
  const hasIndustry = Boolean(
    profile.business_category ||
      (profile.category && profile.category.trim().length > 0),
  );
  const isBusiness = profile.kind === "business";

  // Points are split evenly across each kind's step list so a fully-complete
  // profile always reads 100% (personal: 4 steps × 25, business: 5 steps × 20).
  const account: StrengthStep = {
    key: "account",
    label: "Create your account",
    action: "Account created",
    done: true,
    points: isBusiness ? 20 : 25,
    href: "/settings/profile",
  };
  const photo: StrengthStep = {
    key: "photo",
    label: isBusiness ? "Add your logo" : "Add a profile photo",
    action: isBusiness ? "Add your logo" : "Add a profile photo",
    done: Boolean(profile.avatar_url),
    points: isBusiness ? 20 : 25,
    href: "/settings/profile",
  };
  const industry: StrengthStep = {
    key: "industry",
    label: "Set your industry",
    action: "Set your industry",
    done: hasIndustry,
    points: 20,
    href: "/settings/profile",
  };
  const follow: StrengthStep = {
    key: "follow",
    label: "Follow 3 members",
    action: "Follow 3 members",
    done: profile.following_count >= 3,
    points: isBusiness ? 20 : 25,
    href: "/search",
  };
  const post: StrengthStep = {
    key: "post",
    label: "Make your first post",
    action: "Make your first post",
    done: profile.posts_count >= 1,
    points: isBusiness ? 20 : 25,
    href: "/home",
  };

  const steps: StrengthStep[] = isBusiness
    ? [account, photo, industry, follow, post]
    : [account, photo, follow, post];

  const earned = steps
    .filter((s) => s.done)
    .reduce((sum, s) => sum + s.points, 0);
  const pct = Math.max(20, earned);
  const next = steps.find((s) => !s.done) ?? null;

  return { steps, pct, next, complete: next === null };
}

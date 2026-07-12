"use client";

import {
  ArrowLeft,
  Check,
  Eye,
  Heart,
  Loader2,
  MessageCircle,
  Sparkles,
} from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { TYPE_BADGES } from "@/components/post-types/registry";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Slider } from "@/components/ui/slider";
import * as api from "@/lib/api/client";
import {
  BUDGET_MAX_CENTS,
  BUDGET_MIN_CENTS,
  BUDGET_STEP_CENTS,
  DURATION_MAX_DAYS,
  DURATION_MIN_DAYS,
  estimatedReach,
  formatCompact,
  formatRand,
} from "@/lib/ads/format";
import type { Campaign, CampaignPost, Paginated, Post } from "@/lib/api/types";
import { formatCount } from "@/lib/reactions";

/** A short human label for a post's type (badge or capitalized fallback). */
function typeLabel(type: Post["type"]): string {
  return TYPE_BADGES[type] ?? type.charAt(0).toUpperCase() + type.slice(1);
}

/** Best-effort one-line preview of a post for compact rows. */
function postPreview(post: Pick<Post, "body" | "type">): string {
  const body = post.body?.trim();
  if (body) return body;
  return `${typeLabel(post.type)} post`;
}

function CompactPostRow({
  post,
  onSelect,
}: {
  post: Post;
  onSelect: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className="flex w-full flex-col gap-2 rounded-xl border p-3 text-left transition-colors hover:bg-accent/50"
    >
      <div className="flex items-center gap-2">
        <Badge variant="secondary" className="text-[10px]">
          {typeLabel(post.type)}
        </Badge>
        <span className="text-xs text-muted-foreground">
          {post.visibility === "public" ? "Public" : "Followers"}
        </span>
      </div>
      <p className="line-clamp-2 text-sm">{postPreview(post)}</p>
      <div className="flex items-center gap-4 text-xs text-muted-foreground tabular-nums">
        <span className="flex items-center gap-1">
          <Eye className="size-3.5" aria-hidden />
          {formatCount(post.views_count) || "0"}
        </span>
        <span className="flex items-center gap-1">
          <Heart className="size-3.5" aria-hidden />
          {formatCount(post.likes_count) || "0"}
        </span>
        <span className="flex items-center gap-1">
          <MessageCircle className="size-3.5" aria-hidden />
          {formatCount(post.comments_count) || "0"}
        </span>
      </div>
    </button>
  );
}

function PickStep({
  excluded,
  onSelect,
}: {
  excluded: Set<string>;
  onSelect: (post: Post) => void;
}) {
  const [posts, setPosts] = useState<Post[] | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    api
      .get<Paginated<Post>>("/api/v1/me/posts?status=published")
      .then((res) => {
        if (!cancelled) setPosts(res.data);
      })
      .catch(() => {
        if (!cancelled) setFailed(true);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  if (failed) {
    return (
      <p className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
        Couldn&apos;t load your posts. Please try again.
      </p>
    );
  }

  if (posts === null) {
    return (
      <div className="flex flex-col gap-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-24 w-full rounded-xl" />
        ))}
      </div>
    );
  }

  const promotable = posts.filter(
    (post) => post.visibility === "public" && !excluded.has(post.ulid),
  );

  if (promotable.length === 0) {
    return (
      <p className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
        You don&apos;t have any public published posts to promote yet.
      </p>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {promotable.map((post) => (
        <CompactPostRow
          key={post.ulid}
          post={post}
          onSelect={() => onSelect(post)}
        />
      ))}
    </div>
  );
}

function ConfigStep({
  budgetCents,
  durationDays,
  onBudget,
  onDuration,
}: {
  budgetCents: number;
  durationDays: number;
  onBudget: (cents: number) => void;
  onDuration: (days: number) => void;
}) {
  const reach = estimatedReach(budgetCents);
  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-col gap-3">
        <div className="flex items-baseline justify-between">
          <label className="text-sm font-medium">Budget</label>
          <span className="text-lg font-semibold tabular-nums">
            {formatRand(budgetCents)}
          </span>
        </div>
        <Slider
          value={[budgetCents]}
          min={BUDGET_MIN_CENTS}
          max={BUDGET_MAX_CENTS}
          step={BUDGET_STEP_CENTS}
          onValueChange={([value]) => onBudget(value)}
          aria-label="Budget"
        />
        <div className="flex justify-between text-xs text-muted-foreground">
          <span>{formatRand(BUDGET_MIN_CENTS)}</span>
          <span>{formatRand(BUDGET_MAX_CENTS)}</span>
        </div>
      </div>

      <div className="flex flex-col gap-3">
        <div className="flex items-baseline justify-between">
          <label className="text-sm font-medium">Duration</label>
          <span className="text-lg font-semibold tabular-nums">
            {durationDays} {durationDays === 1 ? "day" : "days"}
          </span>
        </div>
        <Slider
          value={[durationDays]}
          min={DURATION_MIN_DAYS}
          max={DURATION_MAX_DAYS}
          step={1}
          onValueChange={([value]) => onDuration(value)}
          aria-label="Duration"
        />
        <div className="flex justify-between text-xs text-muted-foreground">
          <span>{DURATION_MIN_DAYS} day</span>
          <span>{DURATION_MAX_DAYS} days</span>
        </div>
      </div>

      <div className="flex items-center gap-3 rounded-xl border bg-muted/40 p-4">
        <Sparkles className="size-5 shrink-0 text-primary" aria-hidden />
        <div className="flex flex-col">
          <span className="text-sm font-medium">
            Estimated reach: ~{formatCompact(reach)} impressions
          </span>
          <span className="text-xs text-muted-foreground">
            Shown in the For You feed over {durationDays}{" "}
            {durationDays === 1 ? "day" : "days"}.
          </span>
        </div>
      </div>
    </div>
  );
}

function ReviewStep({
  post,
  budgetCents,
  durationDays,
}: {
  post: Post;
  budgetCents: number;
  durationDays: number;
}) {
  const rows: [string, string][] = [
    ["Budget", formatRand(budgetCents)],
    ["Duration", `${durationDays} ${durationDays === 1 ? "day" : "days"}`],
    ["Estimated reach", `~${formatCompact(estimatedReach(budgetCents))}`],
  ];
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-2 rounded-xl border p-3">
        <Badge variant="secondary" className="w-fit text-[10px]">
          {typeLabel(post.type)}
        </Badge>
        <p className="line-clamp-3 text-sm">{postPreview(post)}</p>
      </div>
      <dl className="flex flex-col gap-2 rounded-xl border p-4">
        {rows.map(([label, value]) => (
          <div key={label} className="flex items-center justify-between text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>
      <p className="text-xs text-muted-foreground">
        Your card won&apos;t be charged in this demo. The campaign starts
        immediately and you can pause or end it any time.
      </p>
    </div>
  );
}

function SuccessStep({ post }: { post: CampaignPost }) {
  return (
    <div className="flex flex-col items-center gap-4 py-8 text-center">
      <div className="flex size-14 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600">
        <Check className="size-7" aria-hidden />
      </div>
      <div className="flex flex-col gap-1">
        <p className="text-lg font-semibold">Your post is being promoted</p>
        <p className="max-w-xs text-sm text-muted-foreground">
          It will start appearing in the For You feed shortly. Track its
          performance from the Ad Center.
        </p>
      </div>
      <div className="w-full rounded-xl border p-3 text-left">
        <Badge variant="secondary" className="mb-2 text-[10px]">
          {typeLabel(post.type)}
        </Badge>
        <p className="line-clamp-2 text-sm">{postPreview(post)}</p>
      </div>
    </div>
  );
}

const STEP_META = [
  { title: "Choose a post", description: "Pick a public post to promote." },
  {
    title: "Set your budget",
    description: "Choose how much to spend and for how long.",
  },
  { title: "Review & promote", description: "Confirm the details below." },
] as const;

export function PromoteSheet({
  open,
  onOpenChange,
  preselected,
  onCreated,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  preselected: Post | null;
  onCreated: (campaign: Campaign) => void;
}) {
  const [step, setStep] = useState<1 | 2 | 3>(preselected ? 2 : 1);
  const [selected, setSelected] = useState<Post | null>(preselected);
  const [budgetCents, setBudgetCents] = useState(50_000);
  const [durationDays, setDurationDays] = useState(7);
  const [excluded, setExcluded] = useState<Set<string>>(new Set());
  const [submitting, setSubmitting] = useState(false);
  const [created, setCreated] = useState<Campaign | null>(null);

  async function submit() {
    if (!selected || submitting) return;
    setSubmitting(true);
    try {
      const res = await api.post<{ data: Campaign }>(
        "/api/v1/ads/campaigns",
        {
          post_ulid: selected.ulid,
          budget_cents: budgetCents,
          duration_days: durationDays,
        },
      );
      setCreated(res.data);
      onCreated(res.data);
    } catch (error) {
      if (error instanceof api.ApiError && error.status === 409) {
        // Post is already promoted — drop it from the picker and restart.
        const ulid = selected.ulid;
        setExcluded((prev) => new Set(prev).add(ulid));
        setSelected(null);
        setStep(1);
        toast.error("That post is already being promoted.");
      } else {
        toast.error(
          error instanceof api.ApiError
            ? error.message
            : "Couldn't start the campaign.",
        );
      }
    } finally {
      setSubmitting(false);
    }
  }

  const meta = STEP_META[step - 1];

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="w-full gap-0 sm:max-w-md"
        onInteractOutside={(event) => {
          if (submitting) event.preventDefault();
        }}
      >
        {created ? (
          <>
            <SheetHeader>
              <SheetTitle>Promotion started</SheetTitle>
              <SheetDescription>Your campaign is now live.</SheetDescription>
            </SheetHeader>
            <div className="flex-1 overflow-y-auto px-4">
              <SuccessStep post={created.post} />
            </div>
            <div className="flex flex-col gap-2 border-t p-4">
              <Button className="h-11" onClick={() => onOpenChange(false)}>
                Done
              </Button>
            </div>
          </>
        ) : (
          <>
            <SheetHeader>
              <SheetTitle>{meta.title}</SheetTitle>
              <SheetDescription>{meta.description}</SheetDescription>
              <div
                className="mt-2 flex gap-1.5"
                role="progressbar"
                aria-valuenow={step}
                aria-valuemin={1}
                aria-valuemax={3}
                aria-label={`Step ${step} of 3`}
              >
                {[1, 2, 3].map((n) => (
                  <span
                    key={n}
                    className={
                      n <= step
                        ? "h-1 flex-1 rounded-full bg-primary"
                        : "h-1 flex-1 rounded-full bg-muted"
                    }
                  />
                ))}
              </div>
            </SheetHeader>

            <div className="flex-1 overflow-y-auto px-4">
              {step === 1 ? (
                <PickStep
                  excluded={excluded}
                  onSelect={(post) => {
                    setSelected(post);
                    setStep(2);
                  }}
                />
              ) : step === 2 ? (
                <ConfigStep
                  budgetCents={budgetCents}
                  durationDays={durationDays}
                  onBudget={setBudgetCents}
                  onDuration={setDurationDays}
                />
              ) : selected ? (
                <ReviewStep
                  post={selected}
                  budgetCents={budgetCents}
                  durationDays={durationDays}
                />
              ) : null}
            </div>

            {step > 1 ? (
              <div className="flex gap-2 border-t p-4">
                <Button
                  type="button"
                  variant="outline"
                  className="h-11"
                  disabled={submitting}
                  onClick={() => setStep((s) => (s === 3 ? 2 : 1) as 1 | 2)}
                >
                  <ArrowLeft className="size-4" aria-hidden />
                  Back
                </Button>
                {step === 2 ? (
                  <Button
                    type="button"
                    className="h-11 flex-1"
                    onClick={() => setStep(3)}
                  >
                    Continue
                  </Button>
                ) : (
                  <Button
                    type="button"
                    className="h-11 flex-1"
                    disabled={submitting}
                    onClick={() => void submit()}
                  >
                    {submitting ? (
                      <Loader2 className="size-4 animate-spin" aria-hidden />
                    ) : null}
                    {submitting ? "Starting…" : "Promote"}
                  </Button>
                )}
              </div>
            ) : null}
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

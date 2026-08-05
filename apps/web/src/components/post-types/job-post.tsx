"use client";

import { Briefcase, ExternalLink, MapPin } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ExternalLink as OutboundLink } from "@/components/ui/external-link";
import type { EmploymentType, Post } from "@/lib/api/types";

import { PostBody } from "./post-body";

const EMPLOYMENT_LABELS: Record<EmploymentType, string> = {
  full_time: "Full-time",
  part_time: "Part-time",
  contract: "Contract",
  freelance: "Freelance",
  internship: "Internship",
};

const CURRENCY_SYMBOLS: Record<string, string> = {
  ZAR: "R",
  USD: "$",
  EUR: "€",
  GBP: "£",
  NGN: "₦",
  KES: "KSh",
};

function compact(amount: number): string {
  if (amount >= 1000) {
    const k = amount / 1000;
    return `${Number.isInteger(k) ? k : k.toFixed(1)}k`;
  }
  return String(amount);
}

function formatSalary(
  min: number | null,
  max: number | null,
  currency: string,
): string | null {
  if (min == null && max == null) return null;
  const symbol = CURRENCY_SYMBOLS[currency] ?? `${currency} `;
  if (min != null && max != null) {
    return `${symbol}${compact(min)}–${symbol}${compact(max)}`;
  }
  const single = (min ?? max) as number;
  return `${min != null ? "From " : "Up to "}${symbol}${compact(single)}`;
}

export function JobPost({ post }: { post: Post }) {
  const job = post.job ?? null;
  if (!job) return post.body ? <PostBody text={post.body} /> : null;

  const salary = formatSalary(job.salary_min, job.salary_max, job.currency);

  return (
    <div className="flex flex-col gap-3 rounded-xl border p-4" data-no-nav>
      <div className="flex items-start gap-3">
        <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Briefcase className="size-5" aria-hidden />
        </span>
        <div className="flex min-w-0 flex-col">
          <h3 className="font-semibold leading-tight">{job.title}</h3>
          <span className="flex items-center gap-1 text-sm text-muted-foreground">
            <span className="truncate">{job.company}</span>
            {job.location ? (
              <>
                <span aria-hidden>·</span>
                <MapPin className="size-3.5 shrink-0" aria-hidden />
                <span className="truncate">{job.location}</span>
              </>
            ) : null}
          </span>
        </div>
      </div>

      <div className="flex flex-wrap gap-1.5">
        <Badge variant="secondary">
          {EMPLOYMENT_LABELS[job.employment_type] ?? job.employment_type}
        </Badge>
        {salary ? <Badge variant="secondary">{salary}</Badge> : null}
      </div>

      {post.body ? <PostBody text={post.body} /> : null}

      {job.is_expired ? (
        <Button type="button" variant="outline" className="h-11" disabled>
          Expired
        </Button>
      ) : (
        <Button asChild className="h-11">
          <OutboundLink href={job.apply_url} stopPropagation>
            Apply
            <ExternalLink className="size-4" aria-hidden />
          </OutboundLink>
        </Button>
      )}
    </div>
  );
}

"use client";
import { Plus, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

export type PollDuration = "1h" | "6h" | "24h" | "3d" | "7d" | "none";

const MIN_OPTIONS = 2;
const MAX_OPTIONS = 6;

const DURATION_LABELS: { value: PollDuration; label: string }[] = [
  { value: "1h", label: "1 hour" },
  { value: "6h", label: "6 hours" },
  { value: "24h", label: "24 hours" },
  { value: "3d", label: "3 days" },
  { value: "7d", label: "7 days" },
  { value: "none", label: "No end date" },
];

const DURATION_MS: Record<Exclude<PollDuration, "none">, number> = {
  "1h": 60 * 60 * 1000,
  "6h": 6 * 60 * 60 * 1000,
  "24h": 24 * 60 * 60 * 1000,
  "3d": 3 * 24 * 60 * 60 * 1000,
  "7d": 7 * 24 * 60 * 60 * 1000,
};

/** Whole hours the API expects for `duration_hours`, or null when the poll never closes. */
export function pollDurationHours(duration: PollDuration): number | null {
  if (duration === "none") return null;
  return DURATION_MS[duration] / (60 * 60 * 1000);
}

export function PollEditor({
  options,
  onOptionsChange,
  duration,
  onDurationChange,
}: {
  options: string[];
  onOptionsChange: (options: string[]) => void;
  duration: PollDuration;
  onDurationChange: (duration: PollDuration) => void;
}) {
  function setOption(index: number, value: string) {
    onOptionsChange(options.map((o, i) => (i === index ? value : o)));
  }

  function addOption() {
    if (options.length < MAX_OPTIONS) onOptionsChange([...options, ""]);
  }

  function removeOption(index: number) {
    if (options.length > MIN_OPTIONS) {
      onOptionsChange(options.filter((_, i) => i !== index));
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-col gap-2">
        {options.map((option, index) => (
          <div key={index} className="flex items-center gap-2">
            <Input
              placeholder={`Option ${index + 1}`}
              className="h-11"
              value={option}
              maxLength={80}
              onChange={(e) => setOption(index, e.target.value)}
              aria-label={`Poll option ${index + 1}`}
            />
            {options.length > MIN_OPTIONS ? (
              <button
                type="button"
                aria-label={`Remove option ${index + 1}`}
                onClick={() => removeOption(index)}
                className="flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
              >
                <X className="size-4" aria-hidden />
              </button>
            ) : null}
          </div>
        ))}
      </div>

      {options.length < MAX_OPTIONS ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-10 self-start gap-1.5"
          onClick={addOption}
        >
          <Plus className="size-4" aria-hidden />
          Add option
        </Button>
      ) : null}

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="poll-duration" className="text-xs text-muted-foreground">
          Poll ends after
        </Label>
        <Select
          value={duration}
          onValueChange={(value) => onDurationChange(value as PollDuration)}
        >
          <SelectTrigger id="poll-duration" className="h-11 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {DURATION_LABELS.map(({ value, label }) => (
              <SelectItem key={value} value={value}>
                {label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}

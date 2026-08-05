"use client";

import { useEffect, useRef, useState } from "react";

import { ProfileAvatar } from "@/components/profile-avatar";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import type { Profile } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/** Matches an in-progress "@handle" token immediately before the caret. */
const MENTION_RE = /(?:^|\s)@(\w{1,30})$/;

interface MentionState {
  query: string;
  /** Index in the text where the "@" starts. */
  at: number;
}

/**
 * Auto-growing textarea with @mention autocomplete. Typing `@word` opens a
 * keyboard-navigable list from /search/profiles; selecting inserts the handle.
 */
export function MentionTextarea({
  value,
  onChange,
  placeholder,
  maxLength,
  autoFocus,
  minHeightClass = "min-h-11",
  textareaRef,
  onKeyDown,
}: {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  maxLength?: number;
  autoFocus?: boolean;
  minHeightClass?: string;
  textareaRef?: React.RefObject<HTMLTextAreaElement | null>;
  onKeyDown?: (event: React.KeyboardEvent<HTMLTextAreaElement>) => void;
}) {
  const innerRef = useRef<HTMLTextAreaElement | null>(null);
  const ref = textareaRef ?? innerRef;

  const [mention, setMention] = useState<MentionState | null>(null);
  const [results, setResults] = useState<Profile[]>([]);
  const [active, setActive] = useState(0);
  const seqRef = useRef(0);

  const open = mention !== null && results.length > 0;

  function closeMenu() {
    setMention(null);
    setResults([]);
  }

  // Recompute the mention token whenever the caret moves or text changes.
  function syncMention(target: HTMLTextAreaElement) {
    const caret = target.selectionStart ?? target.value.length;
    const before = target.value.slice(0, caret);
    const match = before.match(MENTION_RE);
    if (match) {
      const at = caret - match[1].length - 1;
      setMention({ query: match[1], at });
    } else {
      closeMenu();
    }
  }

  // Debounced profile search for the active mention query.
  useEffect(() => {
    if (!mention) return;
    const seq = ++seqRef.current;
    const timer = window.setTimeout(() => {
      api
        .get<{ data: Profile[] }>(
          `/api/v1/search/profiles?q=${encodeURIComponent(mention.query)}`,
        )
        .then((res) => {
          if (seqRef.current === seq) {
            setResults(res.data);
            setActive(0);
          }
        })
        .catch(() => {
          if (seqRef.current === seq) setResults([]);
        });
    }, 180);
    return () => window.clearTimeout(timer);
  }, [mention]);

  function insertHandle(profile: Profile) {
    if (!mention) return;
    const target = ref.current;
    const caret = target?.selectionStart ?? value.length;
    const before = value.slice(0, mention.at);
    const after = value.slice(caret);
    const insertion = `@${profile.handle} `;
    const next = before + insertion + after;
    onChange(next);
    closeMenu();
    // Restore focus and place the caret after the inserted handle.
    requestAnimationFrame(() => {
      const node = ref.current;
      if (node) {
        const pos = before.length + insertion.length;
        node.focus();
        node.setSelectionRange(pos, pos);
      }
    });
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (open) {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        setActive((i) => (i + 1) % results.length);
        return;
      }
      if (event.key === "ArrowUp") {
        event.preventDefault();
        setActive((i) => (i - 1 + results.length) % results.length);
        return;
      }
      if (event.key === "Enter" || event.key === "Tab") {
        event.preventDefault();
        insertHandle(results[active]);
        return;
      }
      if (event.key === "Escape") {
        event.preventDefault();
        closeMenu();
        return;
      }
    }
    onKeyDown?.(event);
  }

  return (
    <div className="relative">
      <Textarea
        ref={ref}
        value={value}
        placeholder={placeholder}
        maxLength={maxLength}
        autoFocus={autoFocus}
        className={cn("resize-none", minHeightClass)}
        onChange={(event) => {
          onChange(event.target.value);
          syncMention(event.target);
        }}
        onKeyUp={(event) => syncMention(event.currentTarget)}
        onClick={(event) => syncMention(event.currentTarget)}
        onBlur={() => {
          // Delay so a click on a suggestion registers first.
          window.setTimeout(closeMenu, 120);
        }}
        onKeyDown={handleKeyDown}
        aria-autocomplete="list"
        aria-expanded={open}
      />
      {open ? (
        <ul
          role="listbox"
          className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
        >
          {results.map((profile, index) => (
            <li key={profile.ulid} role="option" aria-selected={index === active}>
              <button
                type="button"
                // Prevent the textarea blur from firing before the click.
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => insertHandle(profile)}
                className={cn(
                  "flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm",
                  index === active ? "bg-accent" : "hover:bg-accent/60",
                )}
              >
                <ProfileAvatar profile={profile} className="size-7" />
                <span className="flex min-w-0 flex-col">
                  <span className="truncate font-medium">{profile.name}</span>
                  <span className="truncate text-xs text-muted-foreground">
                    @{profile.handle}
                  </span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

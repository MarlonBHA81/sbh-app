"use client";

import { Sparkles } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { ScreenHeader } from "@/components/shell/screen-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as api from "@/lib/api/client";
import type { CoachMessage } from "@/lib/api/types";
import { cn } from "@/lib/utils";

interface SendResponse {
  data: { user: CoachMessage; assistant: CoachMessage };
}

const SUGGESTIONS = [
  "How should I price my products?",
  "How do I find my first customers?",
  "Where can I find funding?",
];

/** AI Business Coach v1 (V2 · LEARN): a persisted chat surface. */
export function CoachView() {
  const [messages, setMessages] = useState<CoachMessage[] | null>(null);
  const [input, setInput] = useState("");
  const [sending, setSending] = useState(false);
  const listEndRef = useRef<HTMLDivElement>(null);
  // Monotonic negative ids for optimistic (not-yet-persisted) member messages.
  const tempIdRef = useRef(0);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: CoachMessage[] }>("/api/v1/coach")
      .then((res) => {
        if (!cancelled) setMessages(res.data);
      })
      .catch(() => {
        if (!cancelled) setMessages([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    listEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, sending]);

  async function send(text: string) {
    const body = text.trim();
    if (!body || sending) return;

    // Optimistically show the member's message (temporary negative id).
    tempIdRef.current -= 1;
    const optimistic: CoachMessage = {
      id: tempIdRef.current,
      role: "user",
      body,
      created_at: null,
    };
    setMessages((prev) => [...(prev ?? []), optimistic]);
    setInput("");
    setSending(true);

    try {
      const res = await api.post<SendResponse>("/api/v1/coach/messages", {
        body,
      });
      setMessages((prev) => [
        ...(prev ?? []).filter((m) => m.id !== optimistic.id),
        res.data.user,
        res.data.assistant,
      ]);
    } catch {
      setMessages((prev) => (prev ?? []).filter((m) => m.id !== optimistic.id));
      setInput(body);
      toast.error("Couldn't reach the coach — try again.");
    } finally {
      setSending(false);
    }
  }

  const isEmpty = messages !== null && messages.length === 0;

  return (
    <div className="flex min-h-[70vh] flex-col gap-4">
      <ScreenHeader title="AI Coach" />

      <div className="flex flex-1 flex-col gap-3">
        {messages === null ? (
          <p className="text-sm text-text-secondary">Loading…</p>
        ) : isEmpty ? (
          <div className="flex flex-col gap-4 rounded-(--radius-card) border border-warmgray bg-card p-5 text-center shadow-card">
            <span className="mx-auto flex size-11 items-center justify-center rounded-full bg-teal/12 text-teal">
              <Sparkles className="size-5" aria-hidden />
            </span>
            <div className="flex flex-col gap-1">
              <h2 className="font-heading text-[16px] font-semibold text-text-primary">
                Your business coach
              </h2>
              <p className="text-sm text-text-secondary">
                Ask anything about running or growing your business. Replies are
                AI-generated guidance — always sense-check the specifics.
              </p>
            </div>
            <div className="flex flex-col gap-2">
              {SUGGESTIONS.map((s) => (
                <button
                  key={s}
                  type="button"
                  onClick={() => void send(s)}
                  className="rounded-full border border-warmgray bg-background px-3 py-2 text-sm text-text-primary transition-colors hover:bg-accent"
                >
                  {s}
                </button>
              ))}
            </div>
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            {messages.map((m) => (
              <div
                key={m.id}
                className={cn(
                  "flex",
                  m.role === "user" ? "justify-end" : "justify-start",
                )}
              >
                <div
                  className={cn(
                    "max-w-[85%] rounded-2xl px-3.5 py-2.5 text-[15px] leading-relaxed whitespace-pre-wrap",
                    m.role === "user"
                      ? "bg-teal text-white"
                      : "border border-warmgray bg-card text-text-primary",
                  )}
                >
                  {m.body}
                </div>
              </div>
            ))}
            {sending ? (
              <div className="flex justify-start">
                <div className="rounded-2xl border border-warmgray bg-card px-3.5 py-2.5 text-sm text-text-secondary">
                  Thinking…
                </div>
              </div>
            ) : null}
          </div>
        )}
        <div ref={listEndRef} />
      </div>

      <form
        className="sticky bottom-0 flex items-center gap-2 bg-background/95 py-2 backdrop-blur"
        onSubmit={(e) => {
          e.preventDefault();
          void send(input);
        }}
      >
        <Input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Ask your coach…"
          aria-label="Message the coach"
          className="flex-1"
          disabled={sending}
        />
        <Button
          type="submit"
          className="h-11 shrink-0"
          disabled={sending || input.trim() === ""}
        >
          Ask the coach
        </Button>
      </form>
    </div>
  );
}

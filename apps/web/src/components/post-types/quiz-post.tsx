"use client";

import { Check, GraduationCap, X } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import * as api from "@/lib/api/client";
import type { Post, Quiz } from "@/lib/api/types";
import { cn } from "@/lib/utils";

import { PostBody } from "./post-body";

export function QuizPost({ post }: { post: Post }) {
  const [quiz, setQuiz] = useState<Quiz | null>(post.quiz ?? null);
  const [open, setOpen] = useState(false);
  const [step, setStep] = useState(0);
  const [answers, setAnswers] = useState<number[]>([]);
  const [phase, setPhase] = useState<"taking" | "result">("taking");
  const [submitting, setSubmitting] = useState(false);

  if (!quiz) return post.body ? <PostBody text={post.body} /> : null;

  const questions = quiz.questions;
  const attempted = quiz.viewer_attempt != null;

  function start() {
    if (attempted && quiz?.viewer_attempt) {
      setAnswers(quiz.viewer_attempt.answers);
      setPhase("result");
    } else {
      setAnswers(Array(questions.length).fill(-1));
      setPhase("taking");
      setStep(0);
    }
    setOpen(true);
  }

  function choose(optionIndex: number) {
    setAnswers((prev) => prev.map((a, i) => (i === step ? optionIndex : a)));
  }

  async function submit() {
    if (submitting) return;
    setSubmitting(true);
    try {
      const res = await api.post<{ data: Post }>(
        `/api/v1/posts/${post.ulid}/quiz-attempt`,
        { answers },
      );
      if (res.data.quiz) setQuiz(res.data.quiz);
      setPhase("result");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't submit your answers",
      );
    } finally {
      setSubmitting(false);
    }
  }

  const scorePct = quiz.viewer_attempt?.score_pct ?? 0;
  const answered = answers[step] >= 0;
  const isLast = step === questions.length - 1;
  const allAnswered = answers.length === questions.length && answers.every((a) => a >= 0);

  return (
    <>
      <div className="flex flex-col gap-3 rounded-xl border bg-card p-4" data-no-nav>
        {post.body ? <PostBody text={post.body} className="font-medium" /> : null}
        <div className="flex items-center gap-3">
          <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
            <GraduationCap className="size-5" aria-hidden />
          </span>
          <div className="flex min-w-0 flex-1 flex-col">
            <span className="text-sm font-semibold">
              {questions.length} {questions.length === 1 ? "question" : "questions"}
            </span>
            <span className="text-xs text-muted-foreground">
              {quiz.attempts_count}{" "}
              {quiz.attempts_count === 1 ? "attempt" : "attempts"}
              {attempted ? ` · You scored ${Math.round(scorePct)}%` : ""}
            </span>
          </div>
          <Button
            type="button"
            variant={attempted ? "outline" : "default"}
            className="h-10 shrink-0"
            onClick={(event) => {
              event.stopPropagation();
              start();
            }}
          >
            {attempted ? "Review answers" : "Take quiz"}
          </Button>
        </div>
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="flex max-h-[90dvh] flex-col gap-4 sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {phase === "result" ? "Results" : "Quiz"}
            </DialogTitle>
            <DialogDescription className="sr-only">
              Answer each question then submit.
            </DialogDescription>
          </DialogHeader>

          {phase === "taking" ? (
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
              <div className="flex items-center justify-center gap-1.5">
                {questions.map((_, i) => (
                  <span
                    key={i}
                    className={cn(
                      "size-2 rounded-full transition-colors",
                      i === step
                        ? "bg-primary"
                        : answers[i] >= 0
                          ? "bg-primary/40"
                          : "bg-muted",
                    )}
                    aria-hidden
                  />
                ))}
              </div>

              <p className="text-center text-xs text-muted-foreground">
                Question {step + 1} of {questions.length}
              </p>
              <p className="text-base font-medium">{questions[step].question}</p>

              <div className="flex flex-col gap-2">
                {questions[step].options.map((option, i) => (
                  <button
                    key={i}
                    type="button"
                    onClick={() => choose(i)}
                    className={cn(
                      "flex min-h-11 items-center rounded-lg border px-4 text-left text-sm transition-colors",
                      answers[step] === i
                        ? "border-primary bg-primary/10 font-medium"
                        : "hover:border-primary/50 hover:bg-accent/50",
                    )}
                  >
                    {option}
                  </button>
                ))}
              </div>

              <div className="mt-auto flex gap-2 pt-2">
                {step > 0 ? (
                  <Button
                    type="button"
                    variant="outline"
                    className="h-11 flex-1"
                    onClick={() => setStep((s) => s - 1)}
                  >
                    Back
                  </Button>
                ) : null}
                {isLast ? (
                  <Button
                    type="button"
                    className="h-11 flex-1"
                    disabled={!allAnswered || submitting}
                    onClick={() => void submit()}
                  >
                    {submitting ? "Submitting…" : "Submit"}
                  </Button>
                ) : (
                  <Button
                    type="button"
                    className="h-11 flex-1"
                    disabled={!answered}
                    onClick={() => setStep((s) => s + 1)}
                  >
                    Next
                  </Button>
                )}
              </div>
            </div>
          ) : (
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
              <div className="flex flex-col items-center gap-1 py-2">
                <span className="text-4xl font-bold tabular-nums text-primary">
                  {Math.round(scorePct)}%
                </span>
                <span className="text-sm text-muted-foreground">
                  {answers.filter(
                    (a, i) => a === questions[i]?.correct_index,
                  ).length}{" "}
                  of {questions.length} correct
                </span>
              </div>

              <div className="flex flex-col gap-3">
                {questions.map((q, qi) => {
                  const userAnswer = answers[qi];
                  return (
                    <div
                      key={qi}
                      className="flex flex-col gap-1.5 rounded-lg border p-3"
                    >
                      <p className="text-sm font-medium">{q.question}</p>
                      {q.options.map((option, oi) => {
                        const isCorrect = q.correct_index === oi;
                        const isUser = userAnswer === oi;
                        return (
                          <div
                            key={oi}
                            className={cn(
                              "flex items-center gap-2 rounded-md px-2 py-1.5 text-sm",
                              isCorrect &&
                                "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
                              isUser &&
                                !isCorrect &&
                                "bg-destructive/10 text-destructive",
                            )}
                          >
                            {isCorrect ? (
                              <Check className="size-4 shrink-0" aria-hidden />
                            ) : isUser ? (
                              <X className="size-4 shrink-0" aria-hidden />
                            ) : (
                              <span className="size-4 shrink-0" aria-hidden />
                            )}
                            <span>{option}</span>
                          </div>
                        );
                      })}
                    </div>
                  );
                })}
              </div>

              <Button
                type="button"
                className="h-11"
                onClick={() => setOpen(false)}
              >
                Done
              </Button>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}

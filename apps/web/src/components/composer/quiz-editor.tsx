"use client";
import { Check, Plus, Trash2, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

export interface QuizDraftQuestion {
  question: string;
  options: string[];
  correctIndex: number;
}

export const MAX_QUESTIONS = 10;
const MIN_OPTIONS = 2;
const MAX_OPTIONS = 4;

export function emptyQuizQuestion(): QuizDraftQuestion {
  return { question: "", options: ["", ""], correctIndex: 0 };
}

/** True when every question has text, ≥2 non-empty options and a valid answer. */
export function isQuizValid(questions: QuizDraftQuestion[]): boolean {
  if (questions.length === 0) return false;
  return questions.every((q) => {
    const filled = q.options.filter((o) => o.trim().length > 0);
    return (
      q.question.trim().length > 0 &&
      filled.length >= MIN_OPTIONS &&
      q.options[q.correctIndex]?.trim().length > 0
    );
  });
}

export function QuizEditor({
  questions,
  onChange,
}: {
  questions: QuizDraftQuestion[];
  onChange: (questions: QuizDraftQuestion[]) => void;
}) {
  function update(index: number, patch: Partial<QuizDraftQuestion>) {
    onChange(questions.map((q, i) => (i === index ? { ...q, ...patch } : q)));
  }

  function addQuestion() {
    if (questions.length < MAX_QUESTIONS) {
      onChange([...questions, emptyQuizQuestion()]);
    }
  }

  function removeQuestion(index: number) {
    onChange(questions.filter((_, i) => i !== index));
  }

  function setOption(qIndex: number, oIndex: number, value: string) {
    update(qIndex, {
      options: questions[qIndex].options.map((o, i) =>
        i === oIndex ? value : o,
      ),
    });
  }

  function addOption(qIndex: number) {
    const q = questions[qIndex];
    if (q.options.length < MAX_OPTIONS) {
      update(qIndex, { options: [...q.options, ""] });
    }
  }

  function removeOption(qIndex: number, oIndex: number) {
    const q = questions[qIndex];
    if (q.options.length <= MIN_OPTIONS) return;
    const options = q.options.filter((_, i) => i !== oIndex);
    let correctIndex = q.correctIndex;
    if (oIndex === correctIndex) correctIndex = 0;
    else if (oIndex < correctIndex) correctIndex -= 1;
    update(qIndex, { options, correctIndex });
  }

  return (
    <div className="flex flex-col gap-3">
      {questions.map((q, qIndex) => (
        <div
          key={qIndex}
          className="flex flex-col gap-2 rounded-xl border bg-muted/20 p-3"
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-muted-foreground">
              Question {qIndex + 1}
            </span>
            {questions.length > 1 ? (
              <button
                type="button"
                aria-label={`Remove question ${qIndex + 1}`}
                onClick={() => removeQuestion(qIndex)}
                className="flex size-7 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
              >
                <Trash2 className="size-4" aria-hidden />
              </button>
            ) : null}
          </div>
          <Textarea
            placeholder="Question…"
            className="min-h-16 bg-background"
            value={q.question}
            onChange={(e) => update(qIndex, { question: e.target.value })}
            aria-label={`Question ${qIndex + 1} text`}
          />
          <div className="flex flex-col gap-1.5">
            {q.options.map((option, oIndex) => {
              const correct = q.correctIndex === oIndex;
              return (
                <div key={oIndex} className="flex items-center gap-2">
                  <button
                    type="button"
                    aria-label={`Mark option ${oIndex + 1} correct`}
                    aria-pressed={correct}
                    onClick={() => update(qIndex, { correctIndex: oIndex })}
                    className={cn(
                      "flex size-6 shrink-0 items-center justify-center rounded-full border transition-colors",
                      correct
                        ? "border-emerald-600 bg-emerald-600 text-white"
                        : "border-input text-transparent hover:border-emerald-600/50",
                    )}
                  >
                    <Check className="size-3.5" aria-hidden />
                  </button>
                  <Input
                    placeholder={`Option ${oIndex + 1}`}
                    className="h-10 bg-background"
                    value={option}
                    maxLength={120}
                    onChange={(e) => setOption(qIndex, oIndex, e.target.value)}
                    aria-label={`Question ${qIndex + 1} option ${oIndex + 1}`}
                  />
                  {q.options.length > MIN_OPTIONS ? (
                    <button
                      type="button"
                      aria-label={`Remove option ${oIndex + 1}`}
                      onClick={() => removeOption(qIndex, oIndex)}
                      className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                    >
                      <X className="size-4" aria-hidden />
                    </button>
                  ) : null}
                </div>
              );
            })}
          </div>
          {q.options.length < MAX_OPTIONS ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 self-start gap-1.5"
              onClick={() => addOption(qIndex)}
            >
              <Plus className="size-4" aria-hidden />
              Add option
            </Button>
          ) : null}
          <p className="text-[11px] text-muted-foreground">
            Tap the circle to mark the correct answer.
          </p>
        </div>
      ))}

      {questions.length < MAX_QUESTIONS ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-10 self-start gap-1.5"
          onClick={addQuestion}
        >
          <Plus className="size-4" aria-hidden />
          Add question
        </Button>
      ) : null}
    </div>
  );
}

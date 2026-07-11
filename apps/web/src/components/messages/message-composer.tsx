"use client";

import { ImagePlus, SendHorizontal, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import { PhotoPicker } from "@/components/composer/photo-picker";
import { Button } from "@/components/ui/button";
import { useClientValue } from "@/hooks/use-client-value";
import type { Media } from "@/lib/api/types";
import type { ChatMessage } from "./chat-types";
import { cn } from "@/lib/utils";

export function MessageComposer({
  replyTo,
  onCancelReply,
  onSend,
  onTyping,
  disabled,
}: {
  replyTo: ChatMessage | null;
  onCancelReply: () => void;
  onSend: (body: string, media: Media[]) => void;
  onTyping: () => void;
  disabled?: boolean;
}) {
  const [text, setText] = useState("");
  const [media, setMedia] = useState<Media[]>([]);
  const [showAttach, setShowAttach] = useState(false);
  const [uploading, setUploading] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  // Touch devices: Enter inserts a newline; a Send button submits instead.
  const isTouch = useClientValue(
    () => window.matchMedia("(pointer: coarse)").matches,
    false,
  );

  // Autosize the textarea to its content (capped by max-height in CSS).
  useEffect(() => {
    const el = textareaRef.current;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`;
  }, [text]);

  const canSend =
    !disabled && !uploading && (text.trim().length > 0 || media.length > 0);

  function submit() {
    if (!canSend) return;
    onSend(text.trim(), media);
    setText("");
    setMedia([]);
    setShowAttach(false);
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === "Enter" && !event.shiftKey && !isTouch) {
      event.preventDefault();
      submit();
    }
  }

  return (
    <div className="border-t bg-background p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
      {replyTo ? (
        <div className="mb-2 flex items-start gap-2 rounded-lg border-l-2 border-primary bg-muted/60 px-3 py-2">
          <div className="min-w-0 flex-1">
            <p className="text-xs font-medium">
              Replying to {replyTo.profile.name}
            </p>
            <p className="line-clamp-1 text-xs text-muted-foreground">
              {replyTo.body ?? (replyTo.media.length > 0 ? "Photo" : "Message")}
            </p>
          </div>
          <button
            type="button"
            onClick={onCancelReply}
            aria-label="Cancel reply"
            className="flex size-6 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-background hover:text-foreground"
          >
            <X className="size-4" aria-hidden />
          </button>
        </div>
      ) : null}

      {showAttach || media.length > 0 ? (
        <div className="mb-2">
          <PhotoPicker
            images={media}
            onChange={setMedia}
            max={4}
            onUploadingChange={setUploading}
          />
        </div>
      ) : null}

      <div className="flex items-end gap-1.5">
        <button
          type="button"
          onClick={() => setShowAttach((v) => !v)}
          aria-label="Attach photos"
          disabled={disabled || media.length >= 4}
          className={cn(
            "flex size-10 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50",
            showAttach && "bg-accent text-foreground",
          )}
        >
          <ImagePlus className="size-5" aria-hidden />
        </button>

        <textarea
          ref={textareaRef}
          value={text}
          onChange={(event) => {
            setText(event.target.value);
            onTyping();
          }}
          onKeyDown={handleKeyDown}
          rows={1}
          placeholder="Message"
          disabled={disabled}
          className="max-h-40 min-h-10 flex-1 resize-none rounded-2xl border bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
        />

        <Button
          type="button"
          size="icon"
          onClick={submit}
          disabled={!canSend}
          aria-label="Send message"
          className="size-10 shrink-0 rounded-full"
        >
          <SendHorizontal className="size-5" aria-hidden />
        </Button>
      </div>
    </div>
  );
}

"use client";

import { useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import { ApiError } from "@/lib/api/client";
import type { Conversation } from "@/lib/api/types";

export function EditGroupDialog({
  conversation,
  open,
  onOpenChange,
  onUpdated,
}: {
  conversation: Conversation;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onUpdated: (conversation: Conversation) => void;
}) {
  const [title, setTitle] = useState(conversation.title ?? "");
  const [rules, setRules] = useState(conversation.rules ?? "");
  const [busy, setBusy] = useState(false);
  const [wasOpen, setWasOpen] = useState(open);

  // Reset the fields on the closed→open transition (render-phase, no effect).
  if (open && !wasOpen) {
    setWasOpen(true);
    setTitle(conversation.title ?? "");
    setRules(conversation.rules ?? "");
  } else if (!open && wasOpen) {
    setWasOpen(false);
  }

  async function save() {
    if (busy || !title.trim()) return;
    setBusy(true);
    try {
      const res = await api.patch<{ data: Conversation }>(
        `/api/v1/conversations/${conversation.ulid}`,
        { title: title.trim(), rules: rules.trim() || null },
      );
      onUpdated(res.data);
      toast.success("Group updated");
      onOpenChange(false);
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Couldn't update the group",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Edit group</DialogTitle>
          <DialogDescription>
            Update the group name and rules.
          </DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5">
            <label htmlFor="edit-group-title" className="text-sm font-medium">
              Group name
            </label>
            <Input
              id="edit-group-title"
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              maxLength={80}
              className="h-11"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <label htmlFor="edit-group-rules" className="text-sm font-medium">
              Group rules{" "}
              <span className="font-normal text-muted-foreground">
                (optional)
              </span>
            </label>
            <Textarea
              id="edit-group-rules"
              value={rules}
              onChange={(event) => setRules(event.target.value)}
              rows={4}
              maxLength={1000}
              placeholder="Set expectations for the group"
            />
          </div>
        </div>
        <DialogFooter>
          <Button
            type="button"
            className="h-11"
            disabled={busy || !title.trim()}
            onClick={() => void save()}
          >
            {busy ? "Saving…" : "Save changes"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

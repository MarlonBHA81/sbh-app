"use client";

import {
  CalendarClock,
  Image as ImageIcon,
  Keyboard,
  Link2,
  Lock,
  MapPin,
  Search,
  Type,
  X,
} from "lucide-react";
import { useState } from "react";

import { ParentCard } from "@/components/post-types/parent-card";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Sheet, SheetContent, SheetTitle } from "@/components/ui/sheet";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { useIsMobile } from "@/hooks/use-mobile";
import type {
  Media,
  Post,
  PostType,
  PostVisibility,
  TypewriterSpeed,
} from "@/lib/api/types";
import { formatLocalDateTime, isoToDatetimeLocal } from "@/lib/time";
import { cn } from "@/lib/utils";

import { PhotoPicker } from "./photo-picker";
import { useSavePost, type SavePostInput } from "./use-save-post";

const MAX_BODY = 500;

type ComposerType = Exclude<PostType, "repost">;

const TYPE_CHIPS: { value: ComposerType; label: string; icon: typeof Type }[] =
  [
    { value: "text", label: "Text", icon: Type },
    { value: "image", label: "Photos", icon: ImageIcon },
    { value: "link", label: "Link", icon: Link2 },
    { value: "typewriter", label: "Typewriter", icon: Keyboard },
    { value: "magnifier", label: "Magnifier", icon: Search },
    { value: "secret", label: "Secret", icon: Lock },
    { value: "checkin", label: "Check-in", icon: MapPin },
  ];

/** Types where the payload text field replaces the common body textarea. */
const PAYLOAD_TEXT_TYPES: ComposerType[] = ["typewriter", "magnifier", "secret"];

function isValidUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return url.protocol === "http:" || url.protocol === "https:";
  } catch {
    return false;
  }
}

function payloadString(payload: Record<string, unknown> | null, key: string) {
  const value = payload?.[key];
  return typeof value === "string" ? value : "";
}

export function Composer({
  open,
  onOpenChange,
  quoteParent,
  editPost,
  onSaved,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  quoteParent: Post | null;
  editPost: Post | null;
  onSaved: () => void;
}) {
  const isMobile = useIsMobile();
  const { save, saving } = useSavePost();

  const parent = quoteParent ?? editPost?.parent ?? null;
  const payload = editPost?.payload ?? null;

  const [type, setType] = useState<ComposerType>(() => {
    if (quoteParent) return "quote";
    if (editPost && editPost.type !== "repost") return editPost.type;
    return "text";
  });
  const [body, setBody] = useState(editPost?.body ?? "");
  const [linkUrl, setLinkUrl] = useState(payloadString(payload, "url"));
  const [linkTitle, setLinkTitle] = useState(payloadString(payload, "title"));
  const [linkDescription, setLinkDescription] = useState(
    payloadString(payload, "description"),
  );
  const [twText, setTwText] = useState(
    editPost?.type === "typewriter" ? payloadString(payload, "text") : "",
  );
  const [twSpeed, setTwSpeed] = useState<TypewriterSpeed>(() => {
    const speed = payload?.speed;
    return speed === "slow" || speed === "fast" ? speed : "normal";
  });
  const [magText, setMagText] = useState(
    editPost?.type === "magnifier" ? payloadString(payload, "text") : "",
  );
  const [secretText, setSecretText] = useState(
    payloadString(payload, "secret_text"),
  );
  const [placeName, setPlaceName] = useState(
    payloadString(payload, "place_name"),
  );
  const [city, setCity] = useState(payloadString(payload, "city"));
  const [images, setImages] = useState<Media[]>(
    editPost?.type === "image" ? editPost.media : [],
  );
  const [magImages, setMagImages] = useState<Media[]>(
    editPost?.type === "magnifier" ? editPost.media : [],
  );
  const [visibility, setVisibility] = useState<PostVisibility>(
    editPost?.visibility ?? "public",
  );
  const [sensitive, setSensitive] = useState(editPost?.sensitive ?? false);
  const [scheduledLocal, setScheduledLocal] = useState(
    editPost?.scheduled_at ? isoToDatetimeLocal(editPost.scheduled_at) : "",
  );
  const [uploading, setUploading] = useState(false);

  const editing = Boolean(editPost);
  const isQuote = type === "quote";
  const usesPayloadText = PAYLOAD_TEXT_TYPES.includes(type);
  const payloadText =
    type === "typewriter" ? twText : type === "magnifier" ? magText : secretText;
  const activeText = usesPayloadText ? payloadText : body;
  const overLimit = activeText.length > MAX_BODY;

  const hasContent = (() => {
    switch (type) {
      case "text":
      case "quote":
        return body.trim().length > 0;
      case "image":
        return images.length > 0;
      case "link":
        return isValidUrl(linkUrl.trim());
      case "typewriter":
        return twText.trim().length > 0;
      case "magnifier":
        return magText.trim().length > 0;
      case "secret":
        return secretText.trim().length > 0;
      case "checkin":
        return placeName.trim().length > 0;
    }
  })();

  const canSave = hasContent && !overLimit && !uploading && !saving;

  function buildInput(status: SavePostInput["status"]): SavePostInput {
    const input: SavePostInput = {
      type,
      visibility,
      status,
      sensitive,
    };

    const trimmedBody = body.trim();
    if (!usesPayloadText && trimmedBody) input.body = trimmedBody;

    switch (type) {
      case "image":
        input.media_ids = images.map((m) => m.ulid);
        break;
      case "link":
        input.payload = {
          url: linkUrl.trim(),
          ...(linkTitle.trim() && { title: linkTitle.trim() }),
          ...(linkDescription.trim() && {
            description: linkDescription.trim(),
          }),
        };
        break;
      case "typewriter":
        input.payload = { text: twText.trim(), speed: twSpeed };
        break;
      case "magnifier":
        input.payload = {
          text: magText.trim(),
          ...(magImages[0] && { image_media_id: magImages[0].ulid }),
        };
        if (magImages[0]) input.media_ids = [magImages[0].ulid];
        break;
      case "secret":
        input.payload = { secret_text: secretText.trim() };
        break;
      case "checkin":
        input.payload = {
          place_name: placeName.trim(),
          ...(city.trim() && { city: city.trim() }),
        };
        break;
      case "quote":
        if (!editing && parent) input.parent_post_id = parent.ulid;
        break;
    }

    if (status === "scheduled" && scheduledLocal) {
      input.scheduled_at = new Date(scheduledLocal).toISOString();
    }

    return input;
  }

  async function submit(kind: "primary" | "draft") {
    const status =
      kind === "draft" ? "draft" : scheduledLocal ? "scheduled" : "published";
    const saved = await save(buildInput(status), editPost?.ulid);
    if (saved) {
      onSaved();
      onOpenChange(false);
    }
  }

  const title = editing ? "Edit post" : isQuote ? "Quote post" : "New post";
  const primaryLabel = saving
    ? "Saving…"
    : scheduledLocal
      ? "Schedule"
      : "Post";

  const form = (
    <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 pb-4">
      {!isQuote && !editing ? (
        <div
          className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1"
          role="tablist"
          aria-label="Post type"
        >
          {TYPE_CHIPS.map(({ value, label, icon: Icon }) => (
            <Button
              key={value}
              type="button"
              role="tab"
              aria-selected={type === value}
              variant={type === value ? "default" : "outline"}
              size="sm"
              className="h-10 shrink-0 gap-1.5 rounded-full px-4"
              onClick={() => setType(value)}
            >
              <Icon className="size-4" aria-hidden />
              {label}
            </Button>
          ))}
        </div>
      ) : null}

      {isQuote && parent ? <ParentCard post={parent} /> : null}

      {type === "image" ? (
        <PhotoPicker
          images={images}
          onChange={setImages}
          max={4}
          onUploadingChange={setUploading}
        />
      ) : null}

      {type === "link" ? (
        <div className="flex flex-col gap-2">
          <Input
            type="url"
            inputMode="url"
            placeholder="https://example.com"
            className="h-11"
            value={linkUrl}
            onChange={(e) => setLinkUrl(e.target.value)}
            aria-label="Link URL"
          />
          <Input
            placeholder="Title (optional)"
            className="h-11"
            value={linkTitle}
            onChange={(e) => setLinkTitle(e.target.value)}
            aria-label="Link title"
          />
          <Input
            placeholder="Description (optional)"
            className="h-11"
            value={linkDescription}
            onChange={(e) => setLinkDescription(e.target.value)}
            aria-label="Link description"
          />
        </div>
      ) : null}

      {type === "typewriter" ? (
        <div className="flex flex-col gap-2">
          <Textarea
            placeholder="Text that types itself out…"
            className="min-h-28 font-mono"
            value={twText}
            onChange={(e) => setTwText(e.target.value)}
            aria-label="Typewriter text"
          />
          <Select
            value={twSpeed}
            onValueChange={(value) => setTwSpeed(value as TypewriterSpeed)}
          >
            <SelectTrigger className="h-11 w-full" aria-label="Typing speed">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="slow">Slow</SelectItem>
              <SelectItem value="normal">Normal</SelectItem>
              <SelectItem value="fast">Fast</SelectItem>
            </SelectContent>
          </Select>
        </div>
      ) : null}

      {type === "magnifier" ? (
        <div className="flex flex-col gap-2">
          <Textarea
            placeholder="Hidden text — readers peek with a magnifier lens…"
            className="min-h-28"
            value={magText}
            onChange={(e) => setMagText(e.target.value)}
            aria-label="Magnifier text"
          />
          <PhotoPicker
            images={magImages}
            onChange={setMagImages}
            max={1}
            onUploadingChange={setUploading}
          />
        </div>
      ) : null}

      {type === "secret" ? (
        <div className="flex flex-col gap-2 rounded-xl border border-dashed bg-muted/40 p-3">
          <Label
            htmlFor="composer-secret"
            className="flex items-center gap-1.5 text-xs text-muted-foreground"
          >
            <Lock className="size-3.5" aria-hidden />
            Only revealed when a reader taps to unlock
          </Label>
          <Textarea
            id="composer-secret"
            placeholder="Your secret…"
            className="min-h-24 bg-background"
            value={secretText}
            onChange={(e) => setSecretText(e.target.value)}
          />
        </div>
      ) : null}

      {type === "checkin" ? (
        <div className="flex flex-col gap-2">
          <Input
            placeholder="Place name"
            className="h-11"
            value={placeName}
            onChange={(e) => setPlaceName(e.target.value)}
            aria-label="Place name"
          />
          <Input
            placeholder="City (optional)"
            className="h-11"
            value={city}
            onChange={(e) => setCity(e.target.value)}
            aria-label="City"
          />
        </div>
      ) : null}

      {!usesPayloadText ? (
        <Textarea
          placeholder={
            type === "image"
              ? "Add a caption…"
              : isQuote
                ? "Add your thoughts…"
                : "What's happening at your business?"
          }
          className="min-h-28"
          value={body}
          onChange={(e) => setBody(e.target.value)}
          aria-label="Post text"
          autoFocus={type === "text" || isQuote}
        />
      ) : null}

      <p
        className={cn(
          "-mt-2 self-end text-xs tabular-nums",
          overLimit ? "font-medium text-destructive" : "text-muted-foreground",
        )}
        aria-live="polite"
      >
        {activeText.length}/{MAX_BODY}
      </p>

      <div className="flex flex-wrap items-center gap-3">
        <Select
          value={visibility}
          onValueChange={(value) => setVisibility(value as PostVisibility)}
        >
          <SelectTrigger className="h-11 w-32" aria-label="Visibility">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="public">Public</SelectItem>
            <SelectItem value="followers">Followers</SelectItem>
          </SelectContent>
        </Select>

        <Popover>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="outline"
              className={cn("h-11 gap-2", scheduledLocal && "border-primary")}
            >
              <CalendarClock className="size-4" aria-hidden />
              {scheduledLocal
                ? formatLocalDateTime(new Date(scheduledLocal).toISOString())
                : "Schedule"}
            </Button>
          </PopoverTrigger>
          <PopoverContent align="start" className="flex w-80 flex-col gap-3">
            <Label htmlFor="composer-schedule">Publish at</Label>
            <Input
              id="composer-schedule"
              type="datetime-local"
              className="h-11"
              value={scheduledLocal}
              min={isoToDatetimeLocal(new Date().toISOString())}
              onChange={(e) => setScheduledLocal(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">
              Times are in SAST (your local time).
            </p>
            {scheduledLocal ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-9 self-start gap-1.5"
                onClick={() => setScheduledLocal("")}
              >
                <X className="size-4" aria-hidden />
                Clear schedule
              </Button>
            ) : null}
          </PopoverContent>
        </Popover>

        <label className="flex min-h-11 items-center gap-2 text-sm">
          <Switch checked={sensitive} onCheckedChange={setSensitive} />
          Sensitive
        </label>
      </div>

      <div className="mt-auto flex gap-2 pt-2">
        <Button
          type="button"
          variant="outline"
          className="h-11 flex-1"
          disabled={!canSave}
          onClick={() => void submit("draft")}
        >
          Save draft
        </Button>
        <Button
          type="button"
          className="h-11 flex-1"
          disabled={!canSave}
          onClick={() => void submit("primary")}
        >
          {primaryLabel}
        </Button>
      </div>
    </div>
  );

  if (isMobile) {
    return (
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent
          side="bottom"
          className="h-dvh gap-0 rounded-none border-t-0"
        >
          <div className="flex items-center px-4 py-3">
            <SheetTitle className="text-lg">{title}</SheetTitle>
          </div>
          {form}
        </SheetContent>
      </Sheet>
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex max-h-[90dvh] flex-col gap-0 p-0 sm:max-w-xl">
        <DialogHeader className="px-4 py-4">
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription className="sr-only">
            Create a new post
          </DialogDescription>
        </DialogHeader>
        {form}
      </DialogContent>
    </Dialog>
  );
}

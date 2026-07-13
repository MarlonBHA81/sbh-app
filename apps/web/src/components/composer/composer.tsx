"use client";

import {
  AudioLines,
  BarChart3,
  Briefcase,
  CalendarClock,
  CalendarDays,
  GraduationCap,
  Image as ImageIcon,
  Keyboard,
  LayoutGrid,
  Link2,
  Lock,
  MapPin,
  Newspaper,
  Search,
  Type,
  Video,
  X,
} from "lucide-react";
import dynamic from "next/dynamic";
import { useTranslations } from "next-intl";
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
  EmploymentType,
  Media,
  Post,
  PostTopic,
  PostType,
  PostVisibility,
  TiptapDoc,
  TypewriterSpeed,
} from "@/lib/api/types";
import { formatLocalDateTime, isoToDatetimeLocal } from "@/lib/time";
import { cn } from "@/lib/utils";

import { MediaUpload } from "./media-upload";
import { PhotoPicker } from "./photo-picker";
import {
  PollEditor,
  pollDurationHours,
  type PollDuration,
} from "./poll-editor";
import {
  QuizEditor,
  emptyQuizQuestion,
  isQuizValid,
  type QuizDraftQuestion,
} from "./quiz-editor";
import { SuggestedTopics } from "./suggested-topics";
import { TopicPicker } from "./topic-picker";
import { ProfileAvatar } from "@/components/profile-avatar";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

import { useSavePost, type SavePostInput } from "./use-save-post";

// The Tiptap editor is heavy; keep it out of the main bundle and off the server.
const BlogEditor = dynamic(() => import("./blog-editor"), {
  ssr: false,
  loading: () => <div className="h-52 rounded-md border bg-muted/40" />,
});

const MAX_BODY = 500;

type ComposerType = Exclude<PostType, "repost">;

/** `labelKey` indexes the `composer.types` message namespace. */
const TYPE_CHIPS: {
  value: ComposerType;
  labelKey: string;
  icon: typeof Type;
}[] = [
  { value: "text", labelKey: "text", icon: Type },
  { value: "image", labelKey: "photos", icon: ImageIcon },
  { value: "link", labelKey: "link", icon: Link2 },
  { value: "typewriter", labelKey: "typewriter", icon: Keyboard },
  { value: "magnifier", labelKey: "magnifier", icon: Search },
  { value: "secret", labelKey: "secret", icon: Lock },
  { value: "checkin", labelKey: "checkin", icon: MapPin },
  // Milestone 5 additions.
  { value: "video", labelKey: "video", icon: Video },
  { value: "audio", labelKey: "audio", icon: AudioLines },
  { value: "blog", labelKey: "blog", icon: Newspaper },
  { value: "poll", labelKey: "poll", icon: BarChart3 },
  { value: "quiz", labelKey: "quiz", icon: GraduationCap },
  { value: "event", labelKey: "event", icon: CalendarDays },
  { value: "job", labelKey: "job", icon: Briefcase },
  { value: "portfolio", labelKey: "portfolio", icon: LayoutGrid },
];

/** Types where the payload text field replaces the common body textarea. */
const PAYLOAD_TEXT_TYPES: ComposerType[] = ["typewriter", "magnifier", "secret"];

/** Types that show and submit the shared body textarea (caption / question). */
const SHOW_BODY: ComposerType[] = [
  "text",
  "quote",
  "image",
  "link",
  "checkin",
  "video",
  "poll",
];

/** Types that persist the body textarea as `post.body` (not into payload). */
const SEND_COMMON_BODY: ComposerType[] = [
  "text",
  "quote",
  "image",
  "link",
  "checkin",
  "video",
];

const EMPLOYMENT_TYPES: { value: EmploymentType; label: string }[] = [
  { value: "full_time", label: "Full-time" },
  { value: "part_time", label: "Part-time" },
  { value: "contract", label: "Contract" },
  { value: "freelance", label: "Freelance" },
  { value: "internship", label: "Internship" },
];

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

/** Returns a key under the `composer.placeholder` message namespace. */
function bodyPlaceholderKey(type: ComposerType, isQuote: boolean): string {
  if (type === "image") return "caption";
  if (type === "video") return "caption";
  if (type === "poll") return "pollQuestion";
  if (isQuote) return "quoteThoughts";
  return "default";
}

export function Composer({
  open,
  onOpenChange,
  quoteParent,
  editPost,
  onSaved,
  onPublished,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  quoteParent: Post | null;
  editPost: Post | null;
  onSaved: () => void;
  /** Fires when the post goes live immediately (not draft/scheduled). */
  onPublished?: (post: Post) => void;
}) {
  const isMobile = useIsMobile();
  const t = useTranslations("composer");
  const { save, saving } = useSavePost();
  const activeProfile = useAuthStore((state) => state.activeProfile);

  const parent = quoteParent ?? editPost?.parent ?? null;
  const payload = editPost?.payload ?? null;

  const [type, setType] = useState<ComposerType>(() => {
    if (quoteParent) return "quote";
    if (editPost && editPost.type !== "repost") return editPost.type;
    return "text";
  });
  const [body, setBody] = useState(() => {
    if (editPost?.type === "poll") return editPost.poll?.question ?? "";
    return editPost?.body ?? "";
  });
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

  // --- Milestone 5 state ---
  const [videoMedia, setVideoMedia] = useState<Media | null>(
    editPost?.type === "video" ? (editPost.media[0] ?? null) : null,
  );
  const [videoUploading, setVideoUploading] = useState(false);
  const [audioMedia, setAudioMedia] = useState<Media | null>(
    editPost?.type === "audio" ? (editPost.media[0] ?? null) : null,
  );
  const [audioUploading, setAudioUploading] = useState(false);
  const [audioTitle, setAudioTitle] = useState(payloadString(payload, "title"));

  const [blogTitle, setBlogTitle] = useState(payloadString(payload, "title"));
  const [blogDoc, setBlogDoc] = useState<TiptapDoc | null>(() => {
    const doc = payload?.doc;
    return editPost?.type === "blog" && doc ? (doc as TiptapDoc) : null;
  });
  const [blogText, setBlogText] = useState(payloadString(payload, "excerpt"));

  const [pollOptions, setPollOptions] = useState<string[]>(() => {
    const opts = editPost?.poll?.options;
    return opts && opts.length >= 2 ? opts.map((o) => o.label) : ["", ""];
  });
  const [pollDuration, setPollDuration] = useState<PollDuration>("24h");

  const [quizQuestions, setQuizQuestions] = useState<QuizDraftQuestion[]>(() => {
    const qs = editPost?.quiz?.questions;
    if (qs && qs.length > 0) {
      return qs.map((q) => ({
        question: q.question,
        options: q.options.length >= 2 ? q.options : ["", ""],
        correctIndex: q.correct_index ?? 0,
      }));
    }
    return [emptyQuizQuestion()];
  });

  const [eventTitle, setEventTitle] = useState(editPost?.event?.title ?? "");
  const [eventStart, setEventStart] = useState(
    editPost?.event?.starts_at
      ? isoToDatetimeLocal(editPost.event.starts_at)
      : "",
  );
  const [eventEnd, setEventEnd] = useState(
    editPost?.event?.ends_at ? isoToDatetimeLocal(editPost.event.ends_at) : "",
  );
  const [eventVenue, setEventVenue] = useState(editPost?.event?.venue ?? "");
  const [eventCover, setEventCover] = useState<Media[]>(
    editPost?.type === "event" ? editPost.media.slice(0, 1) : [],
  );

  const [jobTitle, setJobTitle] = useState(editPost?.job?.title ?? "");
  const [jobCompany, setJobCompany] = useState(editPost?.job?.company ?? "");
  const [jobLocation, setJobLocation] = useState(editPost?.job?.location ?? "");
  const [jobEmployment, setJobEmployment] = useState<EmploymentType>(
    editPost?.job?.employment_type ?? "full_time",
  );
  const [jobSalaryMin, setJobSalaryMin] = useState(
    editPost?.job?.salary_min != null ? String(editPost.job.salary_min) : "",
  );
  const [jobSalaryMax, setJobSalaryMax] = useState(
    editPost?.job?.salary_max != null ? String(editPost.job.salary_max) : "",
  );
  const [jobCurrency, setJobCurrency] = useState(
    editPost?.job?.currency ?? "ZAR",
  );
  const [jobApplyUrl, setJobApplyUrl] = useState(editPost?.job?.apply_url ?? "");
  const [jobExpires, setJobExpires] = useState(
    editPost?.job?.expires_at
      ? isoToDatetimeLocal(editPost.job.expires_at).slice(0, 10)
      : "",
  );

  const [portfolioTitle, setPortfolioTitle] = useState(
    editPost?.type === "portfolio" ? payloadString(payload, "title") : "",
  );
  const [portfolioDesc, setPortfolioDesc] = useState(
    editPost?.type === "portfolio" ? payloadString(payload, "description") : "",
  );
  const [portfolioImages, setPortfolioImages] = useState<Media[]>(
    editPost?.type === "portfolio" ? editPost.media : [],
  );

  const [topics, setTopics] = useState<PostTopic[]>(editPost?.topics ?? []);
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
  const showBody = SHOW_BODY.includes(type);
  const payloadText =
    type === "typewriter" ? twText : type === "magnifier" ? magText : secretText;
  const activeText = usesPayloadText ? payloadText : showBody ? body : "";
  const overLimit = activeText.length > MAX_BODY;

  const anyUploading = uploading || videoUploading || audioUploading;
  const mediaProcessing =
    (type === "video" && videoMedia?.status === "processing") ||
    (type === "audio" && audioMedia?.status === "processing");

  const pollFilled = pollOptions.filter((o) => o.trim().length > 0);

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
      case "video":
        return videoMedia !== null;
      case "audio":
        return audioMedia !== null;
      case "blog":
        return blogTitle.trim().length > 0 && blogText.trim().length > 0;
      case "poll":
        return pollFilled.length >= 2;
      case "quiz":
        return isQuizValid(quizQuestions);
      case "event":
        return eventTitle.trim().length > 0 && eventStart.length > 0;
      case "job":
        return jobTitle.trim().length > 0 && jobCompany.trim().length > 0;
      case "portfolio":
        return portfolioTitle.trim().length > 0 && portfolioImages.length > 0;
      default:
        return false;
    }
  })();

  const canSaveBase =
    hasContent && !overLimit && !anyUploading && !saving;
  const canPublish = canSaveBase && !mediaProcessing;

  function buildInput(status: SavePostInput["status"]): SavePostInput {
    const input: SavePostInput = {
      type,
      visibility,
      status,
      sensitive,
    };

    const trimmedBody = body.trim();
    if (SEND_COMMON_BODY.includes(type) && trimmedBody) input.body = trimmedBody;

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
      case "video":
        if (videoMedia) input.media_ids = [videoMedia.ulid];
        break;
      case "audio":
        if (audioMedia) input.media_ids = [audioMedia.ulid];
        if (audioTitle.trim()) input.payload = { title: audioTitle.trim() };
        break;
      case "blog":
        input.payload = {
          title: blogTitle.trim(),
          ...(blogDoc && { doc: blogDoc }),
          ...(blogText.trim() && { excerpt: blogText.trim().slice(0, 300) }),
        };
        break;
      case "poll": {
        const durationHours = pollDurationHours(pollDuration);
        input.payload = {
          options: pollFilled,
          ...(durationHours !== null && { duration_hours: durationHours }),
        };
        break;
      }
      case "quiz":
        input.payload = {
          questions: quizQuestions.map((q) => ({
            question: q.question.trim(),
            options: q.options.filter((o) => o.trim().length > 0),
            correct_index: q.correctIndex,
          })),
        };
        break;
      case "event":
        input.payload = {
          title: eventTitle.trim(),
          starts_at: new Date(eventStart).toISOString(),
          ...(eventEnd && { ends_at: new Date(eventEnd).toISOString() }),
          ...(eventVenue.trim() && { venue: eventVenue.trim() }),
        };
        if (eventCover[0]) input.media_ids = [eventCover[0].ulid];
        break;
      case "job":
        input.payload = {
          title: jobTitle.trim(),
          company: jobCompany.trim(),
          location: jobLocation.trim(),
          employment_type: jobEmployment,
          currency: jobCurrency.trim() || "ZAR",
          ...(jobSalaryMin.trim() && { salary_min: Number(jobSalaryMin) }),
          ...(jobSalaryMax.trim() && { salary_max: Number(jobSalaryMax) }),
          ...(jobApplyUrl.trim() && { apply_url: jobApplyUrl.trim() }),
          ...(jobExpires && {
            expires_at: new Date(jobExpires).toISOString(),
          }),
        };
        break;
      case "portfolio":
        input.payload = {
          title: portfolioTitle.trim(),
          ...(portfolioDesc.trim() && { description: portfolioDesc.trim() }),
        };
        input.media_ids = portfolioImages.map((m) => m.ulid);
        break;
      case "quote":
        if (!editing && parent) input.parent_post_id = parent.ulid;
        break;
    }

    // Always send when editing so clearing every topic persists too.
    if (topics.length > 0 || editing) {
      input.topic_ids = topics.map((topic) => topic.id);
    }

    if (status === "scheduled" && scheduledLocal) {
      input.scheduled_at = new Date(scheduledLocal).toISOString();
    }

    return input;
  }

  async function submit(kind: "primary" | "draft") {
    // Publishing while transcoding is rejected server-side (422); block early.
    if (kind === "primary" && mediaProcessing) return;
    const status =
      kind === "draft" ? "draft" : scheduledLocal ? "scheduled" : "published";
    const saved = await save(buildInput(status), editPost?.ulid);
    if (saved) {
      onSaved();
      onOpenChange(false);
      if (status === "published") onPublished?.(saved);
    }
  }

  const title = editing
    ? t("editPost")
    : isQuote
      ? t("quotePost")
      : t("newPost");
  const primaryLabel = saving
    ? t("saving")
    : scheduledLocal
      ? t("schedule")
      : t("post");

  const form = (
    <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 pb-4">
      {activeProfile ? (
        <div className="flex items-center gap-2 rounded-full border border-warmgray bg-muted/40 px-2 py-1.5">
          <ProfileAvatar profile={activeProfile} className="size-7" ring />
          <span className="text-xs text-text-secondary">
            Posting as{" "}
            <span className="font-medium text-text-primary">
              {activeProfile.name}
            </span>
          </span>
          <span
            className={
              activeProfile.kind === "business"
                ? "ms-auto rounded-full bg-plum/12 px-2 py-0.5 text-[10px] font-medium text-plum-tint"
                : "ms-auto rounded-full bg-teal/12 px-2 py-0.5 text-[10px] font-medium text-teal-text"
            }
          >
            {activeProfile.kind === "business" ? "Business" : "Personal"}
          </span>
        </div>
      ) : null}
      {!isQuote && !editing ? (
        <div
          className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [mask-image:linear-gradient(to_right,transparent,black_16px,black_calc(100%-16px),transparent)]"
          role="tablist"
          aria-label={t("postTypeLabel")}
        >
          {TYPE_CHIPS.map(({ value, labelKey, icon: Icon }) => (
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
              {t(`types.${labelKey}`)}
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

      {type === "video" ? (
        <MediaUpload
          type="video"
          media={videoMedia}
          onChange={setVideoMedia}
          onUploadingChange={setVideoUploading}
        />
      ) : null}

      {type === "audio" ? (
        <div className="flex flex-col gap-2">
          <MediaUpload
            type="audio"
            media={audioMedia}
            onChange={setAudioMedia}
            onUploadingChange={setAudioUploading}
          />
          <Input
            placeholder="Title (optional)"
            className="h-11"
            value={audioTitle}
            onChange={(e) => setAudioTitle(e.target.value)}
            aria-label="Audio title"
          />
        </div>
      ) : null}

      {type === "blog" ? (
        <div className="flex flex-col gap-2">
          <Input
            placeholder="Article title"
            className="h-11 text-base font-semibold"
            value={blogTitle}
            onChange={(e) => setBlogTitle(e.target.value)}
            aria-label="Article title"
          />
          <BlogEditor
            doc={blogDoc}
            onChange={(doc, text) => {
              setBlogDoc(doc);
              setBlogText(text);
            }}
          />
        </div>
      ) : null}

      {type === "poll" ? (
        <PollEditor
          options={pollOptions}
          onOptionsChange={setPollOptions}
          duration={pollDuration}
          onDurationChange={setPollDuration}
        />
      ) : null}

      {type === "quiz" ? (
        <QuizEditor questions={quizQuestions} onChange={setQuizQuestions} />
      ) : null}

      {type === "event" ? (
        <div className="flex flex-col gap-2">
          <Input
            placeholder="Event title"
            className="h-11"
            value={eventTitle}
            onChange={(e) => setEventTitle(e.target.value)}
            aria-label="Event title"
          />
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="event-start" className="text-xs text-muted-foreground">
              Starts
            </Label>
            <Input
              id="event-start"
              type="datetime-local"
              className="h-11"
              value={eventStart}
              min={isoToDatetimeLocal(new Date().toISOString())}
              onChange={(e) => setEventStart(e.target.value)}
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="event-end" className="text-xs text-muted-foreground">
              Ends (optional)
            </Label>
            <Input
              id="event-end"
              type="datetime-local"
              className="h-11"
              value={eventEnd}
              min={eventStart || isoToDatetimeLocal(new Date().toISOString())}
              onChange={(e) => setEventEnd(e.target.value)}
            />
          </div>
          <Input
            placeholder="Venue (optional)"
            className="h-11"
            value={eventVenue}
            onChange={(e) => setEventVenue(e.target.value)}
            aria-label="Venue"
          />
          <div className="flex flex-col gap-1.5">
            <Label className="text-xs text-muted-foreground">
              Cover photo (optional)
            </Label>
            <PhotoPicker
              images={eventCover}
              onChange={setEventCover}
              max={1}
              onUploadingChange={setUploading}
            />
          </div>
        </div>
      ) : null}

      {type === "job" ? (
        <div className="flex flex-col gap-2">
          <Input
            placeholder="Job title"
            className="h-11"
            value={jobTitle}
            onChange={(e) => setJobTitle(e.target.value)}
            aria-label="Job title"
          />
          <Input
            placeholder="Company"
            className="h-11"
            value={jobCompany}
            onChange={(e) => setJobCompany(e.target.value)}
            aria-label="Company"
          />
          <Input
            placeholder="Location"
            className="h-11"
            value={jobLocation}
            onChange={(e) => setJobLocation(e.target.value)}
            aria-label="Location"
          />
          <Select
            value={jobEmployment}
            onValueChange={(value) =>
              setJobEmployment(value as EmploymentType)
            }
          >
            <SelectTrigger className="h-11 w-full" aria-label="Employment type">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {EMPLOYMENT_TYPES.map(({ value, label }) => (
                <SelectItem key={value} value={value}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <div className="flex gap-2">
            <Input
              type="number"
              inputMode="numeric"
              placeholder="Min salary"
              className="h-11"
              value={jobSalaryMin}
              onChange={(e) => setJobSalaryMin(e.target.value)}
              aria-label="Minimum salary"
            />
            <Input
              type="number"
              inputMode="numeric"
              placeholder="Max salary"
              className="h-11"
              value={jobSalaryMax}
              onChange={(e) => setJobSalaryMax(e.target.value)}
              aria-label="Maximum salary"
            />
            <Input
              placeholder="ZAR"
              className="h-11 w-24"
              value={jobCurrency}
              maxLength={3}
              onChange={(e) => setJobCurrency(e.target.value.toUpperCase())}
              aria-label="Currency"
            />
          </div>
          <Input
            type="url"
            inputMode="url"
            placeholder="Application URL (optional)"
            className="h-11"
            value={jobApplyUrl}
            onChange={(e) => setJobApplyUrl(e.target.value)}
            aria-label="Application URL"
          />
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="job-expires" className="text-xs text-muted-foreground">
              Expires (optional)
            </Label>
            <Input
              id="job-expires"
              type="date"
              className="h-11"
              value={jobExpires}
              onChange={(e) => setJobExpires(e.target.value)}
            />
          </div>
        </div>
      ) : null}

      {type === "portfolio" ? (
        <div className="flex flex-col gap-2">
          <Input
            placeholder="Portfolio title"
            className="h-11"
            value={portfolioTitle}
            onChange={(e) => setPortfolioTitle(e.target.value)}
            aria-label="Portfolio title"
          />
          <Textarea
            placeholder="Description (optional)"
            className="min-h-20"
            value={portfolioDesc}
            onChange={(e) => setPortfolioDesc(e.target.value)}
            aria-label="Portfolio description"
          />
          <PhotoPicker
            images={portfolioImages}
            onChange={setPortfolioImages}
            max={10}
            onUploadingChange={setUploading}
          />
        </div>
      ) : null}

      {showBody ? (
        <Textarea
          placeholder={t(`placeholder.${bodyPlaceholderKey(type, isQuote)}`)}
          className="min-h-28"
          value={body}
          onChange={(e) => setBody(e.target.value)}
          aria-label="Post text"
          autoFocus={type === "text" || isQuote}
        />
      ) : null}

      {showBody || usesPayloadText ? (
        <p
          className={cn(
            "-mt-2 self-end text-xs tabular-nums",
            overLimit
              ? "font-medium text-destructive"
              : "text-muted-foreground",
          )}
          aria-live="polite"
        >
          {activeText.length}/{MAX_BODY}
        </p>
      ) : null}

      <SuggestedTopics
        text={activeText}
        selected={topics}
        onSelect={(topic) =>
          setTopics((prev) =>
            prev.some((t) => t.id === topic.id) || prev.length >= 3
              ? prev
              : [...prev, topic],
          )
        }
      />

      <TopicPicker value={topics} onChange={setTopics} />

      <div className="flex flex-wrap items-center gap-3">
        <Select
          value={visibility}
          onValueChange={(value) => setVisibility(value as PostVisibility)}
        >
          <SelectTrigger className="h-11 w-32" aria-label={t("visibility")}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="public">{t("public")}</SelectItem>
            <SelectItem value="followers">{t("followers")}</SelectItem>
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
                : t("schedule")}
            </Button>
          </PopoverTrigger>
          <PopoverContent align="start" className="flex w-80 flex-col gap-3">
            <Label htmlFor="composer-schedule">{t("publishAt")}</Label>
            <Input
              id="composer-schedule"
              type="datetime-local"
              className="h-11"
              value={scheduledLocal}
              min={isoToDatetimeLocal(new Date().toISOString())}
              onChange={(e) => setScheduledLocal(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">
              {t("scheduleHint")}
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
                {t("clearSchedule")}
              </Button>
            ) : null}
          </PopoverContent>
        </Popover>

        <label className="flex min-h-11 items-center gap-2 text-sm">
          <Switch checked={sensitive} onCheckedChange={setSensitive} />
          {t("sensitive")}
        </label>
      </div>

      {mediaProcessing ? (
        <p className="text-xs text-amber-600 dark:text-amber-500" aria-live="polite">
          {t("processingHint")}
        </p>
      ) : null}

      <div className="mt-auto flex gap-2 pt-2">
        <Button
          type="button"
          variant="outline"
          className="h-11 flex-1"
          disabled={!canSaveBase}
          onClick={() => void submit("draft")}
        >
          {t("saveDraft")}
        </Button>
        <Button
          type="button"
          className="h-11 flex-1"
          disabled={!canPublish}
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
            {t("createDescription")}
          </DialogDescription>
        </DialogHeader>
        {form}
      </DialogContent>
    </Dialog>
  );
}

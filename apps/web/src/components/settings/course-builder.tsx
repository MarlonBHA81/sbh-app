"use client";

import { ArrowLeft, Plus, Trash2, Upload } from "lucide-react";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";

interface BuilderLesson {
  ulid: string;
  title: string;
  body: string | null;
  video_url: string | null;
  minutes: number | null;
  is_preview: boolean;
  has_attachment: boolean;
}
interface BuilderModule {
  ulid: string;
  title: string;
  lessons: BuilderLesson[];
}
interface Curriculum {
  product: { ulid: string; title: string };
  modules: BuilderModule[];
}

/** Vendor course builder (Shop P3): author the modules → lessons curriculum. */
export function CourseBuilder({ ulid }: { ulid: string }) {
  const [data, setData] = useState<Curriculum | null>(null);
  const [missing, setMissing] = useState(false);
  const [newModule, setNewModule] = useState("");

  // Only ever called from event handlers, never inside an effect.
  const reload = useCallback(async () => {
    try {
      const res = await api.get<{ data: Curriculum }>(
        `/api/v1/me/store/products/${ulid}/curriculum`,
      );
      setData(res.data);
    } catch {
      setMissing(true);
    }
  }, [ulid]);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: Curriculum }>(`/api/v1/me/store/products/${ulid}/curriculum`)
      .then((res) => {
        if (!cancelled) setData(res.data);
      })
      .catch(() => {
        if (!cancelled) setMissing(true);
      });
    return () => {
      cancelled = true;
    };
  }, [ulid]);

  async function addModule() {
    if (newModule.trim() === "") return;
    try {
      await api.post(`/api/v1/me/store/products/${ulid}/modules`, {
        title: newModule.trim(),
      });
      setNewModule("");
      await reload();
    } catch {
      toast.error("Couldn't add the module");
    }
  }

  if (missing) {
    return (
      <div className="flex flex-col gap-3">
        <BackLink />
        <p className="text-sm text-text-secondary">
          This course isn&apos;t available, or it isn&apos;t a course product.
        </p>
      </div>
    );
  }

  if (!data) return <Skeleton className="h-72 w-full rounded-xl" />;

  return (
    <div className="flex flex-col gap-4">
      <BackLink />
      <h1 className="font-heading text-xl font-semibold text-text-primary">
        {data.product.title} — curriculum
      </h1>

      <div className="flex flex-col gap-4">
        {data.modules.map((module) => (
          <ModuleCard key={module.ulid} module={module} onChange={reload} />
        ))}
      </div>

      <div className="flex gap-2 rounded-lg border p-3">
        <Input
          value={newModule}
          onChange={(e) => setNewModule(e.target.value)}
          placeholder="New module title"
          className="h-11"
        />
        <Button
          type="button"
          className="h-11 shrink-0 gap-1"
          onClick={() => void addModule()}
          disabled={newModule.trim() === ""}
        >
          <Plus className="size-4" aria-hidden />
          Module
        </Button>
      </div>
    </div>
  );
}

function ModuleCard({
  module,
  onChange,
}: {
  module: BuilderModule;
  onChange: () => Promise<void>;
}) {
  const [adding, setAdding] = useState(false);

  async function removeModule() {
    try {
      await api.del(`/api/v1/me/store/modules/${module.ulid}`);
      await onChange();
    } catch {
      toast.error("Couldn't delete the module");
    }
  }

  return (
    <section className="flex flex-col gap-3 rounded-(--radius-card) border border-warmgray bg-card p-4 shadow-card">
      <div className="flex items-center gap-2">
        <h2 className="flex-1 font-heading text-[15px] font-semibold text-text-primary">
          {module.title}
        </h2>
        <button
          type="button"
          aria-label={`Delete ${module.title}`}
          onClick={() => void removeModule()}
          className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-destructive"
        >
          <Trash2 className="size-4" aria-hidden />
        </button>
      </div>

      <ul className="flex flex-col gap-2">
        {module.lessons.map((lesson) => (
          <LessonRow key={lesson.ulid} lesson={lesson} onChange={onChange} />
        ))}
        {module.lessons.length === 0 ? (
          <li className="text-[13px] text-text-secondary">No lessons yet.</li>
        ) : null}
      </ul>

      {adding ? (
        <LessonForm
          moduleUlid={module.ulid}
          onDone={async () => {
            setAdding(false);
            await onChange();
          }}
          onCancel={() => setAdding(false)}
        />
      ) : (
        <Button
          type="button"
          variant="outline"
          className="h-9 w-fit gap-1"
          onClick={() => setAdding(true)}
        >
          <Plus className="size-4" aria-hidden />
          Add lesson
        </Button>
      )}
    </section>
  );
}

function LessonRow({
  lesson,
  onChange,
}: {
  lesson: BuilderLesson;
  onChange: () => Promise<void>;
}) {
  const [editing, setEditing] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);

  async function remove() {
    try {
      await api.del(`/api/v1/me/store/lessons/${lesson.ulid}`);
      await onChange();
    } catch {
      toast.error("Couldn't delete the lesson");
    }
  }

  async function upload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      const form = new FormData();
      form.append("file", file);
      await api.postMultipart(
        `/api/v1/me/store/lessons/${lesson.ulid}/attachment`,
        form,
      );
      toast.success("Attachment uploaded");
      await onChange();
    } catch {
      toast.error("Upload failed");
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  }

  if (editing) {
    return (
      <li>
        <LessonForm
          lesson={lesson}
          onDone={async () => {
            setEditing(false);
            await onChange();
          }}
          onCancel={() => setEditing(false)}
        />
      </li>
    );
  }

  return (
    <li className="flex items-center gap-2 rounded-lg border p-2.5">
      <span className="flex min-w-0 flex-1 flex-col">
        <span className="truncate text-sm font-medium text-text-primary">
          {lesson.title}
        </span>
        <span className="text-[11px] text-text-secondary">
          {lesson.is_preview ? "Free preview" : "Members only"}
          {lesson.has_attachment ? " · attachment" : ""}
        </span>
      </span>
      <input ref={fileRef} type="file" className="hidden" onChange={(e) => void upload(e)} />
      <button
        type="button"
        aria-label="Upload attachment"
        onClick={() => fileRef.current?.click()}
        disabled={uploading}
        className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-teal-text disabled:opacity-60"
      >
        <Upload className="size-4" aria-hidden />
      </button>
      <button
        type="button"
        onClick={() => setEditing(true)}
        className="text-[12px] font-medium text-teal-text hover:underline"
      >
        Edit
      </button>
      <button
        type="button"
        aria-label={`Delete ${lesson.title}`}
        onClick={() => void remove()}
        className="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-destructive"
      >
        <Trash2 className="size-4" aria-hidden />
      </button>
    </li>
  );
}

function LessonForm({
  moduleUlid,
  lesson,
  onDone,
  onCancel,
}: {
  moduleUlid?: string;
  lesson?: BuilderLesson;
  onDone: () => Promise<void>;
  onCancel: () => void;
}) {
  const [title, setTitle] = useState(lesson?.title ?? "");
  const [body, setBody] = useState(lesson?.body ?? "");
  const [videoUrl, setVideoUrl] = useState(lesson?.video_url ?? "");
  const [preview, setPreview] = useState(lesson?.is_preview ?? false);
  const [busy, setBusy] = useState(false);

  async function save() {
    if (title.trim() === "" || busy) return;
    setBusy(true);
    try {
      const payload = {
        title: title.trim(),
        body: body.trim() || null,
        video_url: videoUrl.trim() || null,
        is_preview: preview,
      };
      if (lesson) {
        await api.patch(`/api/v1/me/store/lessons/${lesson.ulid}`, payload);
      } else {
        await api.post(`/api/v1/me/store/modules/${moduleUlid}/lessons`, payload);
      }
      await onDone();
    } catch {
      toast.error("Couldn't save the lesson");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-teal/30 bg-sage/8 p-3">
      <Input
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        placeholder="Lesson title"
        className="h-10"
      />
      <Textarea
        value={body}
        onChange={(e) => setBody(e.target.value)}
        placeholder="Lesson content (optional)"
        rows={3}
      />
      <Input
        value={videoUrl}
        onChange={(e) => setVideoUrl(e.target.value)}
        placeholder="Video URL — YouTube/Vimeo (optional)"
        className="h-10"
      />
      <label className="flex items-center gap-2 text-[13px] text-text-secondary">
        <input
          type="checkbox"
          className="size-4 accent-teal"
          checked={preview}
          onChange={(e) => setPreview(e.target.checked)}
        />
        Free preview (visible without purchase)
      </label>
      <div className="flex gap-2">
        <Button
          type="button"
          className="h-9"
          onClick={() => void save()}
          disabled={busy || title.trim() === ""}
        >
          {lesson ? "Save lesson" : "Add lesson"}
        </Button>
        <Button type="button" variant="ghost" className="h-9" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </div>
  );
}

function BackLink() {
  return (
    <Link
      href="/settings"
      className="flex w-fit items-center gap-1 text-[13px] font-medium text-teal-text hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden />
      Back to settings
    </Link>
  );
}

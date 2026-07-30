"use client";

import { Radio, Trash2 } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { ApiError } from "@/lib/api/client";
import {
  createMasterclass,
  deleteMasterclass,
  listMyMasterclasses,
  updateMasterclass,
  type MyMasterclass,
} from "@/lib/masterclasses";

/**
 * Facilitator self-serve masterclass authoring. Shown in profile settings when
 * the active profile is a facilitator — mirrors the vendor StoreSettings.
 */
export function MasterclassSettings() {
  const [classes, setClasses] = useState<MyMasterclass[] | null>(null);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [startsAt, setStartsAt] = useState("");
  const [endsAt, setEndsAt] = useState("");
  const [capacity, setCapacity] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let cancelled = false;
    listMyMasterclasses()
      .then((res) => {
        if (!cancelled) setClasses(res.data);
      })
      .catch(() => {
        if (!cancelled) setClasses([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  async function create() {
    if (!title.trim() || !description.trim() || !startsAt || !endsAt) return;
    setSaving(true);
    try {
      const res = await createMasterclass({
        title: title.trim(),
        description: description.trim(),
        starts_at: new Date(startsAt).toISOString(),
        ends_at: new Date(endsAt).toISOString(),
        capacity: capacity ? Number(capacity) : null,
        is_published: false,
      });
      setClasses((prev) => [res.data, ...(prev ?? [])]);
      setTitle("");
      setDescription("");
      setStartsAt("");
      setEndsAt("");
      setCapacity("");
      toast.success("Masterclass created as a draft.");
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.message : "Couldn't create masterclass",
      );
    } finally {
      setSaving(false);
    }
  }

  async function togglePublish(m: MyMasterclass) {
    try {
      const res = await updateMasterclass(m.ulid, {
        is_published: !m.is_published,
      });
      setClasses((prev) =>
        (prev ?? []).map((c) => (c.ulid === m.ulid ? res.data : c)),
      );
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.message : "Couldn't update masterclass",
      );
    }
  }

  async function remove(m: MyMasterclass) {
    const prev = classes ?? [];
    setClasses(prev.filter((c) => c.ulid !== m.ulid));
    try {
      await deleteMasterclass(m.ulid);
    } catch {
      setClasses(prev);
      toast.error("Couldn't delete masterclass");
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Masterclasses</CardTitle>
        <CardDescription>
          Create and run your own cohort programmes. Drafts stay hidden until
          you publish them.
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        {classes && classes.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {classes.map((m) => (
              <li
                key={m.ulid}
                className="flex items-center gap-3 rounded-lg border p-3"
              >
                <span className="flex min-w-0 flex-1 flex-col">
                  <span className="truncate text-sm font-medium">
                    {m.title}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {m.participants_count} enrolled · {m.status}
                    {m.is_published ? "" : " · draft"}
                  </span>
                </span>
                {m.is_published ? (
                  <Button asChild variant="ghost" size="sm" className="gap-1.5">
                    <Link href={`/masterclasses/${m.ulid}`}>
                      <Radio className="size-4" aria-hidden />
                      Open
                    </Link>
                  </Button>
                ) : null}
                <label className="flex items-center gap-1.5 text-xs">
                  <Switch
                    checked={m.is_published}
                    onCheckedChange={() => void togglePublish(m)}
                  />
                  Live
                </label>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={`Delete ${m.title}`}
                  onClick={() => void remove(m)}
                >
                  <Trash2 className="size-4 text-danger" aria-hidden />
                </Button>
              </li>
            ))}
          </ul>
        ) : classes ? (
          <p className="text-sm text-muted-foreground">
            You haven&apos;t created any masterclasses yet.
          </p>
        ) : null}

        <div className="flex flex-col gap-3 border-t pt-4">
          <p className="text-sm font-medium">New masterclass</p>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mc-title">Title</Label>
            <Input
              id="mc-title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="e.g. Founder Sales Sprint"
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mc-desc">Description</Label>
            <Textarea
              id="mc-desc"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={3}
            />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="mc-start">Starts</Label>
              <Input
                id="mc-start"
                type="datetime-local"
                value={startsAt}
                onChange={(e) => setStartsAt(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="mc-end">Ends</Label>
              <Input
                id="mc-end"
                type="datetime-local"
                value={endsAt}
                onChange={(e) => setEndsAt(e.target.value)}
              />
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="mc-cap">
              Capacity{" "}
              <span className="text-muted-foreground">(optional)</span>
            </Label>
            <Input
              id="mc-cap"
              type="number"
              min={1}
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
              placeholder="Unlimited"
            />
          </div>
          <Button
            type="button"
            className="h-11 sm:self-start"
            onClick={() => void create()}
            disabled={
              saving ||
              !title.trim() ||
              !description.trim() ||
              !startsAt ||
              !endsAt
            }
          >
            {saving ? "Creating…" : "Create draft"}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

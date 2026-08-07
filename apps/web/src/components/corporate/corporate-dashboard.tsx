"use client";

import { Building2, Plus, Users } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { ApiError } from "@/lib/api/client";
import {
  createProgramme,
  listProgrammes,
  PROGRAMME_TYPE_LABELS,
  STATUS_LABELS,
  type ProgrammeListItem,
  type ProgrammeType,
} from "@/lib/esd";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "outline"> = {
  active: "default",
  draft: "secondary",
  closed: "outline",
};

export function CorporateDashboard() {
  const [programmes, setProgrammes] = useState<ProgrammeListItem[] | null>(null);

  useEffect(() => {
    let active = true;
    listProgrammes()
      .then((res) => active && setProgrammes(res.data))
      .catch(() => active && setProgrammes([]));
    return () => {
      active = false;
    };
  }, []);

  function onCreated(programme: ProgrammeListItem) {
    setProgrammes((prev) => [programme, ...(prev ?? [])]);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm text-muted-foreground">
          Run your supplier &amp; enterprise development programmes.
        </p>
        <CreateProgrammeDialog onCreated={onCreated} />
      </div>

      {programmes === null ? (
        <div className="flex flex-col gap-2">
          <Skeleton className="h-20 w-full" />
          <Skeleton className="h-20 w-full" />
        </div>
      ) : programmes.length === 0 ? (
        <EmptyState
          icon={Building2}
          title="No programmes yet"
          description="Create your first supplier-development programme to start onboarding suppliers."
        />
      ) : (
        <ul className="flex flex-col gap-2">
          {programmes.map((programme) => (
            <li key={programme.ulid}>
              <Link href={`/corporate/programmes/${programme.ulid}`}>
                <Card className="flex items-center justify-between gap-3 p-4 transition-colors hover:bg-accent/50">
                  <div className="flex flex-col gap-1">
                    <span className="font-medium">{programme.name}</span>
                    <span className="text-xs text-muted-foreground">
                      {PROGRAMME_TYPE_LABELS[programme.type]}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="flex items-center gap-1 text-xs text-muted-foreground">
                      <Users className="size-3.5" aria-hidden />
                      {programme.cohorts_count}
                    </span>
                    <Badge variant={STATUS_VARIANT[programme.status] ?? "outline"}>
                      {STATUS_LABELS[programme.status] ?? programme.status}
                    </Badge>
                  </div>
                </Card>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function CreateProgrammeDialog({
  onCreated,
}: {
  onCreated: (programme: ProgrammeListItem) => void;
}) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [type, setType] = useState<ProgrammeType>("supplier_development");
  const [saving, setSaving] = useState(false);

  async function submit() {
    if (!name.trim()) return;
    setSaving(true);
    try {
      const res = await createProgramme({ name: name.trim(), type });
      onCreated(res.data);
      toast.success("Programme created");
      setName("");
      setOpen(false);
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Could not create programme");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm">
          <Plus className="size-4" aria-hidden />
          New programme
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New programme</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-3">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="programme-name">Name</Label>
            <Input
              id="programme-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Township Supplier Accelerator"
              maxLength={160}
            />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="programme-type">Type</Label>
            <Select value={type} onValueChange={(v) => setType(v as ProgrammeType)}>
              <SelectTrigger id="programme-type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="supplier_development">
                  {PROGRAMME_TYPE_LABELS.supplier_development}
                </SelectItem>
                <SelectItem value="enterprise_development">
                  {PROGRAMME_TYPE_LABELS.enterprise_development}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <DialogFooter>
          <Button onClick={submit} disabled={saving || !name.trim()}>
            Create
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

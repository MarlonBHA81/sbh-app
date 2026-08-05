"use client";

import { Trash2 } from "lucide-react";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import * as api from "@/lib/api/client";
import type { Profile, ProfileRole, TeamMember } from "@/lib/api/types";

/**
 * Team management for a business profile (Roles P4). Owners and managers can add
 * members by handle, change their role, and remove them. The owner is shown but
 * can't be changed or removed.
 */
export function BusinessTeamSettings({ profile }: { profile: Profile }) {
  const canManage =
    !profile.my_role ||
    profile.my_role === "owner" ||
    profile.my_role === "manager";

  const [members, setMembers] = useState<TeamMember[] | null>(null);
  const [handle, setHandle] = useState("");
  const [role, setRole] = useState<ProfileRole>("poster");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const data = (
          await api.get<{ data: TeamMember[] }>(
            `/api/v1/me/profiles/${profile.ulid}/members`,
          )
        ).data;
        if (!cancelled) setMembers(data);
      } catch {
        if (!cancelled) setMembers([]);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [profile.ulid]);

  async function addMember() {
    const cleaned = handle.trim().replace(/^@/, "");
    if (!cleaned || busy) return;
    setBusy(true);
    try {
      const data = (
        await api.post<{ data: TeamMember[] }>(
          `/api/v1/me/profiles/${profile.ulid}/members`,
          { handle: cleaned, role },
        )
      ).data;
      setMembers(data);
      setHandle("");
      toast.success("Team updated");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't add member",
      );
    } finally {
      setBusy(false);
    }
  }

  async function removeMember(memberHandle: string) {
    if (busy) return;
    setBusy(true);
    try {
      await api.del(
        `/api/v1/me/profiles/${profile.ulid}/members/${memberHandle}`,
      );
      setMembers((prev) => prev?.filter((m) => m.handle !== memberHandle) ?? null);
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't remove member",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Team</CardTitle>
        <CardDescription>
          Give people access to post and manage this business profile.
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <ul className="flex flex-col gap-2">
          {(members ?? []).map((member) => (
            <li
              key={member.handle ?? member.name}
              className="flex min-h-11 items-center gap-3 rounded-lg border p-3"
            >
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">
                  {member.name}
                </span>
                {member.handle ? (
                  <span className="truncate text-xs text-muted-foreground">
                    @{member.handle}
                  </span>
                ) : null}
              </span>
              <span className="shrink-0 text-xs font-medium text-muted-foreground capitalize">
                {member.role}
              </span>
              {canManage && member.role !== "owner" && member.handle ? (
                <button
                  type="button"
                  aria-label={`Remove ${member.name}`}
                  disabled={busy}
                  onClick={() => void removeMember(member.handle!)}
                  className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-destructive disabled:opacity-50"
                >
                  <Trash2 className="size-4" aria-hidden />
                </button>
              ) : null}
            </li>
          ))}
          {members !== null && members.length === 0 ? (
            <li className="text-sm text-muted-foreground">
              No team members yet.
            </li>
          ) : null}
        </ul>

        {canManage ? (
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              value={handle}
              onChange={(e) => setHandle(e.target.value)}
              placeholder="Add by @handle"
              className="h-11 flex-1"
              autoCapitalize="none"
              autoCorrect="off"
            />
            <Select
              value={role}
              onValueChange={(v) => setRole(v as ProfileRole)}
            >
              <SelectTrigger className="h-11 sm:w-36">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="poster">Poster</SelectItem>
                <SelectItem value="manager">Manager</SelectItem>
              </SelectContent>
            </Select>
            <Button
              type="button"
              className="h-11"
              disabled={busy || handle.trim() === ""}
              onClick={() => void addMember()}
            >
              Add
            </Button>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}

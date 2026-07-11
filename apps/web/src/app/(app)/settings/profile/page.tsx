"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Ban, ChevronRight, CircleAlert, VolumeX } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";

import { PushSettings } from "@/components/notifications/push-settings";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import type { DmPrivacy, Profile } from "@/lib/api/types";
import { BUSINESS_CATEGORIES } from "@/lib/categories";
import { applyServerErrors, errorMessage } from "@/lib/forms";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

const schema = z.object({
  name: z.string().min(2, "Enter a name"),
  bio: z.string().max(500, "Keep it under 500 characters"),
  category: z.string(),
  website: z
    .union([z.url("Enter a valid URL (include https://)"), z.literal("")])
    .optional(),
  location: z.string().max(120),
  is_private: z.boolean(),
});

type Values = z.infer<typeof schema>;

function valuesFromProfile(profile: Profile): Values {
  return {
    name: profile.name,
    bio: profile.bio ?? "",
    category: profile.category ?? "",
    website: profile.website ?? "",
    location: profile.location ?? "",
    is_private: profile.is_private,
  };
}

function ContentSettings() {
  const showSensitive = useAuthStore((s) =>
    Boolean(s.user?.settings?.show_sensitive),
  );
  const currentSettings = useAuthStore((s) => s.user?.settings ?? null);
  const updateUserSettings = useAuthStore((s) => s.updateUserSettings);
  const [busy, setBusy] = useState(false);

  async function toggle(next: boolean) {
    if (busy) return;
    setBusy(true);
    // Optimistic: flip the auth-store user immediately.
    updateUserSettings({ show_sensitive: next });
    try {
      await api.patch("/api/v1/me", {
        settings: { ...(currentSettings ?? {}), show_sensitive: next },
      });
    } catch (error) {
      updateUserSettings({ show_sensitive: !next });
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update setting",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Content</CardTitle>
        <CardDescription>Control what you see in your feeds.</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="flex min-h-11 items-center justify-between gap-4 rounded-lg border p-4">
          <div className="space-y-0.5">
            <p className="text-sm font-medium">Show sensitive content</p>
            <p className="text-sm text-muted-foreground">
              Show posts marked as sensitive without a warning overlay.
            </p>
          </div>
          <Switch
            checked={showSensitive}
            disabled={busy}
            onCheckedChange={(checked) => void toggle(checked)}
            aria-label="Show sensitive content"
          />
        </div>
      </CardContent>
    </Card>
  );
}

const DM_PRIVACY_OPTIONS: { value: DmPrivacy; label: string; hint: string }[] = [
  { value: "everyone", label: "Everyone", hint: "Anyone can message you" },
  {
    value: "followers",
    label: "People I follow",
    hint: "Only accounts you follow can start a chat",
  },
  { value: "no_one", label: "No one", hint: "Turn off new direct messages" },
];

function MessagingSettings() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const updateActiveProfile = useAuthStore((s) => s.updateActiveProfile);
  const [busy, setBusy] = useState(false);
  const value: DmPrivacy = activeProfile?.dm_privacy ?? "everyone";

  async function choose(next: DmPrivacy) {
    if (busy || !activeProfile || next === value) return;
    setBusy(true);
    const previous = activeProfile;
    // Optimistic: reflect the choice immediately.
    updateActiveProfile({ ...activeProfile, dm_privacy: next });
    try {
      const res = await api.patch<{ data: Profile }>(
        `/api/v1/me/profiles/${activeProfile.ulid}`,
        { dm_privacy: next },
      );
      updateActiveProfile(res.data);
    } catch (error) {
      updateActiveProfile(previous);
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update messaging setting",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Messaging</CardTitle>
        <CardDescription>Choose who can start a direct message.</CardDescription>
      </CardHeader>
      <CardContent>
        <RadioGroup
          value={value}
          onValueChange={(next) => void choose(next as DmPrivacy)}
          className="gap-2"
        >
          {DM_PRIVACY_OPTIONS.map((option) => (
            <Label
              key={option.value}
              htmlFor={`dm-${option.value}`}
              className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-3 has-[[data-state=checked]]:border-primary/50 has-[[data-state=checked]]:bg-accent/40"
            >
              <RadioGroupItem
                id={`dm-${option.value}`}
                value={option.value}
                disabled={busy}
                className="mt-0.5"
              />
              <span className="flex flex-col gap-0.5">
                <span className="text-sm font-medium">{option.label}</span>
                <span className="text-sm text-muted-foreground">
                  {option.hint}
                </span>
              </span>
            </Label>
          ))}
        </RadioGroup>
      </CardContent>
    </Card>
  );
}

function PrivacySafetySettings() {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Privacy &amp; safety</CardTitle>
        <CardDescription>
          Manage the accounts you&apos;ve blocked or muted.
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-2">
        <Link
          href="/settings/blocked"
          className="flex min-h-11 items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent/40"
        >
          <Ban className="size-5 shrink-0 text-muted-foreground" aria-hidden />
          <span className="flex-1 text-sm font-medium">Blocked accounts</span>
          <ChevronRight
            className="size-4 shrink-0 text-muted-foreground"
            aria-hidden
          />
        </Link>
        <Link
          href="/settings/muted"
          className="flex min-h-11 items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent/40"
        >
          <VolumeX
            className="size-5 shrink-0 text-muted-foreground"
            aria-hidden
          />
          <span className="flex-1 text-sm font-medium">Muted accounts</span>
          <ChevronRight
            className="size-4 shrink-0 text-muted-foreground"
            aria-hidden
          />
        </Link>
      </CardContent>
    </Card>
  );
}

export default function ProfileSettingsPage() {
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const updateActiveProfile = useAuthStore((s) => s.updateActiveProfile);

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      bio: "",
      category: "",
      website: "",
      location: "",
      is_private: false,
    },
  });

  // Populate once the active profile is available / when switching profiles.
  useEffect(() => {
    if (activeProfile) form.reset(valuesFromProfile(activeProfile));
  }, [activeProfile, form]);

  if (!activeProfile) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-96 w-full rounded-xl" />
      </div>
    );
  }

  async function onSubmit(values: Values) {
    if (!activeProfile) return;
    form.clearErrors("root");
    try {
      const res = await api.patch<{ data: Profile }>(
        `/api/v1/me/profiles/${activeProfile.ulid}`,
        {
          name: values.name,
          bio: values.bio || null,
          category: values.category || null,
          website: values.website || null,
          location: values.location || null,
          is_private: values.is_private,
        },
      );
      updateActiveProfile(res.data);
      form.reset(valuesFromProfile(res.data));
      toast.success("Profile updated");
    } catch (error) {
      if (!applyServerErrors(error, form.setError)) {
        form.setError("root", { message: errorMessage(error) });
      }
    }
  }

  const rootError = form.formState.errors.root?.message;

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">Edit profile</h1>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">@{activeProfile.handle}</CardTitle>
          <CardDescription>
            {activeProfile.kind === "business"
              ? "Business profile"
              : "Personal profile"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {rootError ? (
            <Alert variant="destructive" className="mb-4">
              <CircleAlert />
              <AlertDescription>{rootError}</AlertDescription>
            </Alert>
          ) : null}
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit(onSubmit)}
              className="flex flex-col gap-5"
              noValidate
            >
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Name</FormLabel>
                    <FormControl>
                      <Input className="h-11" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="bio"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Bio</FormLabel>
                    <FormControl>
                      <Textarea
                        rows={4}
                        placeholder="Tell people what you do"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="category"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Category</FormLabel>
                    <Select onValueChange={field.onChange} value={field.value}>
                      <FormControl>
                        <SelectTrigger className="h-11 w-full">
                          <SelectValue placeholder="Pick a category" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {BUSINESS_CATEGORIES.map((category) => (
                          <SelectItem key={category} value={category}>
                            {category}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="website"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Website</FormLabel>
                    <FormControl>
                      <Input
                        type="url"
                        inputMode="url"
                        placeholder="https://yourbusiness.com"
                        className="h-11"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="location"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Location</FormLabel>
                    <FormControl>
                      <Input
                        placeholder="Sydney, Australia"
                        className="h-11"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_private"
                render={({ field }) => (
                  <FormItem className="flex min-h-11 flex-row items-center justify-between gap-4 rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <FormLabel>Private account</FormLabel>
                      <FormDescription>
                        New followers must be approved before they can see your
                        posts.
                      </FormDescription>
                    </div>
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        aria-label="Private account"
                      />
                    </FormControl>
                  </FormItem>
                )}
              />
              <Button
                type="submit"
                className="h-11 sm:self-start"
                disabled={
                  form.formState.isSubmitting || !form.formState.isDirty
                }
              >
                {form.formState.isSubmitting ? "Saving…" : "Save changes"}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
      <ContentSettings />
      <MessagingSettings />
      <PrivacySafetySettings />
      <PushSettings />
    </div>
  );
}

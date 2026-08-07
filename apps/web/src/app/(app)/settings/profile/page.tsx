"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { Ban, ChevronRight, CircleAlert, MapPin, VolumeX } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";

import { PushSettings } from "@/components/notifications/push-settings";
import { BusinessTeamSettings } from "@/components/settings/business-team-settings";
import { BusinessVerificationSettings } from "@/components/settings/business-verification-settings";
import { MasterclassSettings } from "@/components/settings/masterclass-settings";
import { ProfileImageUpload } from "@/components/settings/profile-image-upload";
import { StoreSettings } from "@/components/settings/store-settings";
import { DataPrivacySettings } from "@/components/settings/data-privacy-settings";
import { LanguagePicker } from "@/components/settings/language-picker";
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
import { CategoryChip } from "@/components/business/category-chip";
import { useBusinessCategories } from "@/hooks/use-business-categories";
import * as api from "@/lib/api/client";
import type { DmPrivacy, Profile } from "@/lib/api/types";
import { BUSINESS_CATEGORIES } from "@/lib/categories";
import { JOURNEY_STAGES } from "@/lib/journey";
import { applyServerErrors, errorMessage } from "@/lib/forms";
import { useAuthStore } from "@/lib/stores/auth-store-provider";
import { useLocationSharing } from "@/lib/use-location-sharing";

const schema = z.object({
  name: z.string().min(2, "Enter a name"),
  bio: z.string().max(500, "Keep it under 500 characters"),
  category: z.string(),
  journey_stage: z.string(),
  website: z
    .union([z.url("Enter a valid URL (include https://)"), z.literal("")])
    .optional(),
  location: z.string().max(120),
  is_private: z.boolean(),
  is_mentor: z.boolean(),
  linkedin: z
    .union([
      z.string().regex(/^https:\/\/(www\.)?linkedin\.com\/.+/i, "Enter a full LinkedIn URL (https://linkedin.com/...)"),
      z.literal(""),
    ])
    .optional(),
  facebook: z
    .union([
      z.string().regex(/^https:\/\/(www\.)?(facebook\.com|fb\.com)\/.+/i, "Enter a full Facebook URL (https://facebook.com/...)"),
      z.literal(""),
    ])
    .optional(),
  instagram: z
    .union([
      z.string().regex(/^https:\/\/(www\.)?instagram\.com\/.+/i, "Enter a full Instagram URL (https://instagram.com/...)"),
      z.literal(""),
    ])
    .optional(),
  whatsapp: z
    .union([
      z.string().regex(/^(\+?[0-9 ()\-]{6,20}|https:\/\/wa\.me\/[0-9]{6,15})$/, "Enter a WhatsApp number (e.g. +27 82 123 4567) or wa.me link"),
      z.literal(""),
    ])
    .optional(),
});

type Values = z.infer<typeof schema>;

function valuesFromProfile(profile: Profile): Values {
  return {
    name: profile.name,
    bio: profile.bio ?? "",
    category: profile.category ?? "",
    journey_stage: profile.journey_stage ?? "",
    website: profile.website ?? "",
    location: profile.location ?? "",
    is_private: profile.is_private,
    is_mentor: profile.is_mentor ?? false,
    linkedin: profile.social_links?.linkedin ?? "",
    facebook: profile.social_links?.facebook ?? "",
    instagram: profile.social_links?.instagram ?? "",
    whatsapp: profile.social_links?.whatsapp ?? "",
  };
}

function ContentSettings() {
  const t = useTranslations("settings");
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
        <CardTitle className="text-base">{t("content")}</CardTitle>
        <CardDescription>{t("contentHint")}</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="flex min-h-11 items-center justify-between gap-4 rounded-lg border p-4">
          <div className="space-y-0.5">
            <p className="text-sm font-medium">{t("showSensitive")}</p>
            <p className="text-sm text-muted-foreground">
              {t("showSensitiveHint")}
            </p>
          </div>
          <Switch
            checked={showSensitive}
            disabled={busy}
            onCheckedChange={(checked) => void toggle(checked)}
            aria-label={t("showSensitive")}
          />
        </div>
      </CardContent>
    </Card>
  );
}

const DM_PRIVACY_OPTIONS: { value: DmPrivacy; labelKey: string; hintKey: string }[] =
  [
    { value: "everyone", labelKey: "dmEveryone", hintKey: "dmEveryoneHint" },
    { value: "followers", labelKey: "dmFollowers", hintKey: "dmFollowersHint" },
    { value: "no_one", labelKey: "dmNoOne", hintKey: "dmNoOneHint" },
  ];

function MessagingSettings() {
  const t = useTranslations("settings");
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
        <CardTitle className="text-base">{t("messaging")}</CardTitle>
        <CardDescription>{t("messagingHint")}</CardDescription>
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
                <span className="text-sm font-medium">{t(option.labelKey)}</span>
                <span className="text-sm text-muted-foreground">
                  {t(option.hintKey)}
                </span>
              </span>
            </Label>
          ))}
        </RadioGroup>
      </CardContent>
    </Card>
  );
}

/** Business-only: pick the structured business category (Milestone 10). */
function BusinessCategorySettings() {
  const t = useTranslations("settings");
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const updateActiveProfile = useAuthStore((s) => s.updateActiveProfile);
  const { categories, phase } = useBusinessCategories();
  const [busy, setBusy] = useState(false);

  const current = activeProfile?.business_category ?? null;

  async function choose(nextId: string) {
    if (busy || !activeProfile) return;
    const id = Number(nextId);
    if (current && current.id === id) return;
    setBusy(true);
    const previous = activeProfile;
    const chosen = categories.find((c) => c.id === id) ?? null;
    // Optimistic: reflect the choice immediately.
    updateActiveProfile({ ...activeProfile, business_category: chosen });
    try {
      const res = await api.patch<{ data: Profile }>(
        `/api/v1/me/profiles/${activeProfile.ulid}`,
        { business_category_id: id },
      );
      updateActiveProfile(res.data);
      toast.success("Business category updated");
    } catch (error) {
      updateActiveProfile(previous);
      toast.error(
        error instanceof api.ApiError
          ? error.message
          : "Couldn't update business category",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("businessCategory")}</CardTitle>
        <CardDescription>{t("businessCategoryHint")}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        {current ? (
          <div>
            <CategoryChip category={current} />
          </div>
        ) : null}
        <Select
          value={current ? String(current.id) : undefined}
          onValueChange={(value) => void choose(value)}
          disabled={busy || phase !== "loaded"}
        >
          <SelectTrigger className="h-11 w-full">
            <SelectValue
              placeholder={
                phase === "loading"
                  ? "Loading categories…"
                  : "Pick a business category"
              }
            />
          </SelectTrigger>
          <SelectContent>
            {categories.map((category) => (
              <SelectItem key={category.id} value={String(category.id)}>
                <span className="flex items-center gap-1.5">
                  <span aria-hidden>{category.icon ?? "🏢"}</span>
                  {category.name}
                </span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </CardContent>
    </Card>
  );
}

function PrivacySafetySettings() {
  const t = useTranslations("settings");
  const { sharing, busy, enable, disable } = useLocationSharing();

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("privacySafety")}</CardTitle>
        <CardDescription>{t("privacySafetyHint")}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-2">
        <div className="flex min-h-11 items-center justify-between gap-4 rounded-lg border p-4">
          <div className="flex items-start gap-3">
            <MapPin
              className="mt-0.5 size-5 shrink-0 text-muted-foreground"
              aria-hidden
            />
            <div className="space-y-0.5">
              <p className="text-sm font-medium">{t("locationSharing")}</p>
              <p className="text-sm text-muted-foreground">
                {t("locationSharingHint")}
              </p>
            </div>
          </div>
          <Switch
            checked={sharing}
            disabled={busy}
            onCheckedChange={(checked) =>
              void (checked ? enable() : disable())
            }
            aria-label={t("locationSharing")}
          />
        </div>
        <Link
          href="/settings/blocked"
          className="flex min-h-11 items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent/40"
        >
          <Ban className="size-5 shrink-0 text-muted-foreground" aria-hidden />
          <span className="flex-1 text-sm font-medium">
            {t("blockedAccounts")}
          </span>
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
          <span className="flex-1 text-sm font-medium">
            {t("mutedAccounts")}
          </span>
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
  const t = useTranslations("settings");
  const tc = useTranslations("common");
  const activeProfile = useAuthStore((s) => s.activeProfile);
  const updateActiveProfile = useAuthStore((s) => s.updateActiveProfile);

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      bio: "",
      category: "",
      journey_stage: "",
      website: "",
      location: "",
      is_private: false,
      is_mentor: false,
      linkedin: "",
      facebook: "",
      instagram: "",
      whatsapp: "",
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
          journey_stage: values.journey_stage || null,
          website: values.website || null,
          location: values.location || null,
          is_private: values.is_private,
          is_mentor: values.is_mentor,
          social_links: {
            linkedin: values.linkedin || null,
            facebook: values.facebook || null,
            instagram: values.instagram || null,
            whatsapp: values.whatsapp || null,
          },
        },
      );
      updateActiveProfile(res.data);
      form.reset(valuesFromProfile(res.data));
      toast.success(t("profileUpdated"));
    } catch (error) {
      if (!applyServerErrors(error, form.setError)) {
        form.setError("root", { message: errorMessage(error) });
      }
    }
  }

  const rootError = form.formState.errors.root?.message;

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-semibold tracking-tight">
        {t("editProfile")}
      </h1>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">@{activeProfile.handle}</CardTitle>
          <CardDescription>
            {activeProfile.kind === "business"
              ? t("businessProfile")
              : t("personalProfile")}
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
              {activeProfile.kind === "business" ? (
                <div className="flex flex-col gap-1.5">
                  <span className="text-sm font-medium">Cover banner</span>
                  <ProfileImageUpload
                    profile={activeProfile}
                    kind="cover"
                    onUpdated={updateActiveProfile}
                  />
                </div>
              ) : null}
              <div className="flex flex-col gap-1.5">
                <span className="text-sm font-medium">Profile photo</span>
                <ProfileImageUpload
                  profile={activeProfile}
                  kind="avatar"
                  onUpdated={updateActiveProfile}
                />
              </div>
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("name")}</FormLabel>
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
                    <FormLabel>{t("bio")}</FormLabel>
                    <FormControl>
                      <Textarea
                        rows={4}
                        placeholder={t("bioPlaceholder")}
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
                    <FormLabel>{t("category")}</FormLabel>
                    <Select onValueChange={field.onChange} value={field.value}>
                      <FormControl>
                        <SelectTrigger className="h-11 w-full">
                          <SelectValue placeholder={t("pickCategory")} />
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
                name="journey_stage"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Business journey stage</FormLabel>
                    <Select onValueChange={field.onChange} value={field.value}>
                      <FormControl>
                        <SelectTrigger className="h-11 w-full">
                          <SelectValue placeholder="Where are you in your journey?" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {JOURNEY_STAGES.map((s) => (
                          <SelectItem key={s.key} value={s.key}>
                            {s.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormDescription>
                      Tailors your Home, opportunities and learning.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="website"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("website")}</FormLabel>
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
              {(
                [
                  ["linkedin", "LinkedIn", "https://linkedin.com/in/yourname"],
                  ["facebook", "Facebook", "https://facebook.com/yourpage"],
                  ["instagram", "Instagram", "https://instagram.com/yourhandle"],
                  ["whatsapp", "WhatsApp", "+27 82 123 4567"],
                ] as const
              ).map(([fieldName, label, placeholder]) => (
                <FormField
                  key={fieldName}
                  control={form.control}
                  name={fieldName}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{label}</FormLabel>
                      <FormControl>
                        <Input
                          inputMode={fieldName === "whatsapp" ? "tel" : "url"}
                          placeholder={placeholder}
                          className="h-11"
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ))}
              <FormField
                control={form.control}
                name="location"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("location")}</FormLabel>
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
                      <FormLabel>{t("privateAccount")}</FormLabel>
                      <FormDescription>
                        {t("privateAccountHint")}
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
              <FormField
                control={form.control}
                name="is_mentor"
                render={({ field }) => (
                  <FormItem className="flex min-h-11 flex-row items-center justify-between gap-4 rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <FormLabel>Available as a mentor</FormLabel>
                      <FormDescription>
                        Show up in Mentors so members you can help can reach you.
                      </FormDescription>
                    </div>
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        aria-label="Available as a mentor"
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
                {form.formState.isSubmitting ? tc("saving") : t("saveChanges")}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
      <LanguagePicker />
      {activeProfile.kind === "business" ? (
        <>
          <BusinessCategorySettings />
          <BusinessVerificationSettings />
          <StoreSettings />
          <BusinessTeamSettings profile={activeProfile} />
        </>
      ) : null}
      {activeProfile.is_facilitator ? <MasterclassSettings /> : null}
      <ContentSettings />
      <MessagingSettings />
      <PrivacySafetySettings />
      <PushSettings />
      <DataPrivacySettings />
    </div>
  );
}

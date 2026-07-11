"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { CircleAlert } from "lucide-react";
import { useEffect } from "react";
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
import type { Profile } from "@/lib/api/types";
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
      <PushSettings />
    </div>
  );
}

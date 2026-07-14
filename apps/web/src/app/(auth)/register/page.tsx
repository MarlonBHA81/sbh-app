"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, Check, CircleAlert, Lock } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { AuthDivider, SocialButtons } from "@/components/auth/social-buttons";
import { initials } from "@/components/profile-avatar";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { AppStatus, Profile } from "@/lib/api/types";
import { BRAND_COLORS, brandColorByKey } from "@/lib/brand-colors";
import { BUSINESS_CATEGORIES } from "@/lib/categories";
import { hasComposeDraft } from "@/lib/compose-draft";
import { applyServerErrors, errorMessage } from "@/lib/forms";
import {
  clearSignupDraft,
  readSignupDraft,
  writeSignupDraft,
} from "@/lib/signup-draft";
import {
  useAuthStore,
  useAuthStoreApi,
} from "@/lib/stores/auth-store-provider";

const credentialsSchema = z.object({
  email: z.email("Enter a valid email address"),
  password: z.string().min(8, "At least 8 characters"),
});

type Credentials = z.infer<typeof credentialsSchema>;

/** Live profile card that assembles as the user makes each choice. */
function ProfilePreview({
  name,
  industry,
  colorKey,
}: {
  name: string;
  industry: string;
  colorKey: string;
}) {
  const color = brandColorByKey(colorKey);
  const display = name.trim() || "Your business";
  return (
    <div className="overflow-hidden rounded-(--radius-card) border border-warmgray bg-card shadow-card">
      <div className="h-14 w-full" style={{ backgroundColor: color.hex }} />
      <div className="flex flex-col items-center gap-1 px-4 pb-4 text-center">
        <span
          className="-mt-7 flex size-14 items-center justify-center rounded-full font-heading text-lg font-semibold text-white ring-4 ring-card"
          style={{ backgroundColor: color.hex }}
        >
          {name.trim() ? initials(name) : "★"}
        </span>
        <span className="mt-1 truncate font-heading text-[15px] font-semibold text-text-primary">
          {display}
        </span>
        {industry ? (
          <span
            className="rounded-full px-2.5 py-0.5 text-[11px] font-medium"
            style={{ backgroundColor: `${color.hex}1f`, color: color.hex }}
          >
            {industry}
          </span>
        ) : (
          <span className="text-[12px] text-text-secondary">
            Pick an industry to start
          </span>
        )}
      </div>
    </div>
  );
}

export default function RegisterPage() {
  const t = useTranslations("auth");
  const register = useAuthStore((s) => s.register);
  const storeApi = useAuthStoreApi();
  const router = useRouter();

  const [registration, setRegistration] = useState<
    "checking" | "open" | "closed"
  >("checking");

  // Build-first choices (UX pattern 4), restored from localStorage so a reload
  // never loses what the user assembled. Lazy initializers read the draft once;
  // the wizard body only renders after the async registration check resolves
  // (the skeleton covers hydration), so there's no SSR/client mismatch.
  const [step, setStep] = useState(1);
  const [industry, setIndustry] = useState(() => readSignupDraft().industry ?? "");
  const [name, setName] = useState(() => readSignupDraft().name ?? "");
  const [colorKey, setColorKey] = useState(
    () => readSignupDraft().color ?? BRAND_COLORS[0].key,
  );
  const [rootError, setRootError] = useState<string | null>(null);

  useEffect(() => {
    writeSignupDraft({ industry, name, color: colorKey });
  }, [industry, name, colorKey]);

  useEffect(() => {
    let cancelled = false;
    api
      .get<{ data: AppStatus }>("/api/v1/status")
      .then((res) => {
        if (!cancelled) {
          setRegistration(res.data.registration_open ? "open" : "closed");
        }
      })
      .catch(() => {
        if (!cancelled) setRegistration("open");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const form = useForm<Credentials>({
    resolver: zodResolver(credentialsSchema),
    defaultValues: { email: "", password: "" },
  });

  async function onSubmitCredentials(values: Credentials) {
    setRootError(null);
    form.clearErrors("root");
    try {
      await register({ name: name.trim(), email: values.email, password: values.password });

      // Hydrate the industry the user picked onto their new profile.
      const profile = storeApi.getState().activeProfile;
      if (profile && industry) {
        try {
          await api.patch<{ data: Profile }>(
            `/api/v1/me/profiles/${profile.ulid}`,
            { category: industry },
          );
        } catch {
          // Non-fatal: they can set it later in profile settings.
        }
      }

      // Persist the chosen brand colour for cosmetic use.
      // BACKEND-TODO: no brand_color field exists on the profile yet; store it
      // client-side until the API can hold it, then PATCH it here too.
      try {
        window.localStorage.setItem("sbh:brand-color", colorKey);
      } catch {
        // ignore
      }

      clearSignupDraft();

      // Carry a compose draft through signup back into the composer.
      router.replace(hasComposeDraft() ? "/home?compose=draft" : "/home");
    } catch (error) {
      if (!applyServerErrors(error, form.setError)) {
        setRootError(errorMessage(error));
      }
    }
  }

  if (registration === "checking") {
    return (
      <Card className="w-full max-w-sm">
        <CardHeader>
          <Skeleton className="h-6 w-40" />
          <Skeleton className="h-4 w-56" />
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <Skeleton className="h-11 w-full" />
          <Skeleton className="h-11 w-full" />
          <Skeleton className="h-24 w-full" />
        </CardContent>
      </Card>
    );
  }

  if (registration === "closed") {
    return (
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          <div className="mb-1 flex size-12 items-center justify-center rounded-full bg-muted">
            <Lock className="size-6 text-muted-foreground" aria-hidden />
          </div>
          <CardTitle>Registration is temporarily closed</CardTitle>
          <CardDescription>
            We&apos;re not accepting new accounts right now. Please check back
            soon.
          </CardDescription>
        </CardHeader>
        <CardFooter className="justify-center text-sm text-muted-foreground">
          {t("alreadyHaveAccount")}{" "}
          <Link
            href="/login"
            className="ms-1 font-medium text-foreground underline-offset-4 hover:underline"
          >
            {t("signInLink")}
          </Link>
        </CardFooter>
      </Card>
    );
  }

  const totalSteps = 4;
  const canContinueStep1 = industry.length > 0;
  const canContinueStep2 = name.trim().length >= 2;

  return (
    <Card className="w-full max-w-sm">
      <CardHeader>
        <div className="flex items-center gap-2">
          {step > 1 ? (
            <button
              type="button"
              aria-label="Back"
              onClick={() => setStep((s) => s - 1)}
              className="flex size-8 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-accent active:scale-[0.98]"
            >
              <ArrowLeft className="size-4" aria-hidden />
            </button>
          ) : null}
          <CardTitle>Build your profile</CardTitle>
        </div>
        <CardDescription>
          Step {step} of {totalSteps} · set it up before you sign up
        </CardDescription>
        <div className="mt-1 flex gap-1.5" aria-hidden>
          {Array.from({ length: totalSteps }).map((_, i) => (
            <span
              key={i}
              className={
                i < step
                  ? "h-1.5 flex-1 rounded-full bg-teal"
                  : "h-1.5 flex-1 rounded-full bg-muted"
              }
            />
          ))}
        </div>
      </CardHeader>

      <CardContent className="flex flex-col gap-4">
        {step >= 2 ? (
          <ProfilePreview name={name} industry={industry} colorKey={colorKey} />
        ) : null}

        {step === 1 ? (
          <div className="flex flex-col gap-3">
            <p className="text-sm font-medium text-text-primary">
              What kind of business are you?
            </p>
            <div className="grid grid-cols-2 gap-2">
              {BUSINESS_CATEGORIES.map((cat) => {
                const selected = cat === industry;
                return (
                  <button
                    key={cat}
                    type="button"
                    onClick={() => setIndustry(cat)}
                    className={
                      selected
                        ? "flex min-h-11 items-center justify-between gap-1 rounded-xl border-2 border-teal bg-teal/8 px-3 py-2 text-start text-[13px] font-medium text-teal-text"
                        : "flex min-h-11 items-center rounded-xl border border-warmgray bg-card px-3 py-2 text-start text-[13px] text-text-primary transition-colors hover:bg-accent"
                    }
                  >
                    <span className="min-w-0 flex-1 truncate">{cat}</span>
                    {selected ? (
                      <Check className="size-4 shrink-0" aria-hidden />
                    ) : null}
                  </button>
                );
              })}
            </div>
            <Button
              type="button"
              className="h-11 w-full"
              disabled={!canContinueStep1}
              onClick={() => setStep(2)}
            >
              Next
            </Button>
          </div>
        ) : null}

        {step === 2 ? (
          <div className="flex flex-col gap-3">
            <label
              htmlFor="signup-name"
              className="text-sm font-medium text-text-primary"
            >
              Name your business
            </label>
            <Input
              id="signup-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Thabo's Coffee Roastery"
              className="h-11"
              autoFocus
            />
            <Button
              type="button"
              className="h-11 w-full"
              disabled={!canContinueStep2}
              onClick={() => setStep(3)}
            >
              Next
            </Button>
          </div>
        ) : null}

        {step === 3 ? (
          <div className="flex flex-col gap-3">
            <p className="text-sm font-medium text-text-primary">
              Choose your brand colour
            </p>
            <div className="flex flex-wrap gap-3">
              {BRAND_COLORS.map((color) => {
                const selected = color.key === colorKey;
                return (
                  <button
                    key={color.key}
                    type="button"
                    aria-label={color.label}
                    aria-pressed={selected}
                    onClick={() => setColorKey(color.key)}
                    className="flex size-12 items-center justify-center rounded-full ring-offset-2 ring-offset-card transition-transform active:scale-95"
                    style={{
                      backgroundColor: color.hex,
                      boxShadow: selected ? `0 0 0 3px ${color.hex}` : undefined,
                    }}
                  >
                    {selected ? (
                      <Check className="size-5 text-white" aria-hidden />
                    ) : null}
                  </button>
                );
              })}
            </div>
            <Button
              type="button"
              className="h-11 w-full"
              onClick={() => setStep(4)}
            >
              Next
            </Button>
          </div>
        ) : null}

        {step === 4 ? (
          <div className="flex flex-col gap-4">
            <p className="text-sm text-text-secondary">
              Looks great. Create your login to save{" "}
              <span className="font-medium text-text-primary">
                {name.trim() || "your profile"}
              </span>
              .
            </p>
            {rootError ? (
              <Alert variant="destructive">
                <CircleAlert />
                <AlertTitle>{t("registrationFailed")}</AlertTitle>
                <AlertDescription>{rootError}</AlertDescription>
              </Alert>
            ) : null}
            <Form {...form}>
              <form
                onSubmit={form.handleSubmit(onSubmitCredentials)}
                className="flex flex-col gap-4"
                noValidate
              >
                <FormField
                  control={form.control}
                  name="email"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("email")}</FormLabel>
                      <FormControl>
                        <Input
                          type="email"
                          autoComplete="email"
                          inputMode="email"
                          placeholder="you@business.com"
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
                  name="password"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("password")}</FormLabel>
                      <FormControl>
                        <Input
                          type="password"
                          autoComplete="new-password"
                          className="h-11"
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <Button
                  type="submit"
                  className="h-11 w-full"
                  disabled={form.formState.isSubmitting}
                >
                  {form.formState.isSubmitting ? t("creatingAccount") : "Continue"}
                </Button>
              </form>
            </Form>
            <AuthDivider />
            <SocialButtons />
          </div>
        ) : null}
      </CardContent>

      <CardFooter className="justify-center text-sm text-muted-foreground">
        {t("alreadyHaveAccount")}{" "}
        <Link
          href="/login"
          className="ms-1 font-medium text-foreground underline-offset-4 hover:underline"
        >
          {t("signInLink")}
        </Link>
      </CardFooter>
    </Card>
  );
}

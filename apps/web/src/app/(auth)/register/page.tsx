"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { CircleAlert, Lock } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { z } from "zod";

import { AuthDivider, SocialButtons } from "@/components/auth/social-buttons";
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
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api/client";
import type { AppStatus } from "@/lib/api/types";
import { applyServerErrors, errorMessage } from "@/lib/forms";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

const schema = z.object({
  name: z.string().min(2, "Enter your name"),
  email: z.email("Enter a valid email address"),
  handle: z
    .string()
    .regex(/^[a-z0-9_]{3,30}$/, {
      message:
        "3–30 characters: lowercase letters, numbers and underscores only",
    })
    .optional()
    .or(z.literal("")),
  password: z.string().min(8, "At least 8 characters"),
});

type Values = z.infer<typeof schema>;

export default function RegisterPage() {
  const t = useTranslations("auth");
  const register = useAuthStore((s) => s.register);
  const router = useRouter();
  // "checking" while we fetch /status; failures fall back to "open".
  const [registration, setRegistration] = useState<
    "checking" | "open" | "closed"
  >("checking");

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

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", email: "", handle: "", password: "" },
  });

  async function onSubmit(values: Values) {
    form.clearErrors("root");
    try {
      await register({
        name: values.name,
        email: values.email,
        password: values.password,
        ...(values.handle ? { handle: values.handle } : {}),
      });
      router.replace("/home");
    } catch (error) {
      if (!applyServerErrors(error, form.setError)) {
        form.setError("root", { message: errorMessage(error) });
      }
    }
  }

  const rootError = form.formState.errors.root?.message;

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
          <Skeleton className="h-11 w-full" />
          <Skeleton className="h-11 w-full" />
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

  return (
    <Card className="w-full max-w-sm">
      <CardHeader>
        <CardTitle>{t("createTitle")}</CardTitle>
        <CardDescription>{t("createSubtitle")}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {rootError ? (
          <Alert variant="destructive">
            <CircleAlert />
            <AlertTitle>{t("registrationFailed")}</AlertTitle>
            <AlertDescription>{rootError}</AlertDescription>
          </Alert>
        ) : null}
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className="flex flex-col gap-4"
            noValidate
          >
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("name")}</FormLabel>
                  <FormControl>
                    <Input
                      autoComplete="name"
                      placeholder="Alex Rivera"
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
              name="handle"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("handle")}</FormLabel>
                  <FormControl>
                    <Input
                      autoComplete="username"
                      placeholder="alexrivera"
                      className="h-11"
                      {...field}
                    />
                  </FormControl>
                  <FormDescription>
                    Your unique @handle. Leave blank to get one automatically.
                  </FormDescription>
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
              {form.formState.isSubmitting
                ? t("creatingAccount")
                : t("createAccount")}
            </Button>
          </form>
        </Form>
        <AuthDivider />
        <SocialButtons />
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

"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { CircleAlert } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
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
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { ApiError } from "@/lib/api/client";
import { applyServerErrors, errorMessage } from "@/lib/forms";
import { useAuthStore } from "@/lib/stores/auth-store-provider";

const schema = z.object({
  email: z.email("Enter a valid email address"),
  password: z.string().min(1, "Enter your password"),
});

type Values = z.infer<typeof schema>;

/**
 * The post-login destination from `?next=`, restricted to same-site relative
 * paths ("/shop/...") so it can't be used as an open redirect.
 */
function safeNext(): string {
  if (typeof window === "undefined") return "/home";
  const next = new URLSearchParams(window.location.search).get("next");
  return next && next.startsWith("/") && !next.startsWith("//") ? next : "/home";
}

export default function LoginPage() {
  const t = useTranslations("auth");
  const login = useAuthStore((s) => s.login);
  const router = useRouter();
  const [banned, setBanned] = useState<{
    message: string;
    reason?: string;
  } | null>(null);

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", password: "" },
  });

  async function onSubmit(values: Values) {
    setBanned(null);
    form.clearErrors("root");
    try {
      await login(values.email, values.password);
      router.replace(safeNext());
    } catch (error) {
      if (error instanceof ApiError && error.status === 403) {
        setBanned({
          message: error.message,
          reason:
            typeof error.data.ban_reason === "string"
              ? error.data.ban_reason
              : undefined,
        });
        return;
      }
      if (!applyServerErrors(error, form.setError)) {
        form.setError("root", { message: errorMessage(error) });
      }
    }
  }

  const rootError = form.formState.errors.root?.message;

  return (
    <Card className="w-full max-w-sm">
      <CardHeader>
        <CardTitle>{t("welcomeBack")}</CardTitle>
        <CardDescription>{t("signInSubtitle")}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {banned ? (
          <Alert variant="destructive">
            <CircleAlert />
            <AlertTitle>{t("accountSuspended")}</AlertTitle>
            <AlertDescription>
              {banned.message}
              {banned.reason
                ? ` ${t("banReason", { reason: banned.reason })}`
                : null}
            </AlertDescription>
          </Alert>
        ) : null}
        {rootError ? (
          <Alert variant="destructive">
            <CircleAlert />
            <AlertTitle>{t("signInFailed")}</AlertTitle>
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
                  <div className="flex items-center justify-between">
                    <FormLabel>{t("password")}</FormLabel>
                    <Link
                      href="/forgot-password"
                      className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                    >
                      {t("forgotPassword")}
                    </Link>
                  </div>
                  <FormControl>
                    <Input
                      type="password"
                      autoComplete="current-password"
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
              {form.formState.isSubmitting ? t("signingIn") : t("signIn")}
            </Button>
          </form>
        </Form>
        <AuthDivider />
        <SocialButtons />
      </CardContent>
      <CardFooter className="justify-center text-sm text-muted-foreground">
        {t("newToSbh")}{" "}
        <Link
          href="/register"
          className="ms-1 font-medium text-foreground underline-offset-4 hover:underline"
        >
          {t("createAccount")}
        </Link>
      </CardFooter>
    </Card>
  );
}

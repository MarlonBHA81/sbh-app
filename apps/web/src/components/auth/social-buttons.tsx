"use client";

import { useTranslations } from "next-intl";

import { Button } from "@/components/ui/button";
import { API_URL } from "@/lib/api/client";

type Provider = "google" | "facebook" | "twitter";

function redirectTo(provider: Provider) {
  window.location.href = `${API_URL}/api/v1/auth/${provider}/redirect`;
}

function GoogleIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4" aria-hidden>
      <path
        fill="#4285F4"
        d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.51h6.47a5.57 5.57 0 0 1-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82Z"
      />
      <path
        fill="#34A853"
        d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A11.99 11.99 0 0 0 12 24Z"
      />
      <path
        fill="#FBBC05"
        d="M5.27 14.29A7.16 7.16 0 0 1 4.89 12c0-.8.14-1.57.38-2.29V6.62H1.29a11.99 11.99 0 0 0 0 10.76l3.98-3.09Z"
      />
      <path
        fill="#EA4335"
        d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75Z"
      />
    </svg>
  );
}

function FacebookIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4" aria-hidden>
      <path
        fill="#1877F2"
        d="M24 12a12 12 0 1 0-13.88 11.85v-8.38H7.08V12h3.04V9.36c0-3 1.79-4.67 4.53-4.67 1.31 0 2.68.24 2.68.24v2.95h-1.51c-1.49 0-1.95.92-1.95 1.87V12h3.32l-.53 3.47h-2.79v8.38A12 12 0 0 0 24 12Z"
      />
    </svg>
  );
}

function XIcon() {
  return (
    <svg viewBox="0 0 24 24" className="size-4 fill-current" aria-hidden>
      <path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.67l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23Zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64Z" />
    </svg>
  );
}

export function SocialButtons() {
  const t = useTranslations("auth");
  const enableX = process.env.NEXT_PUBLIC_ENABLE_X === "true";

  return (
    <div className="flex flex-col gap-2">
      <Button
        type="button"
        variant="outline"
        className="h-11 w-full"
        onClick={() => redirectTo("google")}
      >
        <GoogleIcon />
        {t("continueWith", { provider: "Google" })}
      </Button>
      <Button
        type="button"
        variant="outline"
        className="h-11 w-full"
        onClick={() => redirectTo("facebook")}
      >
        <FacebookIcon />
        {t("continueWith", { provider: "Facebook" })}
      </Button>
      {enableX ? (
        <Button
          type="button"
          variant="outline"
          className="h-11 w-full"
          onClick={() => redirectTo("twitter")}
        >
          <XIcon />
          {t("continueWith", { provider: "X" })}
        </Button>
      ) : null}
    </div>
  );
}

export function AuthDivider() {
  const t = useTranslations("auth");
  return (
    <div className="flex items-center gap-3 text-xs uppercase text-muted-foreground">
      <span className="h-px flex-1 bg-border" />
      {t("orDivider")}
      <span className="h-px flex-1 bg-border" />
    </div>
  );
}

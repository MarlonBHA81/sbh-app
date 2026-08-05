"use client";

import { Languages } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import { toast } from "sonner";

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { localeNames, locales, LOCALE_COOKIE, type Locale } from "@/i18n/config";
import * as api from "@/lib/api/client";

const ONE_YEAR = 60 * 60 * 24 * 365;

/**
 * Language selector. Persists the choice in the `NEXT_LOCALE` cookie (which the
 * server reads to load messages + direction) and mirrors it to the account via
 * PATCH /me. `router.refresh()` re-renders the tree in the new locale.
 */
export function LanguagePicker() {
  const t = useTranslations("settings");
  const locale = useLocale() as Locale;
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [value, setValue] = useState<Locale>(locale);

  function change(next: Locale) {
    if (next === value) return;
    setValue(next);
    // Persist for SSR: the cookie drives message loading and <html dir>.
    document.cookie = `${LOCALE_COOKIE}=${next}; path=/; max-age=${ONE_YEAR}; samesite=lax`;
    // Best-effort account sync (also localizes API error messages).
    void api
      .patch("/api/v1/me", { locale: next })
      .then(() => toast.success(t("languageUpdated")))
      .catch(() => {
        // Non-fatal — the cookie still applies locally.
      });
    startTransition(() => {
      router.refresh();
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("language")}</CardTitle>
        <CardDescription>{t("languageHint")}</CardDescription>
      </CardHeader>
      <CardContent>
        <Select
          value={value}
          onValueChange={(next) => change(next as Locale)}
          disabled={pending}
        >
          <SelectTrigger className="h-11 w-full">
            <span className="flex items-center gap-2">
              <Languages className="size-4 text-muted-foreground" aria-hidden />
              <SelectValue />
            </span>
          </SelectTrigger>
          <SelectContent>
            {locales.map((code) => (
              <SelectItem key={code} value={code}>
                {localeNames[code]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </CardContent>
    </Card>
  );
}

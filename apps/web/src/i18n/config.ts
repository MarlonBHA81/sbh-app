export const locales = ["en", "bn", "ar", "es", "fr"] as const;

export type Locale = (typeof locales)[number];

export const defaultLocale: Locale = "en";

/** Locales that render right-to-left. */
export const rtlLocales: readonly Locale[] = ["ar"];

/** Native display names for the language picker. */
export const localeNames: Record<Locale, string> = {
  en: "English",
  bn: "বাংলা",
  ar: "العربية",
  es: "Español",
  fr: "Français",
};

export const LOCALE_COOKIE = "NEXT_LOCALE";

export function isLocale(value: unknown): value is Locale {
  return (
    typeof value === "string" && (locales as readonly string[]).includes(value)
  );
}

export function localeDir(locale: Locale): "rtl" | "ltr" {
  return rtlLocales.includes(locale) ? "rtl" : "ltr";
}

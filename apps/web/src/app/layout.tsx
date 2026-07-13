import type { Metadata, Viewport } from "next";
import { Geist_Mono, Mulish, Poppins } from "next/font/google";
import { NextIntlClientProvider } from "next-intl";
import { getLocale } from "next-intl/server";
import "./globals.css";

import { ThemeProvider } from "@/components/theme-provider";
import { Toaster } from "@/components/ui/sonner";
import { localeDir, type Locale } from "@/i18n/config";
import { AuthStoreProvider } from "@/lib/stores/auth-store-provider";

// Brand manual: Poppins for headings/buttons/labels, Avenir for body with
// Mulish loaded as the web substitute (see --font-sans in globals.css).
const poppins = Poppins({
  variable: "--font-poppins",
  weight: ["500", "600", "700"],
  subsets: ["latin"],
});

const mulish = Mulish({
  variable: "--font-mulish",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000";

const siteDescription =
  "SBH Community — the social engagement app for small business owners on the go. Share ideas, find customers and grow together.";

export const metadata: Metadata = {
  metadataBase: new URL(appUrl),
  title: {
    default: "SBH Community",
    template: "%s · SBH Community",
  },
  description: siteDescription,
  applicationName: "SBH Community App",
  // Per-page canonical resolved against metadataBase.
  alternates: {
    canonical: "./",
  },
  openGraph: {
    type: "website",
    siteName: "SBH Community",
    title: "SBH Community",
    description: siteDescription,
    url: "/",
    images: [{ url: "/og.png", width: 1200, height: 630, alt: "SBH Community App" }],
  },
  twitter: {
    card: "summary_large_image",
    title: "SBH Community",
    description: siteDescription,
    images: ["/og.png"],
  },
  appleWebApp: {
    capable: true,
    statusBarStyle: "default",
    title: "SBH Community",
  },
  formatDetection: {
    telephone: false,
  },
};

// Organization + WebSite structured data for rich results.
const structuredData = {
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": `${appUrl}/#organization`,
      name: "SBH Community",
      url: appUrl,
      logo: `${appUrl}/icons/icon-512.png`,
    },
    {
      "@type": "WebSite",
      "@id": `${appUrl}/#website`,
      name: "SBH Community App",
      url: appUrl,
      description: siteDescription,
      publisher: { "@id": `${appUrl}/#organization` },
    },
  ],
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#f6f4f3" },
    { media: "(prefers-color-scheme: dark)", color: "#26262e" },
  ],
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const locale = (await getLocale()) as Locale;
  const dir = localeDir(locale);

  return (
    <html
      lang={locale}
      dir={dir}
      suppressHydrationWarning
      className={`${poppins.variable} ${mulish.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col bg-background text-foreground">
        <script
          type="application/ld+json"
          // Static, build-time JSON — no user input flows in.
          dangerouslySetInnerHTML={{ __html: JSON.stringify(structuredData) }}
        />
        <NextIntlClientProvider>
          <ThemeProvider
            attribute="class"
            defaultTheme="system"
            enableSystem
            disableTransitionOnChange
          >
            <AuthStoreProvider>
              {children}
              <Toaster position="top-center" />
            </AuthStoreProvider>
          </ThemeProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}

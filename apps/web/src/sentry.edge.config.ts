// Sentry initialisation for the Edge runtime (middleware, edge routes).
// Imported from instrumentation.ts. Inert when no DSN is set.
import * as Sentry from "@sentry/nextjs";

const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN ?? process.env.SENTRY_DSN;

Sentry.init({
  dsn,
  enabled: Boolean(dsn),
  tracesSampleRate: Number(process.env.SENTRY_TRACES_SAMPLE_RATE ?? 0),
  sendDefaultPii: false,
});

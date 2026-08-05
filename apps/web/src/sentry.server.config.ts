// Sentry initialisation for the Node.js server runtime. Imported from
// instrumentation.ts. Inert when no DSN is set, so nothing is sent until the
// app is configured with NEXT_PUBLIC_SENTRY_DSN / SENTRY_DSN.
import * as Sentry from "@sentry/nextjs";

const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN ?? process.env.SENTRY_DSN;

Sentry.init({
  dsn,
  enabled: Boolean(dsn),
  // Performance tracing off by default; raise via env to sample transactions.
  tracesSampleRate: Number(process.env.SENTRY_TRACES_SAMPLE_RATE ?? 0),
  // Never attach the requester's IP / cookies / headers automatically (POPIA).
  sendDefaultPii: false,
});

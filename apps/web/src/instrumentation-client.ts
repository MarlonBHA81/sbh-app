// Sentry initialisation for the browser. Loaded automatically by Next.js.
// Error-only: no Session Replay integration is added (POPIA — we capture errors,
// not user sessions). Inert when no DSN is set.
import * as Sentry from "@sentry/nextjs";

const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;

Sentry.init({
  dsn,
  enabled: Boolean(dsn),
  tracesSampleRate: Number(
    process.env.NEXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE ?? 0,
  ),
  sendDefaultPii: false,
});

// Report client-side navigation errors from the App Router.
export const onRouterTransitionStart = Sentry.captureRouterTransitionStart;

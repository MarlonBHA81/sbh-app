"use client";

// Root error boundary — catches render crashes that escape the app layouts and
// reports them to Sentry. Renders its own <html>/<body> because it replaces the
// root layout, so styling is inline (corporate teal) rather than app CSS.
import * as Sentry from "@sentry/nextjs";
import { useEffect } from "react";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <html lang="en">
      <body
        style={{
          margin: 0,
          minHeight: "100vh",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          fontFamily:
            "system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif",
          background: "#f6f4f3",
          color: "#484851",
        }}
      >
        <div style={{ maxWidth: 420, padding: 24, textAlign: "center" }}>
          <h1 style={{ fontSize: 20, fontWeight: 700, color: "#4e8a88" }}>
            Something went wrong
          </h1>
          <p style={{ fontSize: 15, lineHeight: 1.5, marginTop: 8 }}>
            An unexpected error occurred. It has been logged and we&rsquo;ll
            look into it. You can try again.
          </p>
          <button
            type="button"
            onClick={() => reset()}
            style={{
              marginTop: 20,
              padding: "10px 20px",
              borderRadius: 9999,
              border: "none",
              background: "#4e8a88",
              color: "#ffffff",
              fontSize: 15,
              fontWeight: 600,
              cursor: "pointer",
            }}
          >
            Try again
          </button>
        </div>
      </body>
    </html>
  );
}

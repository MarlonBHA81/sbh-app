import { defineConfig, devices } from "@playwright/test";

/**
 * Lean end-to-end smoke suite. Runs a handful of high-signal checks against a
 * production build (`next start`) — enough to catch a broken shell, routing or
 * auth-guard regression without the cost of a full browser matrix.
 *
 * The pre-installed Chromium lives at PLAYWRIGHT_BROWSERS_PATH (set in CI/dev);
 * we never download browsers here.
 */
const PORT = Number(process.env.PLAYWRIGHT_PORT ?? 3100);
const baseURL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [["github"], ["list"]] : "list",
  timeout: 30_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL,
    trace: "on-first-retry",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
  webServer: {
    // Serve the production build. CI runs `pnpm build` first (the web job);
    // locally, run `pnpm build` once before `pnpm test:e2e`.
    command: `pnpm exec next start --port ${PORT}`,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});

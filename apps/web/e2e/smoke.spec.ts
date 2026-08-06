import { expect, test } from "@playwright/test";

/**
 * High-signal smoke tests. No backend is required: the app calls /api/v1/me on
 * boot, that call fails without an API, and the auth store resolves to "guest".
 * From there the public login page renders and private routes bounce to it.
 */

test("the login page renders its sign-in form", async ({ page }) => {
  await page.goto("/login");

  // The email + password fields and a submit control prove the form mounted.
  await expect(page.getByRole("textbox", { name: /email/i })).toBeVisible();
  await expect(page.locator('input[type="password"]')).toBeVisible();
  await expect(
    page.getByRole("button", { name: /sign in|log in/i }),
  ).toBeVisible();
});

test("an unauthenticated private route redirects to login", async ({
  page,
}) => {
  // /messages is a reserved, non-public route: a guest hitting it is sent to
  // the sign-in wall (unlike /home, which falls back to the public feed).
  await page.goto("/messages");

  await expect(page).toHaveURL(/\/login(\?|$)/);
  await expect(page.locator('input[type="password"]')).toBeVisible();
});

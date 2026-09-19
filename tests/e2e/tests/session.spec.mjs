import { test, expect } from "@playwright/test";

// What happens when the server rejects a stored token.
//
// The database is rebuilt often in development, and every login overwrites customers.api_token,
// so a browser can easily be left holding a token the server no longer recognises. Before this,
// the header kept greeting the customer by name while every authenticated call returned 401 —
// they only discovered the session was dead when placing an order failed with "Unauthorized".

const PRODUCT = "cautery-foot-paddle";

/**
 * Put a session in localStorage, then load the site with it.
 *
 * Deliberately NOT page.addInitScript: that re-runs on every navigation, so it would silently
 * re-seed the token after a sign-out or reload. That masked two things at once — a sign-out looked
 * like it leaked the token, and "the session survives a refresh" passed without proving anything,
 * because the script simply put the token back. Seeding once, from the page, tests what the app
 * actually persists.
 */
async function seedSession(page, token) {
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.evaluate((tok) => {
    localStorage.setItem("sdi:user", JSON.stringify({ name: "Shubham Kadvani", mobile: "7990432678" }));
    localStorage.setItem("sdi:token", JSON.stringify(tok));
  }, token);
  await page.reload({ waitUntil: "domcontentloaded" });
}

const seedDeadSession = (page) => seedSession(page, "dead-token-the-server-will-reject");

test.describe("G. Expired session", () => {
  test("G1 · the header stops showing the name once the token is rejected", async ({ page }) => {
    await seedDeadSession(page);

    const account = page.getByRole("button", { name: /account/i }).first();
    // The cart/wishlist sync fires on load, gets a 401, and ends the session.
    await expect(account).not.toContainText(/Hi\s+SHUBHAM/i, { timeout: 15000 });
    await expect(account).toContainText(/You/i);
  });

  test("G2 · clicking the account button offers sign-in instead of the account page", async ({ page }) => {
    await seedDeadSession(page);
    const account = page.getByRole("button", { name: /account/i }).first();
    await expect(account).toContainText(/You/i, { timeout: 15000 });

    await account.click();
    await page.waitForTimeout(1200);
    // The auth modal, not the account page.
    expect(page.url()).not.toMatch(/\/account/);
    expect(await page.locator("body").innerText()).toMatch(/sign in|log ?in|continue|mobile|otp/i);
  });

  test("G3 · the customer is told why, exactly once", async ({ page }) => {
    // Cart and wishlist both sync on load and both receive the 401, so this also guards against
    // the notice firing twice.
    //
    // Counting the event rather than the toast on screen: toasts self-dismiss after 2.5s, so
    // sampling the DOM later is a race — it reports 0 simply because the toast has gone.
    await page.goto("/", { waitUntil: "domcontentloaded" });
    await page.evaluate(() => {
      localStorage.setItem("sdi:user", JSON.stringify({ name: "Shubham Kadvani", mobile: "7990432678" }));
      localStorage.setItem("sdi:token", JSON.stringify("dead-token-the-server-will-reject"));
    });
    // The counter must be installed before the app boots on the next load, and must survive it.
    await page.addInitScript(() => {
      window.__sessionExpiredCount = 0;
      window.addEventListener("sdi:session-expired", () => { window.__sessionExpiredCount++; });
    });
    await page.reload({ waitUntil: "domcontentloaded" });

    await expect(page.getByText(/session has ended/i).first()).toBeVisible({ timeout: 15000 });
    // Give the second sync ample time to raise a duplicate, then confirm it did not.
    await page.waitForTimeout(3000);
    expect(await page.evaluate(() => window.__sessionExpiredCount)).toBe(1);
  });

  test("G4 · a guest with no token is left alone", async ({ page }) => {
    // Guards the earlier failure mode: a sync firing before the token was set produced a
    // spurious 401 and signed people out mid-session. No token means nothing to expire.
    await page.goto("/", { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(2500);
    await expect(page.getByText(/session has ended/i)).toHaveCount(0);
    await expect(page.getByRole("button", { name: /account/i }).first()).toContainText(/You/i);
  });

  test("G6 · REGRESSION: a VALID session survives a page refresh", async ({ page }) => {
    // The bug this guards: AuthContext restores the token into the API client from an effect, but
    // CartProvider/WishlistProvider are its children and React runs child effects first. Their
    // sync therefore fired with no Authorization header, got a 401, and signed the customer out
    // every time they refreshed. Needs a real token — run with VALID_TOKEN=<customers.api_token>.
    const valid = process.env.VALID_TOKEN;
    test.skip(!valid, "Set VALID_TOKEN to a live customers.api_token to run this.");

    await seedSession(page, valid);
    await page.waitForTimeout(4000);      // let both syncs complete

    await expect(page.getByText(/session has ended/i)).toHaveCount(0);
    await expect(page.getByRole("button", { name: /account/i }).first()).toContainText(/Hi\s+SHUBHAM/i);

    // And again after an explicit reload — the original report was "login, refresh, logged out".
    await page.reload({ waitUntil: "domcontentloaded" });
    await page.waitForTimeout(4000);
    await expect(page.getByText(/session has ended/i)).toHaveCount(0);
    await expect(page.getByRole("button", { name: /account/i }).first()).toContainText(/Hi\s+SHUBHAM/i);
  });

  test("G7 · SECURITY: nothing is sent with the token after signing out", async ({ page }) => {
    // authHeaders() falls back to localStorage so a refresh keeps the session (G6). That fallback
    // must stop the instant the customer signs out — logout clears the in-memory token
    // immediately, but useLocalStorage writes the cleared value from an effect, so storage still
    // holds the old token briefly. On a shared device, continuing to authenticate would be a leak.
    const valid = process.env.VALID_TOKEN;
    test.skip(!valid, "Set VALID_TOKEN to a live customers.api_token to run this.");

    await seedSession(page, valid);
    // Let both syncs finish before asserting the precondition. Asserting immediately made this
    // test flaky: under a full headed run the app had not finished booting, the button still read
    // "You", and the failure looked like a logout that had not happened.
    await page.waitForTimeout(4000);
    await expect(page.getByRole("button", { name: /account/i }).first()).toContainText(/Hi\s+SHUBHAM/i, { timeout: 15000 });

    // Record every authenticated API request from here on.
    const authedAfterLogout = [];
    let loggedOut = false;
    page.on("request", (r) => {
      if (!loggedOut || !r.url().includes("/api/v1/")) return;
      const h = r.headers();
      if (h["x-auth-token"] || h["authorization"]) {
        authedAfterLogout.push(r.url().split("/api/v1/")[1].split("?")[0]);
      }
    });

    // Sign out the way a customer does: sidebar item, then confirm the dialog. Missing the
    // confirmation step leaves the session very much alive — the first version of this test did
    // exactly that and reported a "leak" that was really just ordinary logged-in traffic.
    await page.getByRole("button", { name: /account/i }).first().click();
    await page.getByText(/^Sign Out$/).first().click();
    const dialog = page.getByRole("dialog");
    await expect(dialog.getByText(/Are you sure you want to sign out/i)).toBeVisible({ timeout: 10000 });
    loggedOut = true;
    await dialog.getByRole("button", { name: /^sign out$/i }).click();

    // Give the app time to settle and fire whatever it fires after a sign-out.
    await page.waitForTimeout(3000);
    await page.goto("/", { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(3000);

    expect(authedAfterLogout, `these carried the token after sign-out: ${authedAfterLogout.join(", ")}`)
      .toEqual([]);
    await expect(page.getByRole("button", { name: /account/i }).first()).toContainText(/You/i);
  });

  test("G5 · browsing still works with a dead session", async ({ page }) => {
    // Losing the session must not break the shop — only the account-bound parts.
    await seedDeadSession(page);
    await page.goto(`/product/${PRODUCT}`, { waitUntil: "domcontentloaded" });
    await expect(page.getByRole("heading", { name: /Cautery Foot Paddle/i }).first()).toBeVisible();
    await page.getByPlaceholder(/enter pincode/i).fill("380001");
    await page.getByRole("button", { name: /^check$/i }).click();
    await expect(page.getByText(/Get it by/i).first()).toBeVisible();
  });
});

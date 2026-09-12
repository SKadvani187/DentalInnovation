import { test, expect } from "@playwright/test";

// Cart behaviour and the checkout entry point.
//
// Two things the first draft of these tests got wrong, corrected here:
//  * The product page's main ADD button does NOT open the cart (ProductDetailPage.onAdd only calls
//    addToCart). Asserting "adding opens the cart" passed for the wrong reason, because the product
//    name is on the page either way. The cart is opened from the header instead.
//  * The class buy-now-btn__face exists on the product page as well, so an unscoped selector hit
//    BUY NOW rather than the cart's Place Order.

const PRODUCT = "cautery-foot-paddle";
const API = "http://localhost:9090/api/v1";

// The live header is Navbar copy.jsx (NavigationHeader): the cart control is a button whose
// visible text is "cart", followed by "(N)" once the cart has items. There is no aria-label.
const openCart = (page) => page.getByRole("button", { name: /^cart/i }).first();

const addOne = async (page) => {
  await page.goto(`/product/${PRODUCT}`, { waitUntil: "domcontentloaded" });
  await page.getByRole("button", { name: /^add$/i }).first().click();
  await page.waitForTimeout(1200);
};

test.describe("E. Cart and checkout entry", () => {
  test("E1 · ADD puts the item in the cart (badge count rises)", async ({ page }) => {
    await page.goto(`/product/${PRODUCT}`, { waitUntil: "domcontentloaded" });
    await page.getByRole("button", { name: /^add$/i }).first().click();
    await page.waitForTimeout(1200);
    // The count in the header button is the honest signal that the cart changed.
    await expect(openCart(page)).toContainText(/\(\s*1\s*\)/);
  });

  test("E2 · the cart lists the item once opened from the header", async ({ page }) => {
    await addOne(page);
    await openCart(page).click();
    await page.waitForTimeout(1200);
    await expect(page.getByText(/Cautery Foot Paddle/i).first()).toBeVisible();
  });

  test("E3 · the cart's delivery charge is the server's number, not a local guess", async ({ page, request }) => {
    const q = await (await request.post(`${API}/shipping_quote.php`, {
      data: { items: [{ id: PRODUCT, qty: 1 }], pincode: "" },
    })).json();

    await addOne(page);
    await openCart(page).click();
    await page.waitForTimeout(1800);
    const body = await page.locator("body").innerText();
    expect(body, `server quoted Rs.${q.shipping}`).toMatch(new RegExp(`₹\\s?${q.shipping}\\b`));
  });

  test("E4 · a guest clicking Place Order is sent to sign in", async ({ page }) => {
    await addOne(page);
    await openCart(page).click();
    await page.waitForTimeout(1200);

    // The same button class exists on the product page behind the drawer, and the drawer is
    // rendered after it, so the LAST match is the cart's Place Order.
    const placeOrder = page.locator("button.buy-now-btn__face").last();
    await expect(placeOrder).toBeVisible();
    await placeOrder.click();
    await page.waitForTimeout(2000);

    // AuthModal shows a Continue action and a mobile/email field.
    const body = await page.locator("body").innerText();
    expect(body).toMatch(/continue|verify|password|mobile|email/i);
  });

  test("E5 · BLOCKED: the delivery-option picker needs an authenticated session", async ({}, testInfo) => {
    testInfo.annotations.push({
      type: "blocked",
      description:
        "CartDrawer.onCheckout opens the auth modal for guests, so the checkout drawer — and with " +
        "it the delivery-option radio picker, the per-option ETA, COD gating and the COD fee row " +
        "built this session — cannot be reached without credentials. Provide a storefront test " +
        "account, or enable OTP_DEV_RETURN in a local config, to cover these.",
    });
    test.skip(true, "Requires a storefront test account.");
  });
});

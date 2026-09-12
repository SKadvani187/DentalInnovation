import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests",
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,              // one browser, so the run is watchable and the cart state is predictable
  retries: 0,
  reporter: [
    ["list"],
    ["html", { outputFolder: "playwright-report", open: "never" }],
    ["json", { outputFile: "playwright-report/results.json" }],
  ],
  use: {
    baseURL: "http://localhost:5173",
    headless: false,       // visible run
    launchOptions: { slowMo: 250 },
    viewport: { width: 1440, height: 900 },
    screenshot: "on",
    video: "on",
    trace: "on",
    actionTimeout: 15_000,
  },
  projects: [{ name: "chromium", use: { browserName: "chromium", channel: "chrome" } }],
});

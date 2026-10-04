import { defineConfig } from "@playwright/test";
export default defineConfig({
    testDir: "tests/browser",
    workers: 1,
    timeout: 60000,
    use: {
        baseURL: process.env.FITSPOT_TEST_URL || "http://localhost:8080",
        headless: true,
    },
    reporter: "list",
});

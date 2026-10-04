import { chromium, request } from "@playwright/test";
import { performance } from "node:perf_hooks";
const origin = process.env.FITSPOT_TEST_URL || "http://localhost:8080";
const email = process.env.FITSPOT_BENCH_EMAIL || "demo@fitspot.test";
const password = process.env.FITSPOT_BENCH_PASSWORD || "FitSpotDemo123!";
const browser = await chromium.launch({ headless: true });
try {
    const page = await browser.newPage();
    await page.goto(origin + "/login");
    await page.getByLabel("Email", { exact: true }).fill(email);
    await page.getByLabel("Пароль", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Войти", exact: true }).click();
    await page.waitForURL(/\/app/);
    const cookies = await page.context().cookies();
    const csrf = decodeURIComponent(
        cookies.find((c) => c.name === "XSRF-TOKEN").value,
    );
    const html = await (await page.request.get(origin+'/app/rules')).text();
  const embedded=html.match(/<script[^>]*data-page="app"[^>]*>([\s\S]*?)<\/script>/);
  if(!embedded)throw new Error('Inertia page missing');
  const state=JSON.parse(embedded[1]); const version=state.version;
  const {
        buffer_before,
        buffer_after,
        lead_minutes,
        horizon_days,
        slot_step,
        cancel_minutes,
        reschedule_minutes,
    } = state.props.workspace.rules;
    const rules = {
        buffer_before,
        buffer_after,
        lead_minutes,
        horizon_days,
        slot_step,
        cancel_minutes,
        reschedule_minutes,
    };
    const api=await request.newContext({storageState: await page.context().storageState()});
    const failures=[];
    const timings = { read: [], save: [], save_with_redirect: [] };
    let errors = 0;
    const workers = await Promise.allSettled(
        Array.from({ length: 20 }, async () => {
            for (let i = 0; i < 10; i++) {
                const kind = i % 2 ? "save" : "read";
                const start = performance.now();
                const response =
                    kind === "read"
                        ? await api.get(origin + "/app/profile", {
                              headers: { "X-Inertia": "true", "X-Inertia-Version": version },
                          })
                        : await api.put(origin + "/app/rules", {
                              data: rules,
                              maxRedirects: 0,
                              headers: {
                                  "X-XSRF-TOKEN": csrf,
                                  "X-Inertia": "true", "X-Inertia-Version": version,
                                  Referer: origin + "/app/rules",
                                  Accept: "application/json",
                              },
                          });
                timings[kind].push(performance.now() - start);
                if(kind==='save'&&response.status()===303){
                    const follow=await api.get(response.headers().location,{headers:{'X-Inertia':'true','X-Inertia-Version':version}});
                    timings.save_with_redirect.push(performance.now()-start);
                    if(!follow.ok())errors++;
                }else if(!response.ok()) errors++;
            }
        }),
    );
    for(const worker of workers)if(worker.status==='rejected'){errors++;failures.push(worker.reason.message.split("\n")[0]);}
    await api.dispose();
    const results = Object.fromEntries(
        Object.entries(timings).map(([kind, samples]) => {
            samples.sort((a, b) => a - b);
            return [
                kind,
                {
                    requests: samples.length,
                    p95_ms: Math.round(
                        samples[Math.ceil(samples.length * 0.95) - 1],
                    ),
                    max_ms: Math.round(samples.at(-1)),
                },
            ];
        }),
    );
    console.log(
        JSON.stringify({ concurrency: 20, errors, failures, ...results }, null, 2),
    );
    if (errors || [results.read,results.save].some((r) => r.p95_ms > 500))
        process.exitCode = 1;
} finally {
    await browser.close();
}

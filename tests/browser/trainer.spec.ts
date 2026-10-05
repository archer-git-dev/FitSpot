import { test, expect } from "@playwright/test";
const password = "TrainerBrowser123!";
async function mailLink(request: any, email: string, match: string) {
    const api = process.env.MAILPIT_URL || "http://localhost:8025";
    let id: string | undefined;
    for (let i = 0; i < 40; i++) {
        const res = await request.get(api + "/api/v1/messages");
        const data = await res.json();
        id = data.messages?.find(
            (m: any) =>
                m.To?.some((t: any) => t.Address === email) &&
                m.Subject.includes(match),
        )?.ID;
        if (id) break;
        await new Promise((r) => setTimeout(r, 500));
    }
    expect(id, "Письмо должно пройти через RabbitMQ → Mailpit").toBeTruthy();
    const mail = await (
        await request.get(api + "/api/v1/message/" + id)
    ).json();
    const link = mail.Text.match(
        /https?:\/\/[^\s)]+\/(?:email\/verify|reset-password|access\/email\/confirm)[^\s)]*/,
    )?.[0];
    expect(link).toBeTruthy();
    return link;
}
test("регистрация, письмо, настройки, мобильный экран и восстановление", async ({
    page,
    request,
}) => {
    const email = `browser-${Date.now()}@example.test`,
        errors: string[] = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto("/register");
    await page.getByLabel("Имя", { exact: true }).fill("Тренер Проверка");
    await page.getByLabel("Email", { exact: true }).fill(email);
    await page.getByLabel("Пароль", { exact: true }).fill(password);
    await page.getByLabel("Повторите пароль").fill(password);
    await page.getByRole("button", { name: "Создать аккаунт" }).click();
    await expect(page).toHaveURL(/email\/verify/);
    await page.goto(await mailLink(request, email, "Подтвердите email"));
    await expect(page).toHaveURL(/app/);
    await page.goto("/app/profile");
    await page.getByLabel("Имя", { exact: true }).fill("Тренер Проверка UI");
    await page.getByLabel("О себе").fill("Проверка сохранения профиля");
    await page.getByRole("button", { name: "Сохранить профиль" }).click();
    await expect(page.getByRole("status")).toContainText("Профиль сохранён");
    await page.reload();
    await expect(page.getByLabel("О себе")).toHaveValue(
        "Проверка сохранения профиля",
    );
    await page.goto("/app/schedule");
    await page.getByRole("button", { name: "+ Интервал" }).first().click();
    await page.getByRole("button", { name: "Сохранить график" }).click();
    await expect(page.getByRole("status")).toContainText("График сохранён");
    await page.goto("/app/rules");
    await page.getByLabel("Буфер после занятия, минут").fill("20");
    await page.getByRole("button", { name: "Сохранить правила" }).click();
    await expect(page.getByRole("status")).toContainText("Правила сохранены");
    await page.goto("/app/services");
    await page
        .getByRole("button", { name: "Добавить услугу", exact: true })
        .click();
    await page.getByLabel("Название", { exact: true }).fill("Онлайн-сплит");
    await page.getByLabel("Тип", { exact: true }).selectOption("split");
    await page.getByLabel("Формат", { exact: true }).selectOption("online");
    await page.getByLabel("Цена за участника, ₽").fill("2500.05");
    await page.getByRole("button", { name: "Сохранить услугу" }).click();
    await expect(page.getByRole("status")).toContainText("Услуга сохранена");
    await expect(page.getByRole("article")).toContainText("Онлайн-сплит");
    await page.goto("/app");
    await expect(
        page.getByRole("heading", { name: "Базовая настройка завершена" }),
    ).toBeVisible();
    await page.screenshot({
        path: "/tmp/fitspot-overview.png",
        fullPage: true,
    });
    await page.setViewportSize({ width: 360, height: 800 });
    for (const section of [
        "overview",
        "profile",
        "schedule",
        "services",
        "rules",
        "access",
    ]) {
        await page.goto("/app/" + section);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
            section,
        ).toBeTruthy();
    }
    await page.screenshot({ path: "/tmp/fitspot-mobile.png", fullPage: true });
    await page.getByRole("button", { name: "Меню", exact: true }).click();
    await page.getByRole("button", { name: "Выйти", exact: true }).click();
    await expect(page).toHaveURL(/login/);
    await page.goto("/forgot-password");
    await page.getByLabel("Email", { exact: true }).fill(email);
    await page.getByRole("button", { name: "Отправить ссылку" }).click();
    await expect(page.getByRole("status")).toContainText(
        "Если аккаунт существует",
    );
    await page.goto(await mailLink(request, email, "Восстановление"));
    await page.getByLabel("Пароль", { exact: true }).fill("NewTrainerPass123!");
    await page.getByLabel("Повторите пароль").fill("NewTrainerPass123!");
    await page.getByRole("button", { name: "Сохранить пароль" }).click();
    await expect(page).toHaveURL(/login/);
    await page.getByLabel("Email", { exact: true }).fill(email);
    await page.getByLabel("Пароль", { exact: true }).fill("NewTrainerPass123!");
    await page.getByRole("button", { name: "Войти", exact: true }).click();
    await expect(page).toHaveURL(/app/);
    expect(errors).toEqual([]);
});

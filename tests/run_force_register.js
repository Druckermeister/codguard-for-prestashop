const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    console.log('Running force hook registration...\n');

    await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/force_register_hooks.php');
    await page.waitForTimeout(3000);

    const content = await page.textContent('body');
    console.log(content);

    await page.screenshot({ path: '/tmp/force_register.png', fullPage: true });
    console.log('\nScreenshot saved to: /tmp/force_register.png');

    await browser.close();
})();

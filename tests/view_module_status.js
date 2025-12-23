const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    console.log('Checking CodGuard module status...\n');

    await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/check_module_status.php');
    await page.waitForTimeout(2000);

    // Take screenshot
    await page.screenshot({ path: '/tmp/module_status.png', fullPage: true });
    console.log('Screenshot saved to: /tmp/module_status.png\n');

    // Get text content
    const content = await page.textContent('body');
    console.log(content);

    await browser.close();
})();

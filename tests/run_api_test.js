const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    console.log('Running direct API test via PrestaShop...\n');

    await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/test_api_call_direct.php');
    await page.waitForTimeout(3000);

    const content = await page.textContent('body');
    console.log(content);

    await page.screenshot({ path: '/tmp/api_test_result.png', fullPage: true });
    console.log('\nScreenshot saved to: /tmp/api_test_result.png');

    await browser.close();
})();

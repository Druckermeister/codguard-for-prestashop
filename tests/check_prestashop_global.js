const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    await page.goto('https://prestashop.codguard.com.uvds288.active24.cz/en/order');
    await page.waitForTimeout(5000);

    const prestashopGlobal = await page.evaluate(() => {
        if (typeof prestashop !== 'undefined') {
            return JSON.stringify(prestashop, null, 2);
        }
        return 'prestashop global not found';
    });

    console.log('Prestashop global variable:');
    console.log(prestashopGlobal);

    // Check specifically for codguardConfig
    const hasCodeguardConfig = await page.evaluate(() => {
        return typeof prestashop !== 'undefined' && typeof prestashop.codguardConfig !== 'undefined';
    });

    console.log('\nHas prestashop.codguardConfig:', hasCodeguardConfig);

    if (hasCodeguardConfig) {
        const config = await page.evaluate(() => prestashop.codguardConfig);
        console.log('Config:', config);
    }

    await browser.close();
})();

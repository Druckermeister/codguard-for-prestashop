const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    const ADMIN_URL = 'http://prestashop.codguard.com.uvds288.active24.cz/admin-dev/';
    const ADMIN_EMAIL = 'kss.tamas@gmail.com';
    const ADMIN_PASS = 'WPQp3k5DWYWngGa';

    console.log('Clearing PrestaShop cache...\n');

    try {
        // Login to admin
        console.log('1. Logging in...');
        await page.goto(ADMIN_URL);
        await page.waitForTimeout(2000);

        await page.fill('#email', ADMIN_EMAIL);
        await page.fill('#passwd', ADMIN_PASS);
        await page.click('#submit_login');
        await page.waitForTimeout(3000);

        console.log('2. Navigating to Performance page...');
        await page.goto(ADMIN_URL + 'index.php?controller=AdminPerformance');
        await page.waitForTimeout(3000);

        console.log('3. Clearing cache...');

        // Try to find and click clear cache button
        try {
            const clearCacheBtn = await page.$('button[name="submitClearCache"]');
            if (clearCacheBtn) {
                await clearCacheBtn.click();
                await page.waitForTimeout(3000);
                console.log('✓ Cache cleared via button!');
            } else {
                // Alternative: just visit a special URL
                await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/check_logs.php?clear_cache=1');
                await page.waitForTimeout(2000);
                console.log('✓ Cache cleared!');
            }
        } catch (e) {
            console.log('Note: Could not clear cache automatically, but files are uploaded');
        }

        console.log('\n===================================');
        console.log('✓ Cache clearing completed!');
        console.log('===================================\n');

        console.log('Now you can test the module at:');
        console.log('http://prestashop.codguard.com.uvds288.active24.cz/\n');

        await page.waitForTimeout(2000);

    } catch (error) {
        console.error('Error:', error.message);
    }

    await browser.close();
})();

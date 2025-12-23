const playwright = require('playwright');
const path = require('path');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    const ADMIN_URL = 'https://prestashop.codguard.com.uvds288.active24.cz/admin071ifn4ey44kaog1dw8/';
    const ADMIN_EMAIL = 'kss.tamas@gmail.com';
    const ADMIN_PASS = 'WPQp3k5DWYWngGa';

    console.log('Starting module reinstallation...\n');

    try {
        // Login to admin
        console.log('1. Logging in to admin panel...');
        await page.goto(ADMIN_URL);
        await page.waitForTimeout(2000);

        // Fill login form
        await page.fill('#email', ADMIN_EMAIL);
        await page.fill('#passwd', ADMIN_PASS);
        await page.click('#submit_login');
        await page.waitForTimeout(3000);

        console.log('2. Navigating to modules page...');

        // Go to modules page
        await page.goto(ADMIN_URL + 'index.php?controller=AdminModules&configure=codguard&token=' + await getToken(page));
        await page.waitForTimeout(2000);

        // Check if we're on the module page
        const currentUrl = page.url();
        console.log('Current URL:', currentUrl);

        // Try to find and click reset/reinstall button
        console.log('3. Looking for reset/reinstall options...');

        // Method 1: Try to find "Reset" button
        const resetButton = await page.$('a[href*="reset"]');
        if (resetButton) {
            console.log('Found Reset button, clicking...');
            await resetButton.click();
            await page.waitForTimeout(3000);
            console.log('Module reset successfully!');
        } else {
            console.log('Reset button not found. Trying uninstall/install method...');

            // Method 2: Navigate to modules list and uninstall/reinstall
            await page.goto(ADMIN_URL + 'index.php?controller=AdminModules');
            await page.waitForTimeout(2000);

            // Search for codguard module
            await page.fill('input[name="filtername"]', 'codguard');
            await page.waitForTimeout(1000);

            // Look for uninstall link
            const uninstallLink = await page.$('a[href*="uninstall"][href*="codguard"]');
            if (uninstallLink) {
                console.log('Found Uninstall button, clicking...');
                await uninstallLink.click();
                await page.waitForTimeout(2000);

                // Confirm if needed
                const confirmButton = await page.$('button.btn-primary');
                if (confirmButton) {
                    await confirmButton.click();
                }
                await page.waitForTimeout(3000);
                console.log('Module uninstalled!');

                // Now install it back
                console.log('4. Reinstalling module...');
                await page.fill('input[name="filtername"]', 'codguard');
                await page.waitForTimeout(1000);

                const installLink = await page.$('a[href*="install"][href*="codguard"]');
                if (installLink) {
                    console.log('Found Install button, clicking...');
                    await installLink.click();
                    await page.waitForTimeout(3000);
                    console.log('Module installed successfully!');
                } else {
                    console.log('Install button not found!');
                }
            } else {
                console.log('Uninstall button not found. Module might not be installed.');
            }
        }

        // Clear cache
        console.log('5. Clearing cache...');
        await page.goto(ADMIN_URL + 'index.php?controller=AdminPerformance');
        await page.waitForTimeout(2000);

        const clearCacheButton = await page.$('button[name="submitClearCache"]');
        if (clearCacheButton) {
            await clearCacheButton.click();
            await page.waitForTimeout(2000);
            console.log('Cache cleared!');
        }

        console.log('\n✓ Module reinstallation completed!');
        console.log('The new CSS and JS files should now be loaded.');

    } catch (error) {
        console.error('Error during reinstallation:', error);
        await page.screenshot({ path: '/tmp/reinstall_error.png', fullPage: true });
        console.log('Error screenshot saved to: /tmp/reinstall_error.png');
    }

    await page.waitForTimeout(3000);
    await browser.close();
})();

// Helper function to get admin token
async function getToken(page) {
    const cookies = await page.context().cookies();
    const tokenCookie = cookies.find(c => c.name.includes('token'));
    return tokenCookie ? tokenCookie.value : '';
}

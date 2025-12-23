const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({
        ignoreHTTPSErrors: true
    });
    const page = await context.newPage();

    console.log('=== Testing PrestaShop Rating API Call ===\n');

    // Enable request interception to monitor API calls
    const apiCalls = [];
    page.on('request', request => {
        const url = request.url();
        if (url.includes('api.codguard.com') || url.includes('customer-rating')) {
            apiCalls.push({
                url: url,
                method: request.method(),
                headers: request.headers()
            });
            console.log('API CALL DETECTED:');
            console.log('  URL:', url);
            console.log('  Method:', request.method());
        }
    });

    page.on('response', async response => {
        const url = response.url();
        if (url.includes('api.codguard.com') || url.includes('customer-rating')) {
            console.log('API RESPONSE:');
            console.log('  Status:', response.status());
            try {
                const body = await response.text();
                console.log('  Body:', body);
            } catch (e) {
                console.log('  Body: [unable to read]');
            }
        }
    });

    try {
        console.log('Step 1: Going to homepage...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/', { waitUntil: 'networkidle' });
        await page.screenshot({ path: '/tmp/prestashop_test_1_homepage.png', fullPage: true });

        console.log('Step 2: Starting checkout process...');
        // Try to find and click checkout or cart button
        const checkoutSelectors = [
            'a[href*="order"]',
            'a[href*="checkout"]',
            'button:has-text("Checkout")',
            '.checkout-button'
        ];

        let checkoutFound = false;
        for (const selector of checkoutSelectors) {
            try {
                await page.click(selector, { timeout: 3000 });
                checkoutFound = true;
                console.log('  Clicked checkout with selector:', selector);
                break;
            } catch (e) {
                // Try next selector
            }
        }

        if (!checkoutFound) {
            console.log('  WARNING: Could not find checkout button, navigating directly...');
            await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/en/order', { waitUntil: 'networkidle' });
        }

        await page.waitForTimeout(2000);
        await page.screenshot({ path: '/tmp/prestashop_test_2_checkout.png', fullPage: true });

        console.log('Step 3: Filling in customer email...');
        // Generate sequential test email with timestamp to avoid privaterelay
        const testEmail = 'testuser' + Date.now() + '@example.com';
        const emailInput = await page.locator('input[type="email"], input[name*="email"], #email').first();
        if (await emailInput.count() > 0) {
            await emailInput.fill(testEmail);
            console.log('  Email entered:', testEmail);
        } else {
            console.log('  WARNING: Email input not found');
        }

        await page.waitForTimeout(1000);
        await page.screenshot({ path: '/tmp/prestashop_test_3_email_entered.png', fullPage: true });

        console.log('Step 4: Proceeding to payment methods...');
        // Try to click continue/next button
        const continueSelectors = [
            'button[name="continue"]',
            'button[name="submitCreate"]',
            'button:has-text("Continue")',
            'button:has-text("Next")',
            '.btn-primary'
        ];

        for (const selector of continueSelectors) {
            try {
                await page.click(selector, { timeout: 3000 });
                console.log('  Clicked continue with selector:', selector);
                break;
            } catch (e) {
                // Try next selector
            }
        }

        await page.waitForTimeout(3000);
        await page.screenshot({ path: '/tmp/prestashop_test_4_payment_methods.png', fullPage: true });

        console.log('\n=== Test Summary ===');
        console.log('API Calls Made:', apiCalls.length);
        if (apiCalls.length > 0) {
            console.log('\nDetected API calls:');
            apiCalls.forEach((call, i) => {
                console.log(`  ${i + 1}. ${call.method} ${call.url}`);
            });
        } else {
            console.log('WARNING: No API calls to CodGuard detected!');
            console.log('Check:');
            console.log('  1. Is the module enabled?');
            console.log('  2. Are API keys configured?');
            console.log('  3. Check PrestaShop logs for errors');
        }

        console.log('\nScreenshots saved:');
        console.log('  /tmp/prestashop_test_1_homepage.png');
        console.log('  /tmp/prestashop_test_2_checkout.png');
        console.log('  /tmp/prestashop_test_3_email_entered.png');
        console.log('  /tmp/prestashop_test_4_payment_methods.png');

    } catch (error) {
        console.error('ERROR during test:', error.message);
        await page.screenshot({ path: '/tmp/prestashop_test_error.png', fullPage: true });
    }

    await browser.close();
    console.log('\nDone!');
})();

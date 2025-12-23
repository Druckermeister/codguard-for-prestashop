const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({
        ignoreHTTPSErrors: true
    });
    const page = await context.newPage();

    console.log('=== Testing PrestaShop Order Upload API ===\n');

    // Monitor API calls to CodGuard order endpoint
    const apiCalls = [];
    page.on('request', request => {
        const url = request.url();
        if (url.includes('api.codguard.com') && url.includes('orders')) {
            apiCalls.push({
                url: url,
                method: request.method(),
                headers: request.headers(),
                postData: request.postData()
            });
            console.log('ORDER API CALL DETECTED:');
            console.log('  URL:', url);
            console.log('  Method:', request.method());
            console.log('  Payload:', request.postData());
        }
    });

    page.on('response', async response => {
        const url = response.url();
        if (url.includes('api.codguard.com') && url.includes('orders')) {
            console.log('ORDER API RESPONSE:');
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
        const adminUrl = 'http://prestashop.codguard.com.uvds288.active24.cz/admin071ifn4ey44kaog1dw8/';
        const adminEmail = 'kss.tamas@gmail.com';
        const adminPass = 'WPQp3k5DWYWngGa';

        console.log('Step 1: Logging into admin panel...');
        await page.goto(adminUrl, { waitUntil: 'networkidle' });
        await page.screenshot({ path: '/tmp/prestashop_admin_1_login.png', fullPage: true });

        // Login
        await page.fill('input[name="email"]', adminEmail);
        await page.fill('input[name="passwd"]', adminPass);
        await page.screenshot({ path: '/tmp/prestashop_admin_2_credentials.png', fullPage: true });

        await page.click('button[name="submitLogin"]');
        await page.waitForTimeout(3000);
        await page.screenshot({ path: '/tmp/prestashop_admin_3_dashboard.png', fullPage: true });
        console.log('  Logged in successfully');

        console.log('\nStep 2: Navigating to Orders...');
        // Navigate to orders
        await page.goto(adminUrl + 'index.php?controller=AdminOrders', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        await page.screenshot({ path: '/tmp/prestashop_admin_4_orders.png', fullPage: true });

        console.log('\nStep 3: Finding first order...');
        // Click on first order to view details
        const firstOrderLink = await page.locator('table tbody tr a').first();
        if (await firstOrderLink.count() > 0) {
            await firstOrderLink.click();
            await page.waitForTimeout(2000);
            await page.screenshot({ path: '/tmp/prestashop_admin_5_order_details.png', fullPage: true });
            console.log('  Opened order details');

            console.log('\nStep 4: Changing order status...');
            // Find status dropdown
            const statusSelect = await page.locator('select[name="id_order_state"]');
            if (await statusSelect.count() > 0) {
                // Get current status
                const currentStatus = await statusSelect.inputValue();
                console.log('  Current status:', currentStatus);

                // Change to a different status (try "Payment accepted" - usually ID 2)
                await statusSelect.selectOption({ index: 2 });
                console.log('  Changed status in dropdown');

                await page.waitForTimeout(1000);
                await page.screenshot({ path: '/tmp/prestashop_admin_6_status_changed.png', fullPage: true });

                // Click update/save button
                const updateSelectors = [
                    'button[name="submitState"]',
                    'button:has-text("Update")',
                    'button.btn-primary',
                    '#submitState'
                ];

                for (const selector of updateSelectors) {
                    try {
                        await page.click(selector, { timeout: 3000 });
                        console.log('  Clicked update button with selector:', selector);
                        break;
                    } catch (e) {
                        // Try next selector
                    }
                }

                await page.waitForTimeout(3000);
                await page.screenshot({ path: '/tmp/prestashop_admin_7_after_update.png', fullPage: true });

            } else {
                console.log('  WARNING: Status dropdown not found');
            }
        } else {
            console.log('  WARNING: No orders found in the system');
        }

        console.log('\n=== Test Summary ===');
        console.log('Order Upload API Calls Made:', apiCalls.length);
        if (apiCalls.length > 0) {
            console.log('\nDetected API calls:');
            apiCalls.forEach((call, i) => {
                console.log(`\n  ${i + 1}. ${call.method} ${call.url}`);
                if (call.postData) {
                    console.log('     Payload:', call.postData);
                }
            });
        } else {
            console.log('WARNING: No order upload API calls detected!');
            console.log('This could mean:');
            console.log('  1. The module is not enabled');
            console.log('  2. The hook is not registered properly');
            console.log('  3. The new status does not match configured "Good" or "Refused" status');
            console.log('  4. There was an error in the module (check PrestaShop logs)');
        }

        console.log('\nScreenshots saved:');
        console.log('  /tmp/prestashop_admin_1_login.png');
        console.log('  /tmp/prestashop_admin_3_dashboard.png');
        console.log('  /tmp/prestashop_admin_4_orders.png');
        console.log('  /tmp/prestashop_admin_5_order_details.png');
        console.log('  /tmp/prestashop_admin_6_status_changed.png');
        console.log('  /tmp/prestashop_admin_7_after_update.png');

    } catch (error) {
        console.error('ERROR during test:', error.message);
        await page.screenshot({ path: '/tmp/prestashop_admin_error.png', fullPage: true });
    }

    await browser.close();
    console.log('\nDone!');
})();

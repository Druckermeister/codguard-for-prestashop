const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    console.log('Testing rejection message and COD blocking...\n');

    try {
        const testEmail = 'testuser' + Date.now() + '@example.com';
        console.log('Test email:', testEmail);

        // Go directly to checkout
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/en/order', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        // Try to add a product to cart first via URL
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/en/cart?add=1&id_product=1&id_product_attribute=1&action=update', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        // Go to checkout
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/en/order', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        // Fill personal info - use first continue button (guest checkout)
        console.log('Filling personal information...');
        await page.fill('input[name="firstname"]', 'TestUser');
        await page.fill('input[name="lastname"]', 'BlockTest');
        await page.fill('input[name="email"]', testEmail);

        const passwordField = page.locator('input[name="password"]').first();
        if (await passwordField.count() > 0) {
            await passwordField.fill('Test123456');
        }

        // Check privacy checkbox if exists
        const privacyCheck = page.locator('input[name="psgdpr"]').first();
        if (await privacyCheck.count() > 0) {
            await privacyCheck.check();
        }

        await page.waitForTimeout(1000);
        await page.screenshot({ path: '/tmp/rejection_1_personal_filled.png', fullPage: true });

        // Click first continue button (for guest checkout)
        await page.locator('button[name="continue"]').first().click();
        await page.waitForTimeout(3000);
        await page.screenshot({ path: '/tmp/rejection_2_after_personal.png', fullPage: true });

        // Fill address
        console.log('Filling address...');
        const addr1 = page.locator('input[name="address1"]').first();
        if (await addr1.count() > 0) {
            await addr1.fill('Test Street 123');
            await page.fill('input[name="postcode"]', '11000');
            await page.fill('input[name="city"]', 'Prague');

            await page.waitForTimeout(1000);
            await page.screenshot({ path: '/tmp/rejection_3_address_filled.png', fullPage: true });

            const confirmAddr = page.locator('button[name="confirm-addresses"]').first();
            if (await confirmAddr.count() > 0) {
                await confirmAddr.click();
                await page.waitForTimeout(3000);
            }
        }

        await page.screenshot({ path: '/tmp/rejection_4_after_address.png', fullPage: true });

        // Confirm delivery
        console.log('Confirming delivery...');
        const deliveryBtn = page.locator('button[name="confirmDeliveryOption"]').first();
        if (await deliveryBtn.count() > 0) {
            await deliveryBtn.click();
            await page.waitForTimeout(4000);
        }

        // NOW WE SHOULD BE AT PAYMENT METHODS
        await page.screenshot({ path: '/tmp/rejection_5_PAYMENT_METHODS.png', fullPage: true });
        console.log('\n✓ Screenshot saved: /tmp/rejection_5_PAYMENT_METHODS.png');

        // Check for warnings
        const warnings = await page.locator('.alert, .warning, .error, .notifications-container').allTextContents();
        console.log('\nWarnings/Messages:');
        warnings.forEach(w => {
            if (w.trim()) console.log('  -', w.trim());
        });

        // Check payment methods
        console.log('\nPayment methods available:');
        const paymentLabels = await page.locator('.payment-option label, [data-module-name]').allTextContents();
        paymentLabels.forEach(p => {
            if (p.trim()) console.log('  -', p.trim());
        });

        // Check for COD specifically
        const bodyText = await page.textContent('body');
        const hasCOD = bodyText.toLowerCase().includes('cash on delivery') || bodyText.toLowerCase().includes('cod');
        console.log('\nCOD visible:', hasCOD ? 'YES (should be blocked!)' : 'NO (correctly blocked)');

        // Check for rejection message
        const rejectionMsg = 'Unfortunately, we cannot offer Cash on Delivery';
        if (bodyText.includes(rejectionMsg)) {
            console.log('\n✓ REJECTION MESSAGE FOUND:');
            console.log('  "' + rejectionMsg + ' for this order. Please choose a different payment method."');
        }

    } catch (error) {
        console.error('\nError:', error.message);
        await page.screenshot({ path: '/tmp/rejection_error.png', fullPage: true });
    }

    await browser.close();
})();

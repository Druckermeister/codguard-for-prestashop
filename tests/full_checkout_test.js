const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    // Console logging
    page.on('console', msg => {
        if (msg.text().includes('CodGuard')) {
            console.log('  [BROWSER]', msg.text());
        }
    });

    const TEST_EMAIL = '1431@privaterelay.appleid.com'; // Known to have rating 0%

    try {
        console.log('\n========================================');
        console.log('Full Checkout Test - CodGuard Module');
        console.log('========================================\n');

        // Step 1: Add product to cart
        console.log('1. Adding product to cart...');
        await page.goto('https://prestashop.codguard.com.uvds288.active24.cz/en/');
        await page.waitForTimeout(2000);

        const productLink = await page.$('.product-miniature a.product-thumbnail');
        if (productLink) {
            await productLink.click();
            await page.waitForTimeout(2000);

            const addToCart = await page.$('button[data-button-action="add-to-cart"]');
            if (addToCart) {
                await addToCart.click();
                console.log('   ✓ Product added to cart');
                await page.waitForTimeout(3000);

                // Close modal if present
                const proceedBtn = await page.$('.cart-content-btn a');
                if (proceedBtn) {
                    await proceedBtn.click();
                }
            }
        }

        // Step 2: Go to checkout
        console.log('\n2. Going to checkout...');
        await page.goto('https://prestashop.codguard.com.uvds288.active24.cz/en/order');
        await page.waitForTimeout(3000);

        // Step 3: Fill in personal information
        console.log('\n3. Filling personal information...');
        const guestCheckbox = await page.$('input[name="id_gender"][value="1"]');
        if (guestCheckbox) await guestCheckbox.click();

        await page.fill('input[name="firstname"]', 'Test');
        await page.fill('input[name="lastname"]', 'Customer');
        await page.fill('input[name="email"]', TEST_EMAIL);
        await page.check('input[name="customer_privacy"]');
        await page.check('input[name="psgdpr"]');

        console.log('   ✓ Personal info filled with email:', TEST_EMAIL);

        const continueBtn = await page.$('button[name="continue"]');
        if (continueBtn) {
            await continueBtn.click();
            await page.waitForTimeout(3000);
        }

        // Step 4: Fill address
        console.log('\n4. Filling address...');
        await page.fill('input[name="address1"]', '123 Test Street');
        await page.fill('input[name="postcode"]', '12345');
        await page.fill('input[name="city"]', 'TestCity');

        const addressContinue = await page.$('button[name="confirm-addresses"]');
        if (addressContinue) {
            await addressContinue.click();
            await page.waitForTimeout(3000);
        }

        // Step 5: Select shipping
        console.log('\n5. Selecting shipping method...');
        const shippingContinue = await page.$('button[name="confirmDeliveryOption"]');
        if (shippingContinue) {
            await shippingContinue.click();
            await page.waitForTimeout(3000);
        }

        // Step 6: Check payment page
        console.log('\n6. Checking payment page...');
        await page.waitForTimeout(2000);

        // Check page source
        const pageSource = await page.content();

        console.log('\n--- Page Analysis ---');
        console.log('Has CodGuard CSS:', pageSource.includes('codguard.css'));
        console.log('Has CodGuard JS:', pageSource.includes('codguard.js'));
        console.log('Has data-codguard-config:', pageSource.includes('data-codguard-config'));
        console.log('Has alert-warning:', pageSource.includes('alert-warning'));
        console.log('Has CodGuard comment:', pageSource.includes('<!-- CodGuard:'));

        if (pageSource.includes('data-codguard-config')) {
            const match = pageSource.match(/data-codguard-config='([^']+)'/);
            if (match) {
                console.log('\n✓ CONFIG FOUND:', match[1]);
            }
        }

        // Check for warning banner
        const warningBanner = await page.$('.alert-warning.codguard-warning');
        console.log('Warning banner visible:', !!warningBanner);

        // Check payment options
        const paymentOptions = await page.$$eval('input[type="radio"][name="payment-option"]',
            inputs => inputs.map(input => ({
                id: input.id,
                value: input.value,
                disabled: input.disabled,
                checked: input.checked,
                visible: input.offsetParent !== null
            }))
        );

        console.log('\n--- Payment Options ---');
        paymentOptions.forEach(opt => {
            console.log(`  ${opt.id}: disabled=${opt.disabled}, visible=${opt.visible}`);
        });

        // Check cookies
        const cookies = await context.cookies();
        const codguardCookie = cookies.find(c => c.name === 'codguard_blocked');
        console.log('\n--- Cookies ---');
        console.log('codguard_blocked cookie:', codguardCookie ? codguardCookie.value : 'NOT FOUND');

        // Check config element in DOM
        const configEl = await page.$('[data-codguard-config]');
        if (configEl) {
            const config = await configEl.getAttribute('data-codguard-config');
            console.log('\n✓ Config element in DOM:', config);
        } else {
            console.log('\n✗ Config element NOT in DOM');
        }

        // Take screenshot
        await page.screenshot({ path: '/tmp/full_checkout_test.png', fullPage: true });
        console.log('\n✓ Screenshot saved: /tmp/full_checkout_test.png');

        console.log('\n========================================');
        if (pageSource.includes('data-codguard-config')) {
            console.log('✓ TEST PASSED - Config element present');
        } else {
            console.log('✗ TEST FAILED - Config element missing');
        }
        console.log('========================================\n');

    } catch (error) {
        console.error('\n✗ ERROR:', error.message);
        await page.screenshot({ path: '/tmp/checkout_error.png', fullPage: true });
    }

    await browser.close();
})();

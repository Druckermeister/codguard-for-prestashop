const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    console.log('=== Testing Checkout with Low-Rated Customer ===\n');

    try {
        // Step 1: Go to homepage
        console.log('Step 1: Loading homepage...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/', { waitUntil: 'networkidle' });
        await page.screenshot({ path: '/tmp/checkout_1_homepage.png', fullPage: true });

        // Step 2: Add a product to cart
        console.log('Step 2: Adding product to cart...');

        // Click on first product
        const productLink = page.locator('.product-miniature a.product-thumbnail').first();
        if (await productLink.count() > 0) {
            await productLink.click();
            await page.waitForTimeout(2000);
            await page.screenshot({ path: '/tmp/checkout_2_product.png', fullPage: true });

            // Add to cart
            const addToCartBtn = page.locator('button[data-button-action="add-to-cart"]');
            if (await addToCartBtn.count() > 0) {
                await addToCartBtn.click();
                await page.waitForTimeout(2000);
                console.log('  Product added to cart');
            }
        }

        await page.screenshot({ path: '/tmp/checkout_3_cart_added.png', fullPage: true });

        // Step 3: Go to checkout
        console.log('Step 3: Proceeding to checkout...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/en/order', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        await page.screenshot({ path: '/tmp/checkout_4_personal_info.png', fullPage: true });

        // Step 4: Fill in personal information with test email
        console.log('Step 4: Filling in personal information...');

        // Use a test email that should return low rating
        const testEmail = 'testuser' + Date.now() + '@example.com';
        console.log('  Test email:', testEmail);

        // Check if guest checkout or logged in flow
        const guestRadio = page.locator('input[name="id_gender"][value="1"]');
        if (await guestRadio.count() > 0) {
            console.log('  Using guest checkout...');

            await guestRadio.click();
            await page.fill('input[name="firstname"]', 'TestUser');
            await page.fill('input[name="lastname"]', 'BlockTest');
            await page.fill('input[name="email"]', testEmail);
            await page.fill('input[name="password"]', 'Test123456');
            await page.fill('input[name="birthday"]', '1990-01-01');

            // Accept terms if present
            const termsCheckbox = page.locator('input[name="psgdpr"]');
            if (await termsCheckbox.count() > 0) {
                await termsCheckbox.check();
            }

            await page.waitForTimeout(1000);

            // Click continue
            const continueBtn = page.locator('button[name="continue"]');
            if (await continueBtn.count() > 0) {
                await continueBtn.click();
                await page.waitForTimeout(3000);
            }
        }

        await page.screenshot({ path: '/tmp/checkout_5_after_personal.png', fullPage: true });

        // Step 5: Fill address
        console.log('Step 5: Filling address...');

        const addressInput = page.locator('input[name="address1"]');
        if (await addressInput.count() > 0) {
            await page.fill('input[name="address1"]', 'Test Street 123');
            await page.fill('input[name="postcode"]', '11000');
            await page.fill('input[name="city"]', 'Prague');

            const continueBtn = page.locator('button[name="confirm-addresses"]');
            if (await continueBtn.count() > 0) {
                await continueBtn.click();
                await page.waitForTimeout(3000);
            }
        }

        await page.screenshot({ path: '/tmp/checkout_6_after_address.png', fullPage: true });

        // Step 6: Delivery method
        console.log('Step 6: Selecting delivery method...');

        const deliveryContinue = page.locator('button[name="confirmDeliveryOption"]');
        if (await deliveryContinue.count() > 0) {
            await deliveryContinue.click();
            await page.waitForTimeout(3000);
        }

        await page.screenshot({ path: '/tmp/checkout_7_after_delivery.png', fullPage: true });

        // Step 7: Payment methods - THIS IS WHERE BLOCKING SHOULD HAPPEN
        console.log('Step 7: Checking payment methods...');

        await page.waitForTimeout(2000);

        // Check for warnings/errors
        const warnings = await page.locator('.alert, .warning, .error, .notifications-container').allTextContents();
        if (warnings.length > 0) {
            console.log('\n  ⚠️  WARNINGS/MESSAGES FOUND:');
            warnings.forEach(w => {
                if (w.trim()) {
                    console.log('    -', w.trim());
                }
            });
        }

        // List available payment methods
        const paymentOptions = await page.locator('.payment-option, [data-module-name]').allTextContents();
        console.log('\n  Available payment methods:');
        if (paymentOptions.length > 0) {
            paymentOptions.forEach(p => {
                if (p.trim()) {
                    console.log('    -', p.trim());
                }
            });
        } else {
            console.log('    (No payment methods found or all blocked)');
        }

        // Check specifically for COD
        const codOption = await page.locator('text=/cash.*delivery|cod|cash on delivery/i').count();
        if (codOption > 0) {
            console.log('\n  ✗ COD IS VISIBLE (should be blocked!)');
        } else {
            console.log('\n  ✓ COD IS BLOCKED (correct!)');
        }

        await page.screenshot({ path: '/tmp/checkout_8_payment_methods.png', fullPage: true });

        // Get full page text to see rejection message
        const pageText = await page.textContent('body');
        const rejectionMsg = 'Unfortunately, we cannot offer Cash on Delivery';
        if (pageText.includes(rejectionMsg)) {
            console.log('\n  ✓ REJECTION MESSAGE DISPLAYED:');
            console.log('    "' + rejectionMsg + ' for this order. Please choose a different payment method."');
        } else {
            console.log('\n  ⚠️  Rejection message not found in page');
        }

        console.log('\n=== Test Summary ===');
        console.log('Test Email:', testEmail);
        console.log('Expected Result: COD should be blocked and rejection message shown');
        console.log('\nScreenshots saved:');
        console.log('  /tmp/checkout_1_homepage.png');
        console.log('  /tmp/checkout_2_product.png');
        console.log('  /tmp/checkout_3_cart_added.png');
        console.log('  /tmp/checkout_4_personal_info.png');
        console.log('  /tmp/checkout_5_after_personal.png');
        console.log('  /tmp/checkout_6_after_address.png');
        console.log('  /tmp/checkout_7_after_delivery.png');
        console.log('  /tmp/checkout_8_payment_methods.png ← CHECK THIS ONE');

    } catch (error) {
        console.error('\nERROR during test:', error.message);
        await page.screenshot({ path: '/tmp/checkout_error.png', fullPage: true });
        console.log('Error screenshot saved: /tmp/checkout_error.png');
    }

    await browser.close();
    console.log('\nDone!');
})();

const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    // Listen to all console messages
    page.on('console', msg => {
        console.log('BROWSER:', msg.text());
    });

    // Listen to network requests
    page.on('response', async (response) => {
        if (response.url().includes('customer-rating')) {
            console.log('\n=== API Response ===');
            console.log('URL:', response.url());
            console.log('Status:', response.status());
            try {
                const body = await response.text();
                console.log('Body:', body);
            } catch (e) {
                console.log('Could not read body');
            }
            console.log('===================\n');
        }
    });

    try {
        console.log('===================================');
        console.log('Testing Blocked Customer Checkout');
        console.log('===================================\n');

        const TEST_EMAIL = 'blocked@test.com'; // This should return rating 0

        console.log('1. Going to homepage...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/');
        await page.waitForTimeout(2000);

        console.log('2. Adding a product to cart...');
        // Try to find and click a product
        const productLink = await page.$('.product-miniature a');
        if (productLink) {
            await productLink.click();
            await page.waitForTimeout(2000);

            // Add to cart
            const addToCartBtn = await page.$('button[data-button-action="add-to-cart"]');
            if (addToCartBtn) {
                await addToCartBtn.click();
                await page.waitForTimeout(3000);
            }
        }

        console.log('3. Going to checkout...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/index.php?controller=order');
        await page.waitForTimeout(3000);

        console.log('4. Checking cookies...');
        const cookies = await page.context().cookies();
        const codguardCookies = cookies.filter(c => c.name.includes('codguard'));
        console.log('CodGuard cookies:', codguardCookies.map(c => ({name: c.name, value: c.value})));

        console.log('\n5. Checking for config element...');
        const configElement = await page.$('[data-codguard-config]');
        if (configElement) {
            const configData = await configElement.getAttribute('data-codguard-config');
            console.log('Config found:', configData);
        } else {
            console.log('No config element found');
        }

        console.log('\n6. Checking page HTML for CodGuard elements...');
        const hasWarning = await page.$('.alert-warning');
        console.log('Has warning banner:', !!hasWarning);

        const pageContent = await page.content();
        const hasCodGuardCSS = pageContent.includes('codguard.css');
        const hasCodGuardJS = pageContent.includes('codguard.js');
        const hasConfigDiv = pageContent.includes('data-codguard-config');

        console.log('Has CodGuard CSS:', hasCodGuardCSS);
        console.log('Has CodGuard JS:', hasCodGuardJS);
        console.log('Has config div:', hasConfigDiv);

        console.log('\n7. Checking payment options...');
        const paymentOptions = await page.$$eval('[data-module-name], .payment-option input',
            options => options.map(opt => ({
                tag: opt.tagName,
                type: opt.type,
                module: opt.getAttribute('data-module-name'),
                disabled: opt.disabled,
                name: opt.name
            }))
        );
        console.log('Payment options:', paymentOptions);

        await page.screenshot({ path: '/tmp/blocked_checkout.png', fullPage: true });
        console.log('\n✓ Screenshot saved to /tmp/blocked_checkout.png');

    } catch (error) {
        console.error('Error:', error.message);
        await page.screenshot({ path: '/tmp/error_checkout.png', fullPage: true });
    }

    await browser.close();
})();

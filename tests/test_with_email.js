const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    // Listen to console
    page.on('console', msg => {
        console.log('BROWSER:', msg.text());
    });

    // Listen to API requests
    page.on('response', async (response) => {
        const url = response.url();
        if (url.includes('customer-rating') || url.includes('codguard')) {
            console.log('\n=== API Request ===');
            console.log('URL:', url);
            console.log('Status:', response.status());
            try {
                const body = await response.text();
                console.log('Response:', body);
            } catch (e) {}
            console.log('==================\n');
        }
    });

    try {
        console.log('Testing checkout with email: 1431@privaterelay.appleid.com\n');

        // Go directly to the order page (assuming cart has items and address is filled)
        console.log('1. Going to order/payment page...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/en/order');
        await page.waitForTimeout(5000);

        console.log('\n2. Checking page source for CodGuard elements...');
        const pageContent = await page.content();

        // Check for config element
        const hasConfig = pageContent.includes('data-codguard-config');
        console.log('Has data-codguard-config:', hasConfig);

        if (hasConfig) {
            const configMatch = pageContent.match(/data-codguard-config='([^']+)'/);
            if (configMatch) {
                console.log('Config content:', configMatch[1]);
            }
        }

        // Check for warning banner
        const hasWarning = pageContent.includes('alert-warning');
        console.log('Has alert-warning:', hasWarning);

        // Check for CSS/JS
        const hasCSS = pageContent.includes('codguard.css');
        const hasJS = pageContent.includes('codguard.js');
        console.log('Has CSS:', hasCSS);
        console.log('Has JS:', hasJS);

        console.log('\n3. Checking cookies...');
        const cookies = await context.cookies();
        const codguardCookies = cookies.filter(c => c.name.toLowerCase().includes('codguard'));
        if (codguardCookies.length > 0) {
            console.log('CodGuard cookies found:');
            codguardCookies.forEach(c => console.log('  -', c.name, '=', c.value));
        } else {
            console.log('No CodGuard cookies found');
        }

        console.log('\n4. Checking DOM for config element...');
        const configEl = await page.$('[data-codguard-config]');
        if (configEl) {
            const config = await configEl.getAttribute('data-codguard-config');
            console.log('Config from DOM:', config);
        } else {
            console.log('No config element in DOM');
        }

        console.log('\n5. Checking payment options...');
        const paymentOptions = await page.$$eval('input[type="radio"], .payment-option',
            elements => elements.map(el => ({
                type: el.tagName,
                name: el.name || el.className,
                disabled: el.disabled,
                visible: el.offsetParent !== null
            }))
        );
        console.log('Payment options:', JSON.stringify(paymentOptions, null, 2));

        await page.screenshot({ path: '/tmp/test_email_checkout.png', fullPage: true });
        console.log('\n✓ Screenshot: /tmp/test_email_checkout.png');

    } catch (error) {
        console.error('Error:', error.message);
    }

    await browser.close();
})();

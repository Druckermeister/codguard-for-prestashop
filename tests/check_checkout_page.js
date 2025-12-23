const playwright = require('playwright');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    // Listen to console messages
    page.on('console', msg => {
        if (msg.text().includes('CodGuard')) {
            console.log('BROWSER:', msg.text());
        }
    });

    try {
        console.log('===================================');
        console.log('Checking Checkout Page');
        console.log('===================================\n');

        console.log('1. Navigating to checkout page...');
        await page.goto('http://prestashop.codguard.com.uvds288.active24.cz/index.php?controller=order');
        await page.waitForTimeout(3000);

        console.log('2. Checking for CodGuard assets...');

        // Check if CSS is loaded
        const cssLoaded = await page.evaluate(() => {
            const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
            return links.some(link => link.href.includes('codguard.css'));
        });
        console.log('CSS loaded:', cssLoaded);

        // Check if JS is loaded
        const jsLoaded = await page.evaluate(() => {
            const scripts = Array.from(document.querySelectorAll('script'));
            return scripts.some(script => script.src && script.src.includes('codguard.js'));
        });
        console.log('JS loaded:', jsLoaded);

        // Check for config element
        const configFound = await page.evaluate(() => {
            return !!document.querySelector('[data-codguard-config]');
        });
        console.log('Config element found:', configFound);

        // Check if CodGuardPaymentManager exists
        const managerExists = await page.evaluate(() => {
            return typeof window.CodGuardPaymentManager !== 'undefined';
        });
        console.log('CodGuardPaymentManager exists:', managerExists);

        // Get all loaded stylesheets
        console.log('\n3. All loaded stylesheets:');
        const stylesheets = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
                .map(link => link.href)
                .filter(href => href.includes('modules'));
        });
        stylesheets.forEach(href => console.log('  -', href));

        // Get all loaded scripts
        console.log('\n4. All loaded scripts:');
        const scripts = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('script[src]'))
                .map(script => script.src)
                .filter(src => src.includes('modules'));
        });
        scripts.forEach(src => console.log('  -', src));

        // Check payment options
        console.log('\n5. Payment options on page:');
        const paymentOptions = await page.evaluate(() => {
            const options = Array.from(document.querySelectorAll('[data-module-name], .payment-option'));
            return options.map(opt => ({
                tag: opt.tagName,
                module: opt.getAttribute('data-module-name'),
                text: opt.textContent.substring(0, 50)
            }));
        });
        console.log(paymentOptions);

        await page.screenshot({ path: '/tmp/checkout_page.png', fullPage: true });
        console.log('\n✓ Screenshot saved to /tmp/checkout_page.png');

    } catch (error) {
        console.error('Error:', error.message);
    }

    await browser.close();
})();

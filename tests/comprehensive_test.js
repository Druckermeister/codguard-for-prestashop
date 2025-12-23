const playwright = require('playwright');
const fs = require('fs');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    const consoleMessages = [];
    const networkRequests = [];

    // Capture console messages
    page.on('console', msg => {
        consoleMessages.push(msg.text());
        console.log('[CONSOLE]', msg.text());
    });

    // Capture network requests
    page.on('request', req => {
        if (req.url().includes('codguard') || req.url().includes('customer-rating')) {
            networkRequests.push(req.url());
        }
    });

    try {
        console.log('\n========================================');
        console.log('Comprehensive CodGuard Test');
        console.log('========================================\n');

        console.log('1. Navigating to checkout page...\n');
        await page.goto('https://prestashop.codguard.com.uvds288.active24.cz/en/order');
        await page.waitForTimeout(5000);

        console.log('\n2. Getting page source...\n');
        const html = await page.content();
        fs.writeFileSync('/tmp/checkout_source.html', html);

        // Search for CodGuard in HTML
        console.log('--- HTML Analysis ---');
        console.log('Has codguard.css:', html.includes('codguard.css'));
        console.log('Has codguard.js:', html.includes('codguard.js'));

        const comments = html.match(/<!--.*?CodGuard.*?-->/gs);
        console.log('CodGuard comments found:', comments ? comments.length : 0);
        if (comments) {
            comments.forEach((c, i) => console.log(`  ${i+1}. ${c.substring(0, 100)}...`));
        }

        const configMatch = html.match(/data-codguard-config='([^']+)'/);
        console.log('Has data-codguard-config:', !!configMatch);
        if (configMatch) {
            console.log('  Config:', configMatch[1]);
        }

        console.log('\n--- Console Messages ---');
        const codguardConsole = consoleMessages.filter(m => m.includes('CodGuard'));
        console.log('CodGuard console messages:', codguardConsole.length);
        codguardConsole.slice(0, 10).forEach((m, i) => console.log(`  ${i+1}. ${m}`));

        console.log('\n--- Network Requests ---');
        console.log('CodGuard-related requests:', networkRequests.length);
        networkRequests.forEach((url, i) => console.log(`  ${i+1}. ${url}`));

        console.log('\n--- DOM Check ---');
        const configEl = await page.$('[data-codguard-config]');
        console.log('Config element in DOM:', !!configEl);

        if (configEl) {
            const config = await configEl.getAttribute('data-codguard-config');
            console.log('  Config value:', config);
        }

        const cssLink = await page.$('link[href*="codguard.css"]');
        console.log('CSS link in DOM:', !!cssLink);

        const jsScript = await page.$('script[src*="codguard.js"]');
        console.log('JS script in DOM:', !!jsScript);

        console.log('\n--- JavaScript Global Check ---');
        const hasManager = await page.evaluate(() => typeof window.CodGuardPaymentManager !== 'undefined');
        console.log('CodGuardPaymentManager exists:', hasManager);

        if (hasManager) {
            const managerState = await page.evaluate(() => ({
                initialized: window.CodGuardPaymentManager.initialized,
                blockedMethods: window.CodGuardPaymentManager.blockedMethods,
                rejectionMessage: window.CodGuardPaymentManager.rejectionMessage
            }));
            console.log('Manager state:', JSON.stringify(managerState, null, 2));
        }

        // Search for specific patterns in HTML
        console.log('\n--- Pattern Search ---');
        const patterns = [
            'Hook called',
            'Module ENABLED',
            'Module DISABLED',
            'No email found',
            'Email found',
            'Rating received',
            'BLOCK-START'
        ];

        patterns.forEach(pattern => {
            const found = html.includes(pattern);
            console.log(`Pattern "${pattern}":`, found);
        });

        console.log('\n========================================');
        console.log('Full HTML saved to: /tmp/checkout_source.html');
        console.log('========================================\n');

    } catch (error) {
        console.error('\nERROR:', error.message);
    }

    await browser.close();
})();

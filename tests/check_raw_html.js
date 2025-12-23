const playwright = require('playwright');
const fs = require('fs');

(async () => {
    const browser = await playwright.chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    await page.goto('https://prestashop.codguard.com.uvds288.active24.cz/en/order', { waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);

    const html = await page.content();

    // Save full HTML to file
    fs.writeFileSync('/tmp/page_source.html', html);
    console.log('✓ Saved full HTML to /tmp/page_source.html\n');

    // Search for CodGuard comments
    const codguardMatches = html.match(/<!--.*?CodGuard.*?-->/gs);

    console.log('CodGuard HTML comments found:', codguardMatches ? codguardMatches.length : 0);
    if (codguardMatches) {
        console.log('\nComments:');
        codguardMatches.forEach((comment, i) => {
            console.log(`${i + 1}. ${comment}`);
        });
    }

    // Check if hook output is there at all
    const hasDisplayPaymentTop = html.includes('displayPaymentTop') || html.includes('payment-top');
    console.log('\nHas displayPaymentTop reference:', hasDisplayPaymentTop);

    await browser.close();
})();

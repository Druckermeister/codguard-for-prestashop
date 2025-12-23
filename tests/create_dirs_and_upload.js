const ftp = require('basic-ftp');
const path = require('path');
const fs = require('fs');

async function main() {
    const client = new ftp.Client();

    try {
        console.log('===================================');
        console.log('CodGuard Module Upload via FTP');
        console.log('===================================\n');

        console.log('1. Connecting...');
        await client.access({
            host: 'prestashop.codguard.com.uvds288.active24.cz',
            user: 'prestashop',
            password: 'i4fxKAd9vc',
            secure: false
        });
        console.log('✓ Connected\n');

        console.log('2. Creating directories...');
        await client.cd('www/modules/codguard');

        try {
            await client.send('MKD views');
        } catch (e) {
            console.log('  views directory may exist');
        }

        try {
            await client.send('MKD views/css');
        } catch (e) {
            console.log('  views/css directory may exist');
        }

        try {
            await client.send('MKD views/js');
        } catch (e) {
            console.log('  views/js directory may exist');
        }

        console.log('✓ Directories ready\n');

        console.log('3. Uploading files...');

        // Upload PHP first (this worked before)
        const phpPath = path.join(__dirname, '../codguard.php');
        if (fs.existsSync(phpPath)) {
            console.log('  - Uploading codguard.php...');
            await client.uploadFrom(phpPath, 'codguard.php');
            console.log('    ✓ codguard.php');
        }

        // Upload CSS with binary mode
        const cssPath = path.join(__dirname, '../views/css/codguard.css');
        if (fs.existsSync(cssPath)) {
            console.log('  - Uploading codguard.css...');
            const cssContent = fs.readFileSync(cssPath);
            await client.uploadFrom(cssPath, 'views/css/codguard.css');
            console.log('    ✓ codguard.css');
        }

        // Upload JS with binary mode
        const jsPath = path.join(__dirname, '../views/js/codguard.js');
        if (fs.existsSync(jsPath)) {
            console.log('  - Uploading codguard.js...');
            await client.uploadFrom(jsPath, 'views/js/codguard.js');
            console.log('    ✓ codguard.js');
        }

        console.log('\n===================================');
        console.log('✓ All files uploaded successfully!');
        console.log('===================================\n');

        console.log('Next steps:');
        console.log('1. Clear PrestaShop cache');
        console.log('2. Test the checkout page');
        console.log('3. Check browser console for [CodGuard] messages\n');

    } catch (err) {
        console.error('\n✗ Error:', err.message);
        console.error(err);
    } finally {
        client.close();
    }
}

main();

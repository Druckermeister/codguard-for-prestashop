const ftp = require('basic-ftp');
const path = require('path');
const fs = require('fs');

async function uploadFiles() {
    const client = new ftp.Client();
    client.ftp.verbose = true;
    client.ftp.timeout = 30000;

    try {
        console.log('===================================');
        console.log('CodGuard Module Upload via FTP');
        console.log('===================================\n');

        console.log('1. Connecting to FTP server...');
        await client.access({
            host: 'prestashop.codguard.com.uvds288.active24.cz',
            user: 'prestashop',
            password: 'i4fxKAd9vc',
            secure: false
        });

        console.log('✓ Connected!\n');

        // Navigate to module directory
        console.log('2. Navigating to module directory...');
        await client.cd('www');
        console.log('  - In www directory');

        // List to see what's here
        const wwwContents = await client.list();
        console.log('  - Contents:', wwwContents.map(f => f.name).join(', '));

        await client.cd('modules');
        console.log('  - In modules directory');

        await client.cd('codguard');
        console.log('✓ In codguard module directory\n');

        // Create views directories
        console.log('3. Creating/checking directories...');
        try {
            await client.ensureDir('views');
            await client.ensureDir('views/css');
            await client.ensureDir('views/js');
            console.log('✓ Directories ready\n');
        } catch (e) {
            console.log('Directories may already exist, continuing...\n');
        }

        // Upload CSS file
        console.log('4. Uploading CSS file...');
        const cssPath = path.join(__dirname, '../views/css/codguard.css');
        if (fs.existsSync(cssPath)) {
            await client.uploadFrom(cssPath, 'views/css/codguard.css');
            console.log('✓ CSS uploaded: views/css/codguard.css\n');
        } else {
            console.log('✗ CSS file not found:', cssPath);
        }

        // Upload JS file
        console.log('5. Uploading JS file...');
        const jsPath = path.join(__dirname, '../views/js/codguard.js');
        if (fs.existsSync(jsPath)) {
            await client.uploadFrom(jsPath, 'views/js/codguard.js');
            console.log('✓ JS uploaded: views/js/codguard.js\n');
        } else {
            console.log('✗ JS file not found:', jsPath);
        }

        // Upload PHP file
        console.log('6. Uploading PHP file...');
        const phpPath = path.join(__dirname, '../codguard.php');
        if (fs.existsSync(phpPath)) {
            await client.uploadFrom(phpPath, 'codguard.php');
            console.log('✓ PHP uploaded: codguard.php\n');
        } else {
            console.log('✗ PHP file not found:', phpPath);
        }

        console.log('===================================');
        console.log('✓ Upload completed successfully!');
        console.log('===================================\n');

        console.log('Next steps:');
        console.log('1. Clear PrestaShop cache (via admin or SSH)');
        console.log('2. Test the checkout page');
        console.log('3. Check browser console for [CodGuard] messages\n');

    } catch (err) {
        console.error('✗ Error:', err.message);
        process.exit(1);
    } finally {
        client.close();
    }
}

uploadFiles();

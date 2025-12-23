const ftp = require('basic-ftp');
const path = require('path');
const fs = require('fs');

async function uploadFile(localPath, remotePath) {
    const client = new ftp.Client();
    // client.ftp.verbose = true;

    try {
        await client.access({
            host: 'prestashop.codguard.com.uvds288.active24.cz',
            user: 'prestashop',
            password: 'i4fxKAd9vc',
            secure: false
        });

        await client.uploadFrom(localPath, remotePath);
        console.log('✓', path.basename(localPath));
        return true;
    } catch (err) {
        console.log('✗', path.basename(localPath), '-', err.message);
        return false;
    } finally {
        client.close();
    }
}

async function main() {
    console.log('===================================');
    console.log('CodGuard Module Upload via FTP');
    console.log('===================================\n');

    const files = [
        {
            local: path.join(__dirname, '../views/css/codguard.css'),
            remote: 'www/modules/codguard/views/css/codguard.css'
        },
        {
            local: path.join(__dirname, '../views/js/codguard.js'),
            remote: 'www/modules/codguard/views/js/codguard.js'
        },
        {
            local: path.join(__dirname, '../codguard.php'),
            remote: 'www/modules/codguard/codguard.php'
        }
    ];

    let success = 0;
    let failed = 0;

    for (const file of files) {
        if (!fs.existsSync(file.local)) {
            console.log('✗', path.basename(file.local), '- file not found');
            failed++;
            continue;
        }

        const result = await uploadFile(file.local, file.remote);
        if (result) {
            success++;
        } else {
            failed++;
        }

        // Wait a bit between uploads
        await new Promise(resolve => setTimeout(resolve, 500));
    }

    console.log('\n===================================');
    console.log(`✓ Upload completed: ${success} succeeded, ${failed} failed`);
    console.log('===================================\n');

    if (success > 0) {
        console.log('Next steps:');
        console.log('1. Clear PrestaShop cache');
        console.log('2. Test the checkout page');
        console.log('3. Check browser console for [CodGuard] messages\n');
    }
}

main();

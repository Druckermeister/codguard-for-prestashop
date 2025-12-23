const ftp = require('basic-ftp');
const path = require('path');

async function main() {
    const client = new ftp.Client();

    try {
        console.log('Uploading PaymentOptionsFinder override...\n');

        await client.access({
            host: 'prestashop.codguard.com.uvds288.active24.cz',
            user: 'prestashop',
            password: 'i4fxKAd9vc',
            secure: false
        });

        const localPath = path.join(__dirname, '../override/classes/checkout/PaymentOptionsFinder.php');
        const remotePath = 'www/override/classes/checkout/PaymentOptionsFinder.php';

        await client.uploadFrom(localPath, remotePath);
        console.log('✓ PaymentOptionsFinder.php uploaded\n');

    } catch (err) {
        console.error('✗ Upload failed:', err.message);
    } finally {
        client.close();
    }
}

main();

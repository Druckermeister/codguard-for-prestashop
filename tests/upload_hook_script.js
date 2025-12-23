const ftp = require('basic-ftp');
const path = require('path');

async function upload() {
    const client = new ftp.Client();

    try {
        console.log('Uploading force_register_hooks.php to server...\n');

        await client.access({
            host: 'prestashop.codguard.com.uvds288.active24.cz',
            user: 'prestashop',
            password: 'i4fxKAd9vc',
            secure: false
        });

        await client.cd('www');

        const localPath = path.join(__dirname, 'force_register_hooks.php');
        await client.uploadFrom(localPath, 'force_register_hooks.php');

        console.log('✓ Uploaded to www/force_register_hooks.php');
        console.log('\nNow visit: http://prestashop.codguard.com.uvds288.active24.cz/force_register_hooks.php\n');

    } catch (err) {
        console.error('Error:', err.message);
    } finally {
        client.close();
    }
}

upload();

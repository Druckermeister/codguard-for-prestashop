const ftp = require('basic-ftp');

async function findPath() {
    const client = new ftp.Client();
    client.ftp.verbose = true;

    try {
        await client.access({
            host: 'prestashop.codguard.com.uvds288.active24.cz',
            user: 'prestashop',
            password: 'i4fxKAd9vc',
            secure: false
        });

        console.log('\n=== Root directory ===');
        console.log(await client.list());

        try {
            await client.cd('www');
            console.log('\n=== www directory ===');
            console.log(await client.list());
        } catch (e) {
            console.log('www not found');
        }

        try {
            await client.cd('/');
            await client.cd('prestashop');
            console.log('\n=== prestashop directory ===');
            console.log(await client.list());
        } catch (e) {
            console.log('prestashop not found');
        }

    } catch (err) {
        console.error('Error:', err.message);
    } finally {
        client.close();
    }
}

findPath();

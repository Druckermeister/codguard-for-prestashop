const { execSync } = require('child_process');

console.log('Fetching recent CodGuard logs from server...\n');

try {
    // SSH into server and get recent logs
    const result = execSync(
        `sshpass -p 'i4fxKAd9vc' ssh -p 10222 -o StrictHostKeyChecking=no prestashop@prestashop.codguard.com.uvds288.active24.cz "tail -100 www/var/logs/*.log | grep -i codguard || echo 'No CodGuard logs found'"`,
        { encoding: 'utf-8', timeout: 30000 }
    );

    console.log('Recent CodGuard logs:');
    console.log('===================\n');
    console.log(result);

} catch (error) {
    console.error('Error fetching logs:', error.message);

    // Try alternative method - fetch via FTP/web
    console.log('\nTrying alternative method...');
    console.log('Please check logs manually at:');
    console.log('http://prestashop.codguard.com.uvds288.active24.cz/check_logs.php');
}

# CodGuard PrestaShop Testing Scripts

This directory contains automated testing and deployment scripts for the CodGuard PrestaShop module.

## Quick Start

**Full Deployment & Test:**
```bash
./deploy_and_test_prestashop.sh
```

This single command will:
1. Upload the latest `codguard.php` to the server
2. Register hooks if needed
3. Test rating API (payment blocking)
4. Test order upload API
5. Generate screenshots for verification

## Individual Test Scripts

### 1. Test Rating API (Payment Blocking)
```bash
node test_prestashop_rating_api.js
```
- Tests customer rating check during checkout
- Uses sequential emails: `testuser[timestamp]@example.com`
- Monitors for API calls to CodGuard
- Generates screenshots in `/tmp/`

**What it does:**
- Goes to checkout page
- Enters a test email
- Proceeds to payment methods
- Checks if rating API was called

### 2. Direct API Test
```bash
# Upload test script
lftp -c "set ssl:verify-certificate no; open -u prestashop,i4fxKAd9vc ftp://prestashop.codguard.com.uvds288.active24.cz; cd www; put test_api_call_direct.php"

# Run test
node run_api_test.js
```
- Makes a direct API call from PrestaShop server
- Shows full API response with headers
- Displays rating and action that would be taken
- Logs to PrestaShop logger

**Use this to verify:**
- API keys are configured correctly
- API endpoint is reachable
- Rating is retrieved successfully
- Logic for blocking/allowing is correct

### 3. Test Order Upload API
```bash
node test_prestashop_order_upload.js
```
- Logs into PrestaShop admin
- Navigates to orders
- Changes order status
- Monitors for order upload API calls

**What it tests:**
- Order status change triggers upload
- Order data is sent to CodGuard API
- Proper authentication with private key

### 4. Module Status Checker
```bash
node view_module_status.js
```
- Shows module configuration
- Lists registered hooks
- Displays API key status
- Shows overall module health

### 5. Force Hook Registration
```bash
# Upload script
lftp -c "set ssl:verify-certificate no; open -u prestashop,i4fxKAd9vc ftp://prestashop.codguard.com.uvds288.active24.cz; cd www; put force_register_hooks.php"

# Run registration
node run_force_register.js
```
- Forces re-registration of all hooks
- Works across all shops
- Clears any broken hook registrations

## Important Notes

### Sequential Test Emails
All tests use sequential timestamp-based emails:
- Format: `testuser[timestamp]@example.com`
- Example: `testuser1766329005@example.com`
- This avoids Apple's privaterelay.appleid.com which always returns rating 0

### Server Credentials
From `.env` file:
- FTP: prestashop / i4fxKAd9vc
- Host: prestashop.codguard.com.uvds288.active24.cz
- Admin URL: /admin071ifn4ey44kaog1dw8/
- Admin Login: kss.tamas@gmail.com / WPQp3k5DWYWngGa

### API Configuration
- Shop ID: 25179266
- Public Key: wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38
- Private Key: wt-86d53ffbc7265d4428a33b6cdb539bf482d6400423a61b35488a2b92b091b481
- Rating Tolerance: 35%

## Module Functionality

### 1. Rating API Check (Payment Blocking)
**Hook:** `actionPresentPaymentOptions`
**When:** Payment methods are displayed during checkout
**What it does:**
- Gets customer email
- Calls: `GET https://api.codguard.com/api/customer-rating/{shop_id}/{email}`
- Headers: `x-api-key: {public_key}`
- If rating < 35%, blocks configured payment methods (COD)
- Shows rejection message to customer

### 2. Order Upload
**Hook:** `actionOrderStatusPostUpdate`
**When:** Shop admin changes order status
**What it does:**
- Checks if new status matches "Good Status" (ID: 2) or "Refused Status" (ID: 14)
- Prepares order data (email, phone, address, country, status, outcome)
- Calls: `POST https://api.codguard.com/api/orders/import`
- Headers: `X-API-PUBLIC-KEY`, `X-API-PRIVATE-KEY`
- Payload: `{"orders": [...]}`

## Troubleshooting

### No API calls detected in tests
- This is normal - Playwright doesn't intercept server-side PHP calls
- Check PrestaShop logs or use direct API test instead
- The module still works even if test doesn't detect calls

### Hooks not registered
- Run: `node run_force_register.js`
- This forces re-registration across all shops
- Verify with: `node view_module_status.js`

### Module not enabled
- Go to: PrestaShop Admin > Modules > Module Manager
- Search for "CodGuard"
- Click "Configure" and enable it

## File Structure

```
tests/
├── README.md                          # This file
├── deploy_and_test_prestashop.sh     # Main deployment script
├── test_prestashop_rating_api.js     # Test rating API
├── test_prestashop_order_upload.js   # Test order upload
├── test_api_call_direct.php          # Direct API test (upload to server)
├── run_api_test.js                   # Runs direct API test
├── force_register_hooks.php          # Hook registration script (upload to server)
├── run_force_register.js             # Runs hook registration
└── view_module_status.js             # Check module status
```

## Common Workflows

### After making changes to codguard.php
```bash
./deploy_and_test_prestashop.sh
```

### Verify API is working
```bash
lftp -c "set ssl:verify-certificate no; open -u prestashop,i4fxKAd9vc ftp://prestashop.codguard.com.uvds288.active24.cz; cd www; put test_api_call_direct.php"
node run_api_test.js
```

### Check module health
```bash
node view_module_status.js
```

### Fix broken hooks
```bash
lftp -c "set ssl:verify-certificate no; open -u prestashop,i4fxKAd9vc ftp://prestashop.codguard.com.uvds288.active24.cz; cd www; put force_register_hooks.php"
node run_force_register.js
```

# CodGuard for PrestaShop

COD (Cash on Delivery) fraud prevention module for PrestaShop.

## Description

CodGuard validates customer ratings via API and disables Cash on Delivery payment method for high-risk customers. The module shows an explanation message when COD is not available.

## Features

- Real-time customer rating validation via CodGuard API
- Automatically disables COD payment method for low-rated customers
- Configurable rating tolerance threshold
- Custom rejection messages displayed to customers
- Detailed logging of block events
- Fail-open approach (allows checkout if API fails)
- Compatible with PrestaShop 1.7 and 8.x

## Installation

The module is already uploaded to: `/www/modules/codguard/`

**Install via Admin Panel:**
1. Go to: Modules > Module Manager
2. Search for "CodGuard"
3. Click "Install"
4. Click "Configure" and enter your API credentials

## Configuration

### API Settings

From the `.env` file:
- **Shop ID**: 25179266
- **Public API Key**: wt-cf0f7df5cfc99f8059e22d7f4432fd79a003ed3a4c07079cb617f5f681b10c38
- **Private API Key**: wt-86d53ffbc7265d4428a33b6cdb539bf482d6400423a61b35488a2b92b091b481

### Rating Settings

- **Rating Tolerance**: Minimum acceptable customer rating (0-100%)
  - Default: 35%
  - Example: 35 means customers with rating below 35% will have COD disabled

### Messages

- **Rejection Message**: Customizable message shown when COD is not available
  - Default: "Unfortunately, we cannot offer Cash on Delivery for this order. Please choose a different payment method."

## How It Works

1. Customer logs in or provides email during checkout
2. When payment options are displayed, the module hooks into `hookPaymentOptions`
3. Module queries CodGuard API to get customer rating
4. If rating is below tolerance:
   - Warning message is displayed to customer
   - COD payment option remains visible but blocked
   - Event is logged in database
5. If rating is acceptable or API fails:
   - All payment methods are available (fail-open approach)

## API Endpoint

The module uses the CodGuard Rating API:

```
GET https://api.codguard.com/api/customer-rating/{shop_id}/{email}
Header: x-api-key: {public_key}
```

Response:
```json
{
  "rating": 0.75
}
```

- Rating is returned as decimal (0-1), converted to percentage (0-100%)
- 404 status = new customer (treated as rating 100%)
- Non-200 status = fail open (allow all payment methods)

## Database Tables

The module creates the following tables:

### `ps_codguard_block_events`
Logs all blocked attempts:
- `id_block_event`: Primary key
- `email`: Customer email
- `rating`: Rating that caused the block (0-1 decimal)
- `timestamp`: Unix timestamp
- `ip_address`: Customer IP address

### `ps_codguard_settings`
Persistent settings backup (for configuration recovery)

## Logging

All API calls and decisions are logged to PrestaShop's log system:
- Location: Advanced Parameters > Logs
- Search for "CodGuard" to see module activity

## Module Structure

```
codguard/
├── codguard.php    # Main module file with all logic
├── config.xml      # Module metadata
└── index.php       # Security file
```

## Security

- Fail-open approach: If API is unavailable, customers can still checkout
- All inputs are validated and sanitized
- Database queries use parameterized statements
- API credentials stored securely in PrestaShop configuration

## Support

For issues or questions about the CodGuard API, contact CodGuard support.

## Version

- **Version**: 1.0.0
- **Author**: CodGuard
- **License**: GPL v2 or later
- **PrestaShop Compatibility**: 1.7.x - 8.x

## Changelog

### 1.0.0 (2025-12-20)
- Initial release
- Customer rating validation via `hookPaymentOptions`
- COD payment method filtering for low-rated customers
- Configurable tolerance and warning messages
- Block event logging

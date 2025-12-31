# Installation Guide - CodGuard for PrestaShop

## Important: Correct Download URL

**DO NOT** use the GitHub "Download ZIP" button or archive URL directly!

❌ **Wrong**: `https://github.com/Druckermeister/codguard-for-prestashop/archive/refs/heads/main.zip`
- This creates a zip with folder structure: `codguard-for-prestashop-main/codguard.php`
- PrestaShop won't recognize it as a valid module

✅ **Correct**: Use the release download URL
- `https://github.com/Druckermeister/codguard-for-prestashop/releases/latest/download/codguard.zip`
- This has the proper structure: `codguard/codguard.php`
- PrestaShop will recognize it immediately

## Why the Difference?

PrestaShop requires modules to have this exact structure:
```
codguard.zip
└── codguard/          ← Module folder named after technical name
    ├── codguard.php   ← Main module file
    ├── config.xml
    ├── index.php
    └── views/
```

The GitHub archive creates:
```
codguard-for-prestashop-main.zip
└── codguard-for-prestashop-main/  ← Wrong folder name
    ├── codguard.php
    └── ...
```

## Installation Steps

1. **Download the proper zip**:
   - Go to [Releases](https://github.com/Druckermeister/codguard-for-prestashop/releases)
   - Download `codguard.zip` from the latest release
   - OR use direct URL: `https://github.com/Druckermeister/codguard-for-prestashop/releases/latest/download/codguard.zip`

2. **Install in PrestaShop**:
   - Go to: Modules → Module Manager
   - Click "Upload a module"
   - Select the downloaded `codguard.zip`
   - Click "Install"

3. **Configure**:
   - After installation, click "Configure"
   - Enter your CodGuard API credentials:
     - Eshop ID
     - Public Key
     - Private Key
   - Save settings

## Manual Installation (Alternative)

If you need to install manually via FTP:

1. Extract `codguard.zip`
2. Upload the entire `codguard/` folder to `/modules/` on your PrestaShop server
3. Go to Modules → Module Manager
4. Find "CodGuard" and click "Install"

## Temporary Download

A properly formatted `codguard.zip` is available in your Downloads folder:
`~/Downloads/codguard-prestashop.zip`

Use this for testing until the first GitHub release is created.

## Creating Releases

To create a new release with the proper zip:

1. Update version in `codguard.php`
2. Commit changes
3. Create and push a tag:
   ```bash
   git tag v2.9.0
   git push origin v2.9.0
   ```
4. GitHub Actions will automatically build and attach `codguard.zip` to the release

## Support

For issues, visit: https://github.com/Druckermeister/codguard-for-prestashop/issues

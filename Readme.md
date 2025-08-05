# PayFlex Gateway for PrestaShop

A payment gateway integration for PrestaShop that enables PayFlex payment processing.

## Features
- Seamless integration with PrestaShop 1.6 and 1.7
- Secure payment processing
- Easy installation and configuration
- Supports PrestaShop versions 1.6.x and 1.7.x

## Requirements
- PrestaShop 1.6.x or 1.7.x
- PHP 7.2 or higher

## Installation
1. Download the latest release
2. Upload to your PrestaShop modules directory
3. Install through PrestaShop admin panel
4. Configure your PayFlex credentials

## CRON Setup
To enable automatic payment status updates, configure a CRON job similar to this example:
```bash
*/5 * * * * curl --silent https://your-store-domain/payflex-cron-endpoint
```
**Note:** The actual CRON URL is available in your PrestaShop admin panel: Modules > PayFlex > Configure > Information.

## Support
For support, please email [integrations@payflex.co.za](mailto:integrations@payflex.co.za)

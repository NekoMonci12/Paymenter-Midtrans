## Installation
Manual: Download the code and place it in the `app/Extensions/Gateways/Midtrans` directory.
Automatic: `git clone https://github.com/NekoMonci12/Paymenter-Midtrans /var/www/paymenter/extensions/Gateways/Midtrans`

## Configuration
In the admin panel, go to `Settings > Payment Gateways` and click on the `Midtrans` gateway. Enter Midtrans `MerchantID`/`Server-Key`/`Client-Key` then `Save`.

## Usage
When a user selects the `Midtrans` gateway, they will be redirected to the Midtrans payment page. After the payment is completed, the user will be redirected back to the site. 

## Setup Callback On Midtrans
1. Settings > SNAP Preferences > System Settings
```
Finish URL		= https://yourdomain.com
Unfinish URL		= https://yourdomain.com
Error Payment URL 	= https://yourdomain.com
```
2. Settings > Payment > Finish Redirect URL
```
Finish Redirect URL	= https://yourdomain.com
```
3. Settings > Payment > Notification URL
```
Notification URL			= https://yourdomain.com/extensions/midtrans/webhook
Recurring payment notification URL	= https://yourdomain.com
Account linking notification URL	= https://yourdomain.com
```
# EBANX Magento2 Payment Gateway Extension

This plugin allows you to integrate your Magento2 store with the EBANX payment gateway.
It includes support to installments and custom interest rates.

## Requirements

* PHP >= 5.6
* cURL
* Magento >= 2.0

## Installation
### Source
1. Clone the git repo to your Magento2 root folder
```
git clone --recursive https://github.com/ebanx-integration/ebanx-magento2.git
```
2. Upload the folder app/code and merge them.
3. Open the terminal from the Magento 2 folder and run the command:
```
php bin/magento setup:upgrade
```
4. Remove the content of the folder **pub/static** and run the command:
```
php bin/magento setup:static-content:deploy
```
5. Now run the command:
```
php bin/magento indexer:reindex
```
6. And the composer:
```
composer require ebanx/ebanx
```
7. Set the permissions that you use on your project to the new files.

8. Go to the EBANX Merchant Area, then to **Integration > Merchant Options**.
  1. Change the _Status Change Notification URL_ to:
```
{YOUR_SITE}/ebanx/payment/notify
```
  2. Change the _Response URL_ to:
```
{YOUR_SITE}/ebanx/payment/returns
```
9. That's all!

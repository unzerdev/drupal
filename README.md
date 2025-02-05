# Unzer Payment for Drupal Commerce

Unzer Payment for Drupal Commerce integrates the Unzer Payment methods into your Drupal Commerce Store.
Requires Drupal > 10.3 or 11.x and Drupal commerce > 2.40 or 3.x

## Installation & Upgrade

- Install the module, using composer by running `composer require unzerdev/drupal`
- Alternatively, load the ZIP and unzip it into modules/custom/commerce_unzerpayment in your Drupal folder
- If needed, also manually install the php-SDK via composer by running `composer require unzerdev/php-sdk`
- Activate the plugin Unzer Payment through the `Extension` menu in Drupal backend.

## Configuration

- Browse to `Commerce > Configuration > Payment > Unzer Payment Settings` menu in Drupal backend.
- Enter your Public Key and Private key, which you received from Unzer
- After saving the credentials, all available payment methods are automatically installed and available in your store

## Support

Personal support via e-mail to support@unzer.com or +49 (6221) 43101-00

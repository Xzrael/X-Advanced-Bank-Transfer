=== X Bank Transfer ===
Contributors: xrupt
Tags: woocommerce, bank transfer, payment gateway, receipt, bacs, offline payment
Requires at least: 5.4
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 1.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept direct bank transfer payments. Customers upload their bank payment receipt (JPG, PNG or PDF) during checkout, and admins can preview or download it from the order and emails.

== Description ==

X Bank Transfer is a WooCommerce payment gateway that lets you accept direct bank (wire / BACS) transfers.

* Customers enter your bank account details and upload their payment receipt (JPG, PNG or PDF) during checkout.
* The receipt is stored securely and shown on the order edit screen in your admin.
* The receipt link is also included in order emails so admins and customers can view/download it.
* Fully configurable from WooCommerce > Settings > Payments.
* Built-in checkout styling editor: pick your own brand colors or let the plugin auto-detect your theme colors, with a live preview.
* Clean uninstall - removes only the data it created.

== Installation ==

1. Upload the `x-bank-transfer` folder to the `/wp-content/plugins/` directory, or install the zip via Plugins > Add New > Upload.
2. Activate the plugin through the Plugins screen.
3. Go to WooCommerce > Settings > Payments, find "X Bank Transfer" and click "Set up / Manage".
4. Enable the gateway, set your bank account details, and save.

== Frequently Asked Questions ==

= What file types can customers upload? =
JPG, JPEG, PNG and PDF.

= How do I see a customer's receipt? =
Open the order in your admin. The uploaded receipt shows under the billing details.

== Changelog ==

= 1.1.0 =
* New editable checkout styling panel in the gateway settings (manual brand colors or automatic detection from your theme), with a live preview.
* Colors are applied as CSS variables so every branded element (accent stripe, heading, account card, upload box, file button) updates from one place.
* Rebranded author to Xrupt (Author URI + Plugin URI updated).

= 1.0.1 =
* Checkout markup now uses the HAYZ-exclusive styling classes (`.woocommerce-bacs-bank-details`, `#custom_input`, `.bank_payment_receipt`) so the branded, responsive stylesheet applies.
* Bank name and account number values are bigger and each fills its own full row on mobile.

= 1.0.0 =
* Initial release.

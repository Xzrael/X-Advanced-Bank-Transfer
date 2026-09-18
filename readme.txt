=== Xrupt Bank Transfer Receipts for WooCommerce ===
Contributors: xrupt
Tags: woocommerce, bank transfer, payment gateway, receipt upload, offline payment
Requires at least: 5.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce bank transfer gateway with mandatory receipt upload, styled themed receipt cards and lightbox preview on admin/customer order screens, plus brandable CSS-variable checkout editor with auto theme-color detection and live preview.

== Description ==

**Xrupt Bank Transfer Receipts for WooCommerce is not just another BACS gateway - it fixes what core BACS cannot do.**

WooCommerce core lets customers pick "Direct bank transfer" with no proof of payment. This plugin makes the receipt the centre of the workflow and gives you a fully brandable checkout:

* **Mandatory receipt upload at checkout** - customers must upload JPG, JPEG, PNG or PDF before they can place the order. Upload is handled via secure AJAX with nonce verification; the file is stored as a private media attachment and linked to the order.
* **Secure admin verification** - open the order in WooCommerce > Orders and see a themed receipt card under billing details with image lightbox preview (click to enlarge, tap outside/Esc to close) or PDF view/download - all styled with your Checkout styling colors.
* **Styled customer receipt** - on Thank You and My Account > View Order, customers see the same themed card with Preview (lightbox for images) and Download buttons - never a raw directory link. Emails also use a branded button, not a plain URL.
* **Brandable checkout with CSS variables** - WooCommerce > Settings > Payments > Xrupt Bank Transfer Receipts includes a **Checkout styling** editor. Pick primary/accent colors manually or switch to **Automatic (use my theme colors)** - the plugin reads your Customizer mods and block-theme palette (theme.json). Colors are injected as `:root` CSS variables so every element (accent stripe, heading, account card, upload box, file button) updates from one place. Includes hover/dashed/soft variants and a live preview.
* **Clean & lightweight** - no external services, no tracking, no remote code. Only 2 CSS/JS assets loaded on checkout. Uninstall removes only data it created (receipt attachments tagged `_xbt_receipt` + options/transients).

Built for stores that need proof of payment for wire/BACS/offline transfers and want the checkout to match their brand without custom CSS.

== Installation ==

1. Upload the `xrupt-bank-transfer` folder to the `/wp-content/plugins/` directory, or install the zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins screen.
3. Ensure WooCommerce is active - a notice will appear if not.
4. Go to WooCommerce > Settings > Payments, find "Xrupt Bank Transfer Receipts" and click "Set up / Manage".
5. Enable the gateway, set your bank account details, choose checkout styling (manual or automatic), and save.

== Frequently Asked Questions ==

= What file types can customers upload? =

JPG, JPEG, PNG and PDF. MIME type is verified server-side in addition to extension, and files over 5 MB are rejected. Max upload still respects your WordPress `upload_max_filesize`.

= How do I see a customer's receipt? =

Open the order in WooCommerce > Orders. The uploaded receipt shows under the billing address as a thumbnail (images) or Download button (PDF). It is also linked in order emails.

= How does Automatic theme color mode work? =

When "Automatic (use my theme colors)" is selected, the plugin first checks filters `xbt_theme_primary_color` / `xbt_theme_accent_color`, then common Customizer mods (`primary_color`, `accent_color` etc.), then your block theme palette via `wp_get_global_settings`. If nothing is found it falls back to your manual colors - so it never breaks.

= Does this replace WooCommerce BACS? =

No, it adds a separate gateway so you can run both or disable core BACS. Unlike core BACS this gateway enforces receipt upload and provides the branded checkout editor.

= Is any data sent externally? =

No. All processing is local. No tracking, no remote API calls.

== Screenshots ==

1. Checkout - bank details cards and receipt upload box themed via CSS variables.
2. Gateway settings - bank accounts table + Checkout styling editor with live preview.
3. Admin order - receipt thumbnail / download under billing address.
4. Order email - View receipt link.

== Changelog ==

= 1.2.1 =
* Hotfix: lightbox markup no longer renders on every footer - now only on checkout / thankyou / view-order / my-account and admin order screens, with inline display:none fallback.

= 1.2.0 =
* New styled receipt cards for both admin and customer (Thank You, My Account > View Order) - uses Checkout styling CSS variables (primary/accent) so receipt matches your brand.
* Image lightbox: click thumbnail/Preview to open full receipt in modal; close by tapping backdrop, Close button or Esc; Download button inside modal. PDF opens in new tab with View / Download button.
* Emails now use branded inline buttons (primary color background) instead of plain link - no raw file path shown as text.
* Added `before_woocommerce_init` HPOS `custom_order_tables` + `cart_checkout_blocks` declarations - WooCommerce > Settings > Advanced > Features now shows Compatible for High-Performance Order Storage.
* Update-safe: version bump with upgrade routine keeps `_xbt_receipt_attachment_id` meta and `_xbt_receipt` tagged attachments.

= 1.1.0 =
* New editable checkout styling panel in the gateway settings (manual brand colors or automatic detection from your theme), with a live preview.
* Colors are applied as CSS variables so every branded element (accent stripe, heading, account card, upload box, file button) updates from one place.
* Rebranded to Xrupt Bank Transfer Receipts for WooCommerce (slug: xrupt-bank-transfer).
* Hardened upload handler: MIME verification, 5 MB limit, proper error handling.

= 1.0.1 =
* Checkout markup now uses branded styling classes (`.woocommerce-bacs-bank-details`, `#custom_input`, `.bank_payment_receipt`) so the stylesheet applies.
* Bank name and account number values are bigger and each fills its own full row on mobile.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.1 =
Hotfix 1.2.1 - fixes footer bleed of lightbox. Update immediately if you installed 1.2.0.

= 1.2.0 =
Update to 1.2.0 for styled receipt cards + lightbox + HPOS compatibility. Fully backward compatible - just update via Plugins > Update or re-upload zip; receipts remain linked.

= 1.1.0 =
Rebrand to xrupt-bank-transfer. If upgrading from x-bank-transfer, deactivate the old plugin and activate the new slug. Receipts remain linked via order meta.

== Privacy ==

This plugin does not collect or send any personal data to external services. Uploaded receipts are stored in your WordPress media library as attachments tagged with meta `_xbt_receipt` and are deleted only on plugin uninstall (if you choose to delete).

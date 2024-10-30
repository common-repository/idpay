=== Plugin Name ===
Contributors: idsbdigital
Tags: payment gateway, Malaysia, salary deduction, instalment
Requires at least: 2.4
Tested up to: 5.4
Stable tag: 1.10.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

IDPay payment gateway plugin for WooCommerce. 

== Description ==

Enable customer to pay by salary deduction. Currently IDPay service is only available for customer that working as government servants in Malaysia.

== Frequently Asked Questions ==

= Do I need to register with IDPay in order to use this plugin? =

Yes, you need to have Merchant ID and Secret Key from IDPay. Create your account [here](https://idpay.my/).

= Can I use this plugin without using WooCommerce? =

No.

= What if I have some other question related to IDPay? =

Please submit an email to admin@idpay.my.

== Changelog ==

= 1.10.0 =
* versioning change to semver format
* add check in GET to fix warning message

= 1.9 =
* add error handling when API response become WP_Error

= 1.8 =
* add minimum and maximum range of tenures

= 1.7 =
* fix select2 to load in idpay setting

= 1.6 =
* add months in allowable tenures

= 1.5 =
* store & check status to avoid duplicate order note

= 1.4 =
* change status to on-hold to avoid WC auto cancel unpaid order
* add txn id meta to order to avoid duplicate order note

= 1.3 =
* add transaction number to WC order note
* code & text housekeeping

= 1.2 =
* supports Wordpress 4.9.x
* supports Woocommerce 2.4.x

== Links ==
[Register](https://idpay.my/) for IDPay account to start accepting customer payment by salary deduction.
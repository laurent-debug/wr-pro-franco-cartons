=== WC Pro Franco Cartons ===
Contributors: wearefiber
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WC Pro Franco Cartons enforces wholesaler shipping slot and CHF thresholds for Franco Cartons.

== Description ==
This plugin ensures wholesaler carts respect Franco Cartons lot multiples, slot totals, and CHF thresholds.
It validates carts, adds notices, and injects a free-shipping rate when the configured thresholds are met.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/wc-pro-franco-cartons/` directory, or install via the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure the slot and CHF thresholds in **WooCommerce → Settings → Shipping → Franco Cartons**.

== Frequently Asked Questions ==
= Who is affected by the rules? =
Only users with the `wholesaler` role have the Franco Cartons rules enforced.

= Can I disable enforcement temporarily? =
Filter `wc_pro_franco_cartons_should_enforce` to return `false`.

== Changelog ==
= 0.1.0 =
* Initial release.

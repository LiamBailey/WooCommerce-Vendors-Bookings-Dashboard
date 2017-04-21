=== WooCommerce Vendors Bookings Management ===
Contributors: Webby Scots
Tags: WooCommerce, Vendors, Bookings
Requires at least: 3.0.1
Donate link: https://www.paypal.me/webbyscots/10
Tested up to: 4.7
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Sets up front end management for vendors from the WooThemes WooCommerce Vendors plugin to manage their bookings from the WooCommerce Bookings plugin.

== Description ==

Sets up front end management for vendors from the WooThemes WooCommerce Vendors plugin to manage their bookings from the WooCommerce Bookings plugin.

** Please note ** the base use case I have been building this plugin around dictated that the dashboard should not show the in-cart and was-in-cart statuses. If your use case
needs these statuses I have added a filter woo_vendors_bookings_dashboard_statuses so you can add them back in.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woo-vendors-bookings-dashboard` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Now you can give your vendors the url /vendors-dashboard/{vendor-slug} to allow them to manage their bookings

== Changelog ==

= 1.0.1 =
* Fixes readme

= 1.0.2 =
* changes shop_vendor taxonomy to correct name of wcpv_product_vendors and also checks for vendor management permission to allow page view.

= 2.0.0 =
Big changes to work with current vendors plugin, permissions changed and now checking for admins.

** Note I can't see why this would break backwards compatibility except with very old wcv vendors versions. Please contact me via [Webby Scots Website](https://webbyscots.com/) if you need help.

= 2.0.1 =
* Fix - vendors, vendor admins and vendor managers can now view the vendor dashboard of (their own if they are the vendor) any vendor they are admin/manager for.

= 2.1.0 =
* Fix missing entries in the dashboard - works with more setups

= 2.2.0 =
* Filters out in-cart statuses from results
* Fixes pagination
* Removes rewrite endpoint in favour of rewrite rules so pagintion works
* Removes display of navigation tab at the moment because only one tab is present

= 3.0.0 =
* Improvement: brings querying into main using parse_query hook - for better page title
* Starts moving towards WooCommerce 3.0.0 and latest booking version with backwards compability included

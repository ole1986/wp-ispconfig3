=== WP-ISPConfig 3 ===
Contributors: ole1986, MachineITSvcs
Tags:  host, ISPConfig, hosting, remote, manager, admin, panel, control, wordpress, post, plugin, interface, server
Donate link: https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WP-ISPConfig3&cmd=_donations&business=ole.k@web.de
Requires at least: 5.0
Tested up to: 5.1
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

ISPConfig 3 form generation using Gutenberg Blocks incl. WooCommerce support and individual shortcode creation

== Description ==

The WP ISPConfig 3 plugin allows you to build customer forms for [ISPConfig3](http://www.ispconfig.org) clients, websites, shell accounts and others using the ISPConfig3 REST API.
With the new **Gutenberg Block support** this plugin provides several default templates.

* Create client accounts
* Create websites on existing client logins
* Create websites and client login with a single form
* Update client information
* Update client bank details

If this is not enough, you can take over control by using [action/filter hooks](https://github.com/ole1986/wp-ispconfig3/wiki/Use-wp-ispconfig3-action-and-filter-hooks) provided by this plugin.
Advanded users can extend this plugin using direct access to the ISPConfig3 REST API with a single plugin file and [build individual forms using shortcodes](https://github.com/ole1986/wp-ispconfig3/wiki/Extending-wp-ispconfig3-with-custom-shortcodes)

Check out the [wiki pages on github.com](https://github.com/ole1986/wp-ispconfig3/wiki) for further details

**For WooCommerce integration, please consider installing the [WC Recurring Invoice PDF](https://wordpress.org/plugins/wc-invoice-pdf/) plugin (v1.4)**

== Installation ==

* Search for "wp-ispconfig3" in the "Plugins -> Install" register
* Press Install followed by activate
* Setup the plugin as mentioned below in the Configuration section 

**Configuration**

It is required to configure the plugin in the ISPConfig Panel as well as in the plugin settings (wordpress).

= ISPConfig3 Control panel =

* Open the ISPConfig Control Panel with your favorite browser and login as administrator.
* Navigate to `System -> User Management -> Remote User`
* Add a new remote user with a secure password

= Plugin settings =

* You will need to first create a Remote User account in your ISPConfig Control Panel under the System tab. This user will need all permissions.
* Make sure to choose/create/generate a very secure password. This user account allows full access to your Control Panel REST API.

* Once this account is created, log into your WordPress Dashboard as an administrator
* Active the plugin (if not done yet)
* Open `WP-ISPConfig 3 -> Settings` from the backend
* Fill in ISPConfig information as following (replace localhost with the host the REST API is running)

`SOAP Username: remoteuser`
`SOAP Password: remoteuserpass`
`SOAP Location: http://localhost:8080/remote/index.php`
`SOAP URI: http://localhost:8080/remote/`

== Screenshots ==

1. ISPConfig SOAP settings in wordpress
2. Registration form (client edition) 
3. Registration form (free edition) 
4. Example on how to build a "webspace" product (animated)

== License ==

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with WP Nofollow More Links. If not, see <http://www.gnu.org/licenses/>.

== Changelog ==

= 1.4.2 =
* Fixed post/page save issue when using custom shortcodes

= 1.4.1 =
* Added icon for Gutenberg block "ISPConfig Fields"

= 1.4.0 =
BREAKING CHANGES

* Replaced default shortcodes with Gutenberg Blocks
* Changed concept on how to implement custom shortcode classes
* Added action/filter hooks to customize gutenberg block fields
* Moved WC_Product related classed to the "wc-invoice-pdf" project - https://wordpress.org/plugins/wc-invoice-pdf/

= 1.3.5 =
* Added support for specifying multiple user roles for Website and Database lookup/edit functions

= 1.3.4 =
* Fixed activate/deactivate action in Websites menu
* Amended php files to match PSR-2 coding standard

= 1.3.3 =
* support for passing arguments in shortcode (allow for execution from filters/hooks)
* switched email method from mail to wp_mail
* added ispconfig_register_cancelled.php for reference in hidden form/scripted use

= 1.3.2 =
* support for any input type using the getField method (issue #12)

= 1.3.1 =
* support for self signed certificates

= 1.3.0 =
* IMPORTANT: moved billing (invoice) parts to a separate plugin "WC-InvoicePdf"
* IMPORTANT: Please install the WC-InvoicePdf plugin to migrate your PDF / Recurring settings

= 1.2.1 =
* fixed "my account" page when displaying the invoice details

= 1.2.0 =
* payment reminder for customers (incl. interval and max reminders)
* display reminder counter in invoice list (X reminders sent)
* customize customer reminder template in WP-ISPConfig3 -> Settings -> Templates
* updated langauge file

= 1.1.19 =
* checked compatibility with WP 4.8 and WC 3.1.2
* updated pdf library (encryption issue)

= 1.1.18 =
* fixed security issue allowing customers open other invoices 

= 1.1.17 =
* hotfix: corrected static method error and class inheritance

= 1.1.16 =
* updated pdf library
* updated readme.txt

= 1.1.15 =
* fixed: active status does not change when deactive websites
* updated pdf library fixing possible issues with png images

= 1.1.14 = 
* serveral SOAP reqeust improvements (session close issues)
* added features to view websites and databases from customers in Wordpress
* properly include product meta data into invoices
* added button to test recurring reminders

= 1.1.13 =
* quicker access to major functions using meta box (in order) and tabs (in settings)

= 1.1.12 =
* moved product option "ISPConfig 3" into its own tab
* code optimization

= 1.1.11 =
* hotfix: IspconfigRegister is no more

= 1.1.10 =
* moved domain part on top in checkout form (frontend)
* code optimization

= 1.1.9 =
* fixed: Pdf creation failed in WooCommerce 3.0

= 1.1.8 =
* fixed shipping title
* PLEASE NOTE: Shipping method is used as ONE-TIME FEE (for server, etc..)

= 1.1.7 =
* fixed issue selecting product type in WC 3.0
* compatible with WooCommerce 3.0
* make use of the WC shipping item

= 1.1.6 =
* calculate the discount properly of an order item
* use modern (OOP) DateTime class from PHP 5.X
* fixed: deleted flag becomes ambiguos in invoice list (multi site?!)

= 1.1.5 =
* use abstraction class to manage the custom products
* compatibility check on WP 4.7
* make domain validation static
* improved invoice creation

= 1.1.4 =
* support for one-time fee on first invoice creation 

= 1.1.3 =
* display recurring status for auto-generated invoices
* fixed issue creating recurring invoices with paid status 

= v1.1.2 =
* several translation fixes
* added paypal instant payment on recurring invoices (through "My accout" -> "Invoices") 

= v1.1.1 =
* fixed issue loading options

= v1.1.0 =
* several improvements and code optimization
* PREMIUM: invoice module becomes available for FREE

= v1.0.4 =
* improved options property
* added confirmation subject and body text into settings (Account creation)

= v1.0.3 =
* PREMIUM: load invoicing module, when WooCommerce and the invoice module exists
* autoload all php files starting with 'ispconfig_register' instead of 'ispconfig_'

= v1.0.2 =
* added action hook `ispconfig_options` to include additional options
* optimized th option handling

= v1.0.1 =
* clean up code and moved code at the right places

= v1.0.0 =
* improved version of the orginal wp-ispconfig

(original version wp-ispconfig from https://de.wordpress.org/plugins/wp-ispconfig/)

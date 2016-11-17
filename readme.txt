=== WP-ISPConfig 3 ===
Contributors: ole1986
Tags:  host, ISPConfig, hosting, remote, manager, admin, panel, control, wordpress, post, plugin, interface, server
Donate link: https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WP-ISPConfig3&cmd=_donations&business=ole.k@web.de
Requires at least: 3.1
Tested up to: 4.6
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

ISPConfig 3 ~ Hosting Control Panel ~ Interface for Wordpress including INVOICE MODULE for WooCommerce

== Description ==

The ISPConfig 3 plugin allows you to create clients, websites, shell users into the [ISPConfig](http://www.ispconfig.org) Control Panel by using its REST API.

**Features**

* use registration forms to create sites, accounts, etc. (shortcode: [ispconfig class=IspconfigRegisterClient] or [ispconfig class=IspconfigRegisterFree])
* quickly build a registration form yourself with a single PHP file (details below)
* extend you WooCommerce shoping cart with "Webspace" products

**FREE PREMIUM: INVOICE MODULE** (WooCommerce plugin required)

Extend your "Webspace" products with an INVOICE MODULE

* generate PDF invoices from a WooCommerce Order
* fully cusdtomizable Invoice PDF (90% through ISPConfig3 settings)
* auto-generate invoices on paypal payment
* display invoices in customers "My Account" frontend 
* automated recurring payment reminders to customers

= Build your own registration form =

* copy one of the existing files (`ispconfig_register_client.php` or `ispconfig_register_free.php`)
* change the php class name to "IspconfigYourClass" for example
* rename the file to "ispconfig_your_class.php".
* customize the file with your needs.
* use the shortcode `[ispconfig class=IspconfigYourClass]` to display its content

**PLEASE NOTE**

The autoload function uses CamelCase style to include the php classes. So, `IspconfigYourClass` will be converted to `ispconfig_your_class.php`

== Installation ==

* Search for "wp-ispconfig3" in the "Plugins -> Install" register
* Press Install followed by activate
* Setup the plugin as mentioned below in the Configuration section 

= Configuration =

*SOAP Setup*

In ISPConfig3

* Open the ISPConfig Control Panel with your favorite browser and login as administrator.
* Navigate to `System -> User Management -> Remote User`
* Add a new remote user with a secure password

In Wordpress

once you have activated the wp-ispconfig3 plugin, please navigate to the `WP-ISPConfig 3 -> Settings` menu and setup the SOAP information

`SOAP Username: remoteuser`
`SOAP Password: remoteuserpass`
`SOAP Location: http://localhost:8080/remote/index.php`
`SOAP URI: http://localhost:8080/remote/`

== Screenshots ==

1. ISPConfig SOAP settings in wordpress
2. Registration form (client edition) 
3. Registration form (free edition) 
4. FREE PREMIUM: Invoice list

== License ==

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with WP Nofollow More Links. If not, see <http://www.gnu.org/licenses/>.

== Changelog ==

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

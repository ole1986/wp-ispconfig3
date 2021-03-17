=== WP-ISPConfig 3 ===
Contributors: ole1986, MachineITSvcs
Tags:  host, ISPConfig, hosting, remote, manager, admin, panel, control, wordpress, post, plugin, interface, server
Donate link: https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WP-ISPConfig3&cmd=_donations&business=ole.k@web.de
Requires at least: 5.0
Tested up to: 5.7
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

ISPConfig 3 form generation using Gutenberg Blocks incl. WooCommerce support and individual shortcode creation

== Description ==

The WP ISPConfig 3 plugin allows you to build customer forms for [ISPConfig3](http://www.ispconfig.org) clients, websites, shell accounts and others using the ISPConfig3 REST API.
With the new **Gutenberg Block support** this plugin provides several default templates.

* Create client accounts
* Create websites
* Create databases
* Update client information
* Update client bank details
* Wizard integration

To extend this plugin [action/filter hooks](https://github.com/ole1986/wp-ispconfig3/wiki/Use-wp-ispconfig3-action-and-filter-hooks) can be used.
For complex solutions and access to the ISPConfig3 REST API an [extension plugin](https://github.com/ole1986/wp-ispconfig3/wiki/Extension-plugins) may be more relevent

Check out the [wiki pages on github.com](https://github.com/ole1986/wp-ispconfig3/wiki) for further details

**For WooCommerce integration, please install [WC Recurring Invoice PDF](https://wordpress.org/plugins/wc-invoice-pdf/)**

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
2. Gutenberg create website
3. Gutenberg create database
4. Gutenberg update client (admin area)
5. Gutenberg update client (frontend)

== License ==

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with WP Nofollow More Links. If not, see <http://www.gnu.org/licenses/>.

== Changelog ==

Release notes are provided by the [Github project page](https://github.com/ole1986/wp-ispconfig3/releases)
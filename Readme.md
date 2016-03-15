## WP-ISPConfig 3
```
Contributors: ole1986, etruel
Tags:  host, ISPConfig, hosting, remote, manager, admin, panel, control, wordpress, post, plugin, interfase, server
Requires at least: 3.1
Tested up to: 4.4
Stable tag: trunk
License: GPLv2
```

WordPress interface for ISPConfig 3 ~ Hosting Control Panel.  The plugin allows you to manage ISPConfig through SOAP requests

It was originally writte by etruel - https://de.wordpress.org/plugins/wp-ispconfig/ -  and optimized by ole1986

### Description

The WordPress interface for [ISPConfig](http://www.ispconfig.org) – Hosting Control Panel. An excelent Open Source, transparent, free Server Manager.

By adding a remote user (ISPConfig -> UserManagement - > Remote Users) you can manage clients features and websites from the ISPConfig 3 – Hosting Control Panel.

This plugin is shipped with two additional files to demonstrate a customer registration form using wordpress shortcodes.

Usage: `[ispconfig class=IspconfigRegisterClient]` or `[ispconfig class=IspconfigRegisterFree]`

### Configuration

Before you can start it is neccessary to setup the remote SOAP server and user credentials.
See the example screenshot below:

![WP-ISPConfig3 settings](img/wp-ispconfig-settings.png "WP-ISPConfig3 settings")

### Add a new shortcode class (its easy - believe me)

If you want to add your own registration form, do the following:

- copy one of the existing files (`ispconfig_register_client.php` or `ispconfig_register_free.php`)
- change the php class name to "IspconfigYourClass" for example
- rename the file to "ispconfig_your_class.php".
- customize the file with your needs.
- use the shortcode `[ispconfig class=IspconfigYourClass]` to display its content

PLEASE NOTE:

The autoload function uses CamelCase style to include the php classes. So, `IspconfigYourClass` will be converted to `ispconfig_your_class.php`

### License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with WP Nofollow More Links. If not, see <http://www.gnu.org/licenses/>.

### Changelog

```
v1.0.1
- clean up code and moved code at the right places

v1.0.0
- improved version of the orginal wp-ispconfig

(original version wp-ispconfig from https://de.wordpress.org/plugins/wp-ispconfig/)
```


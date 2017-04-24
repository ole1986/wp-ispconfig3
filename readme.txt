=== WP-ISPConfig 3 ===
Contributors: ole1986
Tags:  host, ISPConfig, hosting, remote, manager, admin, panel, control, wordpress, post, plugin, interface, server
Donate link: https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WP-ISPConfig3&cmd=_donations&business=ole.k@web.de
Requires at least: 3.1
Tested up to: 4.7
Stable tag: trunk
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

ISPConfig 3 ~ Hosting Control Panel ~ registration form interface incl. invoice module and product management for WooCommerce

== Description ==

The WP ISPConfig 3 plugin allows you to frontend/customer registration forms to create clients, websites, shell users directly into the [ISPConfig](http://www.ispconfig.org) Control Panel by using its REST API.

**General Features**

* shipped with two default registration form for websites (ispconfig_register_client / ispconfig_register_free)
* use the shortcode "[ispconfig class=IspconfigRegisterClient]" and "[ispconfig class=IspconfigRegisterFree]" to display the forms at any place
* build your own forms (incl. shortcodde) in less than 5 minutes (Check out the Installation section for more details)

**Check out the Installation tab for more details on how to build your own extension**

**WooCommerce Features**

* extend the shopping cart with "Webspace" products allowing customer to order domain or webspace packages
* create offers and invoices as PDF directly from an order.
* fully customize your PDF invoice (incl. company logo, footer, text, address lines, ...)
* automate recurring invoice submission for customers
* view all invoices from the WP ISPconfig invoice module

*And alot more more*

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

* Log into your wordpress page as administrator and switch to the backend
* Active the plugin (if not done yet)
* Open `WP-ISPConfig 3 -> Settings` from the backend
* Fill in ISPConfig information as following (replace localhost with the host the REST API is running)

`SOAP Username: remoteuser`
`SOAP Password: remoteuserpass`
`SOAP Location: http://localhost:8080/remote/index.php`
`SOAP URI: http://localhost:8080/remote/`

**Customize the plugin**

You can extend the plugin with your own registration form by using PHP only inside the plugin folder (E.g. wp-content/plugins/wp-ispconfig3/).

**PLEASE NOTE:** BACKUP YOUR EXTENSION FILE(S) BEFORE UPDATING THE PLUGIN

*A general knowledge about PHP and OOP is recommended*

The simpliest way to build your on registration form is to copy one of the existing shortcode classes (either "ispconfig_register_client.php" or "ispconfig_register_free.php").
In this examples we will use the "ispconfig_register_client.php".

* Copy the "ispconfig_register_client.php" into "ispconfig_register_custom.php"
* Open the "ispconfig_register_custom.php" and rename the class name in line 8 to "IspconfigRegisterCustom"
* Replace or amend the method "Display($opt = null)" with your needs
* Replace or amend the method "onPost()" to manage the page post request
* Place a page using the shortcode "[ispconfig class=IspconfigRegisterCustom]" to display the form

Below is a minimal version to register a client user with a website.


`
class IspconfigRegisterCustom {
    public static $Self;

    public static function init() {
        if(!self::$Self) self::$Self = new self();
    }
    
    /**
     * Called when user submits the data from register form - see Ispconfig::Display() for more details
     */
    protected function onPost(){
        if ( 'POST' !== $_SERVER[ 'REQUEST_METHOD' ] ) return;
        
        $opt = ['username' => $_POST['username'], 'password' => $_POST['password'], 'domain'   => $_POST['domain']];

        try{
            $client = Ispconfig::$Self->withSoap();
            // check if the client name already exist in ISPConfig           
            $client = Ispconfig::$Self->GetClientByUser($opt['username']);
            if(!empty($client)) throw new Exception('The user already exist. Please choice a different name');

            // add the customer
            Ispconfig::$Self->AddClient($opt)
                            ->AddWebsite( ['domain' => $opt['domain'], 'password' => $opt['password']] );
            
            echo "<div class='ispconfig-msg ispconfig-msg-success'>" . sprintf(__('Your account %s has been created', 'wp-ispconfig3'), $opt['username']) ."</div>";

            Ispconfig::$Self->closeSoap();
        } catch (SoapFault $e) {
            //WPISPConfig3::soap->__getLastResponse();
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: '.$e->getMessage() .'</div>';
        } catch (Exception $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">Exception: '.$e->getMessage() . "</div>";
        }
    }
    
    /**
     * Use the shortcode "[ispconfig class=IspconfigRegisterCustom]" to display the form
     */
    public function Display($opt = null){
        
        ?>
        <div class="wrap">
            <?php $this->onPost(); ?>
            <form method="post" class="ispconfig" action="">
            <?php
                WPISPConfig3::getField('username', 'User:', 'text', ['container' => 'div']);
                WPISPConfig3::getField('password', 'Pass:', 'text', ['container' => 'div']);
                WPISPConfig3::getField('domain', 'Domain:', 'text', ['container' => 'div']);
            ?>
            <p><input type="submit" class="button-primary" name="submit" value="Submit" /></p>
            </form>
        </div>
        <?php
    }
}
`

== Screenshots ==

1. ISPConfig SOAP settings in wordpress
2. Registration form (client edition) 
3. Registration form (free edition) 
4. Invoice list module
5. Example on how to build a "webspace" product (animated)
6. Complete list of settings (animated)

== License ==

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with WP Nofollow More Links. If not, see <http://www.gnu.org/licenses/>.

== Changelog ==


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

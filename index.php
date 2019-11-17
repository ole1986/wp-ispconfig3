<?php
/**
 * Plugin Name: WP-ISPConfig3
 * Description: ISPConfig3 plugin allows you to register customers through wordpress frontend using shortcodes.
 * Version: 1.5.2
 * Author: ole1986 <ole.k@web.de>, MachineITSvcs <contact@machineitservices.com>
 * Author URI: https://github.com/ole1986/wp-ispconfig3
 * Text Domain: wp-ispconfig3
 * Domain Path: /languages
 */
defined('ABSPATH') or die('No script kiddies please!');

if (! defined('WPISPCONFIG3_VERSION')) {
    define('WPISPCONFIG3_VERSION', '1.5.2');
}

if (! defined('WPISPCONFIG3_PLUGIN_DIR')) {
    define('WPISPCONFIG3_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('WPISPCONFIG3_PLUGIN_URL')) {
    define('WPISPCONFIG3_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once 'ispconfig-abstract.php';
require_once 'ispconfig.php';
require_once 'ispconfig-blocks.php';

load_plugin_textdomain('wp-ispconfig3', false, basename(dirname(__FILE__)) . '/languages');

if (!class_exists('WPISPConfig3')) {
    add_action('init', ['WPISPConfig3', 'init'], 1);

    register_activation_hook(plugin_basename(__FILE__), array( 'WPISPConfig3', 'install' ));
    register_deactivation_hook(plugin_basename(__FILE__), array( 'WPISPConfig3', 'deactivate' ));
    register_uninstall_hook(plugin_basename(__FILE__), array( 'WPISPConfig3', 'uninstall' ));

    class WPISPConfig3
    {
        /**
         * Language domain
         */
        const TEXTDOMAIN = 'wpispconfig3';
        /**
         * Option key stored in wordpress 'wp_options' table
         */
        const OPTION_KEY = 'WPISPConfig3_Options';
        /**
         * Options being stored in wp_options
         */
        public static $OPTIONS = [
            'soapusername' => 'remote_user',
            'soappassword' => 'remote_user_pass',
            'soap_location' => 'http://localhost:8080/remote/index.php',
            'soap_uri' => 'http://localhost:8080/remote/',
            'skip_ssl' => 0,
            'confirm_actions' => [],
            'confirm_subject'=> 'Your ISPConfig account has been created',
            'confirm_body'   => "Your ISPConfig account has been successfully create.\nHere are the details:\n\n
                                Username: [client_username]\n
                                Password: [client_password]\n
                                Domain: [website_domain]\n
                                Login with your account on http://YOURWEBSITE:8080",
            'default_domain' => 'yourdomain.tld',
            'sender_name' => 'Your Sevice name',
            'domain_check_global' => 1,
            'domain_check_usedig' => 0,
            'domain_check_expiration' => 600,
            'domain_check_regex' => '((?!-))(xn--)?[a-z0-9][a-z0-9-_]{0,61}[a-z0-9]{0,1}\.(xn--)?([a-z0-9\-]{1,61}|[a-z0-9-]{1,30}\.[a-z]{2,})',
            'domain_check_whitelist' => '',
            'user_roles' => ['customer', 'subscriber'],
            'user_password_sync' => 0
        ];

        /**
         * Initialize the text domain and load the constructor
         *
         * @return void
         */
        public static function init()
        {
            new self();
        }
        
        public function __construct()
        {
            // load the options from database and make available in WPISPConfig3::$OPTIONS
            $this->load_options();

            // initialize the Ispconfig class
            new Ispconfig();

            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website.php')) {
                include_once WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website.php';
                IspconfigWebsite::init();
            }
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database.php')) {
                include_once WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database.php';
                IspconfigDatabase::init();
            }

            // action hook to load the scripts and style sheets (frontend)
            add_action('wp_enqueue_scripts', array($this, 'wpdocs_theme_name_scripts'));
            // action hook to load the scripts and style sheets (backend)
            add_action('admin_enqueue_scripts', array($this, 'wp_admin_scripts'));
            

            if (self::$OPTIONS['user_password_sync']) {
                add_action('profile_update', [$this, 'sync_user_password']);
                add_action('woocommerce_customer_reset_password', [$this, 'sync_user_password_from_woocommerce_reset']);
            }

            // skip the rest if its a frontend request
            if (! is_admin()) {
                return;
            }
            
            // below is for backend only
            add_action('admin_menu', array( $this, 'admin_menu' ));
        }

        public function sync_user_password_from_woocommerce_reset($user)
        {
            $this->sync_user_password($user->ID);
        }

        public function sync_user_password($user_id)
        {
            // when both wordpress and woocommerce password submission is empty, skip it
            if (empty($_POST['pass1']) && empty($_POST['password_1'])) {
                return;
            }

            // skip when pass_1 and pass_2 are provided (wordpress) but not equal
            if (isset($_POST['pass1'], $_POST['pass2']) && !$_POST['pass1'] === $_POST['pass2']) {
                return;
            }

            // skip when password_1 and password_2 are provided (woocommerce -> my account) but not equal
            if (isset($_POST['password_1'], $_POST['password_2']) && !$_POST['password_1'] === $_POST['password_2']) {
                return;
            }

            if (!empty($_POST['pass1'])) {
                $pass = $_POST['pass1'];
            } elseif (!empty($_POST['password_1'])) {
                $pass = $_POST['password_1'];
            }

            $user = get_user_by('id', $user_id);

            $matchRoles = array_filter(self::$OPTIONS['user_roles'], function ($r) use ($user) {
                return in_array($r, $user->roles);
            });
            
            if (!empty($matchRoles)) {
                $ispconfig_user = Ispconfig::$Self->withSoap()->GetClientByUser($user->user_login);

                if (!empty($ispconfig_user)) {
                    // trying to update clients password on ISPConfig
                    $ok = Ispconfig::$Self->ClientPassword($pass);
                }
            }
        }

        /**
         * Load the neccessary JS and stylesheets
         * HOOK: wp_enqueue_scripts
         */
        public function wpdocs_theme_name_scripts()
        {
            wp_enqueue_style('style-name', WPISPCONFIG3_PLUGIN_URL . 'style/ispconfig.css');
            wp_enqueue_script('ispconfig-script', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig.js');
        }

        /**
         * Load neccessary JS scripts for the admin area
         * HOOK: admin_enqueue_scripts
         */
        public function wp_admin_scripts()
        {
            wp_enqueue_script('ispconfig-admin-script', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig-admin.js');
        }
        
        /**
         * Display the ISPConfig3 admin menu
         *
         * @return void
         */
        public function admin_menu()
        {
            // show the main menu 'WP-ISPConfig 3' in backend
            add_menu_page(__('WP-ISPConfig 3', 'wp-ispconfig3'), __('WP-ISPConfig 3', 'wp-ispconfig3'), 'null', 'ispconfig3_menu', null, WPISPCONFIG3_PLUGIN_URL.'ispconfig.png', 3);
            // display the settings menu entry
            add_submenu_page('ispconfig3_menu', __('Settings'), __('Settings'), 'edit_themes', 'ispconfig_settings', array($this, 'DisplaySettings'));
            // if woocommerce and invoicing module for ISPConfig is avialble, load it and display invoices menu entry
            
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website.php')) {
                add_submenu_page('ispconfig3_menu', __('Websites', 'wp-ispconfig3'), __('Websites', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_websites', array('IspconfigWebsite', 'DisplayWebsites'));
            }
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database.php')) {
                add_submenu_page('ispconfig3_menu', __('Databases', 'wp-ispconfig3'), __('Databases', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_databases', array('IspconfigDatabase', 'DisplayDatabases'));
            }
        }
        
        /**
         * When option update post data are being submitted
         *
         * @return void
         */
        private function onUpdateSettings()
        {
            if ('POST' === $_SERVER[ 'REQUEST_METHOD' ]) {
                foreach (self::$OPTIONS as $k => &$v) {
                    if (in_array($k, ['domain_check_global', 'domain_check_usedig', 'user_password_sync', 'skip_ssl', 'confirm'])) {
                        $v = !empty($_POST[$k]) ? 1 : 0;
                    } elseif (is_array($_POST[$k])) {
                        array_map(function ($item) {
                            return sanitize_text_field($item);
                        }, $_POST[$k]);

                        $v = $_POST[$k];
                    } elseif ($k == 'confirm_body' || $k == 'domain_check_whitelist') {
                        $v = sanitize_textarea_field($_POST[$k]);
                    } else {
                        $v = sanitize_text_field($_POST[$k]);
                    }
                }
                
                if ($this->update_options()) {
                    ?><div class="updated"><p> <?php _e('Settings saved', 'wp-ispconfig3');?></p></div><?php
                }

                $this->load_options();
            }
        }
        
        /**
         * Display the settings for ISPConfig
         * action hook 'ispconfig_options' supported
         *
         * @return void
         */
        public function DisplaySettings()
        {
            $this->onUpdateSettings();
                       
            $cfg = self::$OPTIONS;
            ?>
            <?php
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (!is_plugin_active('wc-invoice-pdf/wc-invoice-pdf.php')) {
                echo '<div class="notice notice-info"><p><a href="'.get_admin_url().'/plugin-install.php?s=WC%20Recurring%20InvoicePdf&tab=search&type=term">Click here</a> to install the <strong>WC-InvoicePdf</strong> plugin for a recurring invoice / billing feature</p></div>';
            }

            if (isset(self::$OPTIONS['wc_pdf_title'])) {
                echo '<div class="notice notice-error"><p><strong>IMPORTANT:</strong> Please install and <strong>MIGRATE</strong> the WC-InvoicePdf plugin before saving - Otherwise PDF and recurring settings get lost!!!</p></div>';
            }
            ?>
            <div class="wrap">
                <h2><?php _e('WP-ISPConfig 3 Settings', 'wp-ispconfig3');?></h2>
                <h2 id="wp-ispconfig-tabs" class="nav-tab-wrapper">
                    <a href="#tab-soap" class="nav-tab nav-tab-active"><?php _e('SOAP Settings', 'wp-ispconfig3') ?></a>
                    <a href="#tab-account" class="nav-tab"><?php _e('Account creation', 'wp-ispconfig3') ?></a>
                    <a href="#tab-domain" class="nav-tab"><?php _e('Domain Check', 'wp-ispconfig3') ?></a>
                    <a href="#tab-usermapping" class="nav-tab"><?php _e('User Mapping', 'wp-ispconfig3') ?></a>
                    <a href="#tab-additional" class="nav-tab">Additional</a>
                </h2>
                <form method="post" action="">
                    <div id="wp-ispconfig-settings">
                        <div id="tab-soap">
                            <?php
                            self::getField('soapusername', 'SOAP Username:');
                            self::getField('soappassword', 'SOAP Password:', 'password');
                            self::getField('soap_location', 'SOAP Location:');
                            self::getField('soap_uri', 'SOAP URI:');
                            self::getField('skip_ssl', 'Skip certificate check', 'checkbox');
                            ?>
                        </div>
                        <div id="tab-account">
                            <p>
                            <label style="width: 220px; display:inline-block;vertical-align:top;">Enable confirmation mail</label>
                            <span style="display:inline-block">
                            <?php
                            foreach (['action_create_client', 'action_create_website', 'action_create_database'] as $k => $v) {
                                $checked = in_array($v, self::$OPTIONS['confirm_actions']) ? 'checked' : '';
                                echo "<input type='checkbox' id='$v' name='confirm_actions[]' value='$v' $checked />";
                                echo "<label for='$v'>" . __($v, 'wp-ispconfig3') . '</label><br />';
                            }
                            ?>
                            </span>
                            </p>
                            <?php
                                self::getField('confirm_subject', 'Confirmation subject');
                                self::getField('confirm_body', 'Confirmation Body', 'textarea', ['input_attr' => ['style' => 'width: 340px; height: 150px']]);
                                self::getField('default_domain', 'Default Domain');
                                self::getField('sender_name', 'Sender name');
                            ?>
                        </div>
                        <div id="tab-domain">
                            <p>
                                Usually domains are being checked for availability based on the Websites stored in ISPConfig 3 using the REST API<br />
                                In addition to this a global check using either `whois` or `dig` can be achieved für validation.
                            </p>
                            <?php
                                self::getField('domain_check_global', 'Enable global domain check<br /><i>Unhook this to only validate against ISPConfig 3 domains</i>', 'checkbox');
                                self::getField('domain_check_usedig', 'Use <strong>dig</strong> to verify domain availability.<br /><i>Works only when global domain check is enabled</i>', 'checkbox');
                                self::getField('domain_check_expiration', 'ISPConfig domain name cache expiration (in seconds)', 'number');
                                self::getField('domain_check_whitelist', 'Exclude the following domains from being checked for availability.<br /><i>Use whitespace as separator</i>', 'textarea');
                                self::getField('domain_check_regex', 'Regular expression used to validate the domain');
                            ?>
                        </div>
                        <div id="tab-usermapping">
                            <p>
                            <label style="width: 220px; display:inline-block;vertical-align:top;">User roles being used to match the clients stored in ISPConfig3</label>
                            <span style="display: inline-block">
                            <?php
                            $roles = wp_roles()->roles;
                            foreach ($roles as $k => $v) {
                                $checked = in_array($k, self::$OPTIONS['user_roles']) ? 'checked' : '';
                                echo "<input type='checkbox' id='user_role_$k' name='user_roles[]' value='$k' $checked />";
                                echo "<label for='user_role_$k'>" . $v['name'] . '</label><br />';
                            }
                            ?>
                            </span>
                            </p>
                            <?php
                                self::getField('user_password_sync', 'Enable password syncronization<br /><i>Syncronize the wordpress user password matching the User Mapping</i>', 'checkbox');
                            ?>
                            <p>
                                <span style="color: red">Be careful with password syncronization when using role <strong>administrator</strong>. This can cause a password change on the ISPConfig admin account</span>
                            </p>
                        </div>
                        <div id="tab-additional">
                            <p>Additional options being loaded from other plugins using 'ispconfig_options' hook</p>
                            <?php do_action('ispconfig_options'); ?>
                        </div>
                    </div>
                    <div class="inside">
                        <p></p>
                        <p><input type="submit" class="button-primary" name="submit" value="<?php _e('Save');?>" /></p>
                        <p></p>
                    </div>
                </form>
            </div>
            <?php
        }
        
        public static function getField($name, $title, $type = 'text', $args = [])
        {
            $xargs = [  'container' => 'p',
                        'required' => false,
                        'attr' => [],
                        'label_attr' => ['style' => 'width: 220px; display:inline-block;vertical-align:top;'],
                        'input_attr' => ['style' => 'width: 340px'],
                        'value' => ''
                    ];

            if ($type == null) {
                $type = 'text';
            }

            foreach ($xargs as $k => $v) {
                if (!empty($args[$k])) {
                    $xargs[$k] = $args[$k];
                }
            }

            echo '<' . $xargs['container'];
            foreach ($xargs['attr'] as $k => $v) {
                echo ' '.$k.'="'.$v.'"';
            }
            echo '>';

            if (!empty($title)) {
                echo '<label';
                foreach ($xargs['label_attr'] as $k => $v) {
                    echo ' '. $k . '="'.$v.'"';
                }
                echo '>';
                _e($title, 'wp-ispconfig3');
                if ($xargs['required']) {
                    echo '<span style="color: red;"> *</span>';
                }
                echo '</label>';
            }

            $attrStr = '';
            foreach ($xargs['input_attr'] as $k => $v) {
                $attrStr.= ' '.$k.'="'.$v.'"';
            }

            if (isset(self::$OPTIONS[$name])) {
                $optValue = self::$OPTIONS[$name];
            } else {
                $optValue = $xargs['value'];
            }

            if ($type == 'textarea') {
                echo '<textarea name="'.$name.'" '.$attrStr.'>'  . strip_tags($optValue) . '</textarea>';
            } elseif ($type == 'checkbox') {
                echo '<input type="'.$type.'" name="'.$name.'" value="1"' . (($optValue == '1')?'checked':'') .' />';
            } elseif ($type == 'rte') {
                echo '<div '.$attrStr.'>';
                wp_editor($optValue, $name, ['teeny' => true, 'editor_height'=>200, 'media_buttons' => false]);
                echo '</div>';
            } else {
                echo '<input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$optValue.'"'.$attrStr.' />';
            }

            echo '</' . $xargs['container'] .'>';
        }
        
        /**
         * Load options being stored in wordpress (wp_options)
         *
         * @return void
         */
        protected function load_options()
        {
            $opt = get_option(self::OPTION_KEY, []);

            foreach (self::$OPTIONS as $k => &$v) {
                if (isset($opt[$k])) {
                    if ($k == 'domain_check_regex') {
                        $v = stripslashes($opt[$k]);
                    } else {
                        $v = $opt[$k];
                    }
                }
            }
        }

        /**
         * Store the options into wordpress
         *
         * @return bool True, if option was changed
         */
        public function update_options()
        {
            return update_option(self::OPTION_KEY, self::$OPTIONS);
        }
        
        /**
         * Installation hook
         *
         * @return void
         */
        public static function install()
        {
        }

        /**
         * Plugin deactivation hook
         *
         * @return void
         */
        public static function deactivate()
        {
        }

        /**
         * Plugin uninstallation hook
         *
         * @return void
         */
        public static function uninstall()
        {
            delete_option(self :: OPTION_KEY);
        }
    }
}

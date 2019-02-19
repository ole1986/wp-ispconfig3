<?php
/**
 * Plugin Name: WP-ISPConfig3
 * Description: ISPConfig3 plugin allows you to register customers through wordpress frontend using shortcodes.
 * Version: 1.3.5
 * Author: ole1986 <ole.k@web.de>, MachineITSvcs <contact@machineitservices.com>
 * Author URI: https://github.com/ole1986/wp-ispconfig3
 * Text Domain: wp-ispconfig3
 */
defined('ABSPATH') or die('No script kiddies please!');
 
if (! defined('WPISPCONFIG3_PLUGIN_DIR')) {
    define('WPISPCONFIG3_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('WPISPCONFIG3_PLUGIN_URL')) {
    define('WPISPCONFIG3_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once 'ispconfig.php';

// autoload php files starting with "ispconfig_register_[...].php" when class is used
spl_autoload_register(
    function ($class) {
        $cls = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], "$1_$2", $class));
        $f = $cls .'.php';
        // only include 'ispconfig_register' files
        if (preg_match("/^ispconfig_register/", $cls)) {
            //error_log('Loading file '. $f .' from class ' . $class);
            include $f;
        }
    }
);

if (!class_exists('WPISPConfig3')) {
    add_action('init', array( 'WPISPConfig3', 'init' ));

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
            'confirm'   => 0,
            'confirm_subject'=> 'Your ISPConfig account has been created',
            'confirm_body'   => "Your payment has been received and your account has been created\n
                                Username: #USERNAME#
                                Password: #PASSWORD#\n
                                Domain: #DOMAIN#\n
                                Login with your account on http://#HOSTNAME#:8080",
            'default_domain' => 'yourdomain.tld',
            'sender_name' => 'Your Sevice name',
            'user_roles' => ['customer', 'subscriber']
        ];

        /**
         * Initialize the text domain and load the constructor
         *
         * @return void
         */
        public static function init()
        {
            WPISPConfig3 :: load_textdomain_file();
            new self();
        }
        
        public function __construct()
        {
            // load the options from database and make available in WPISPConfig3::$OPTIONS
            $this->load_options();

            // initialize the Ispconfig class
            Ispconfig::init();

            // load the ISPConfig3 invoicing module (PREMIUM)
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php')) {
                include_once WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php';
                IspconfigWc::init();
            }
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
            
            // skip the rest if its a frontend request
            if (! is_admin()) {
                return;
            }
            
            // below is for backend only
            add_action('admin_menu', array( $this, 'admin_menu' ));
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
                if (get_magic_quotes_gpc()) {
                    $_POST = array_map('stripslashes_deep', $_POST);
                }
                
                self::$OPTIONS = $_POST;
                
                if ($this->update_options()) {
                    ?><div class="updated"><p> <?php _e('Settings saved', 'wp-ispconfig3');?></p></div><?php
                }
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
                echo '<div class="notice notice-info"><p>Install the <strong>WC-InvoicePdf</strong> plugin to enable the recurring invoice / billing feature - <a href="'.get_admin_url().'/plugin-install.php?s=WC%20InvoicePdf&tab=search&type=term">Click here</a></p></div>';
            }

            if (isset(self::$OPTIONS['wc_pdf_title'])) {
                echo '<div class="notice notice-error"><p><strong>IMPORTANT:</strong> Please install and <strong>MIGRATE</strong> the WC-InvoicePdf plugin before saving - Otherwise PDF and recurring settings get lost!!!</p></div>';
            }
            ?>
            
            <div class="wrap">
                <h2><?php _e('WP-ISPConfig 3 Settings', 'wp-ispconfig3');?></h2>
                <form method="post" action="">
                <div id="poststuff" class="metabox-holder has-right-sidebar">
                    <div class="postbox inside">
                        <div id="ispconfig-general" class="inside tabs-panel" style="display: block;">
                            <h3><?php _e('SOAP Settings', 'wp-ispconfig3') ?></h3>
                                <?php
                                self::getField('soapusername', 'SOAP Username:');
                                self::getField('soappassword', 'SOAP Password:', 'password');
                                self::getField('soap_location', 'SOAP Location:');
                                self::getField('soap_uri', 'SOAP URI:');
                                self::getField('skip_ssl', 'Skip certificate check', 'checkbox');
                            ?>
                            <h3><?php _e('Account creation', 'wp-ispconfig3') ?></h3>
                            <?php
                                self::getField('confirm', 'Send Confirmation', 'checkbox');
                                self::getField('confirm_subject', 'Confirmation subject');
                                self::getField('confirm_body', 'Confirmation Body', 'textarea', ['input_attr' => ['style' => 'width: 340px; height: 150px']]);
                                self::getField('default_domain', 'Default Domain');
                                self::getField('sender_name', 'Sender name');
                            ?>
                            <h3><?php _e('User Mapping') ?></h3>
                            <p>Choose the below WordPress user roles to match the clients stored in ISPconfig3</p>
                            <?php
                            $roles = wp_roles()->roles;
                            foreach ($roles as $k => $v) {
                                $checked = in_array($k, self::$OPTIONS['user_roles']) ? 'checked' : '';
                                echo "<input type='checkbox' id='user_role_$k' name='user_roles[]' value='$k' $checked /> <label for='user_role_$k'>" . $v['name'] . '</label>&nbsp;';
                            }
                            ?>
                        </div>
                        <?php do_action('ispconfig_options'); ?>
                        <div class="inside">
                            <p></p>
                            <p><input type="submit" class="button-primary" name="submit" value="<?php _e('Save');?>" /></p>
                            <p></p>
                        </div>
                    </div>
                </div>
                </form>

            </div><?php
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
         * Load text domain to provide different languages
         *
         * @return void
         */
        protected static function load_textdomain_file()
        {
            // load plugin textdomain
            load_plugin_textdomain('wp-ispconfig3', false, dirname(plugin_basename(__FILE__)) . '/lang');
        }

        /**
         * Load options being stored in wordpress (wp_options)
         *
         * @return void
         */
        protected function load_options()
        {
            $opt = get_option(self :: OPTION_KEY);
            if (!empty($opt)) {
                self::$OPTIONS = $opt;
            }
        }

        /**
         * Store the options into wordpress
         *
         * @return bool True, if option was changed
         */
        public function update_options()
        {
            return update_option(self :: OPTION_KEY, self::$OPTIONS);
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

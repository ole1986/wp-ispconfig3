<?php
/*
 * Plugin Name: WP-ISPConfig3
 * Description: ISPConfig3 plugin allows you to register customers through wordpress frontend using shortcodes.
 * Version: 1.1.16
 * Author: ole1986 <ole.k@web.de>
 * Author URI: https://github.com/ole1986/wp-ispconfig3
 * Text Domain: wp-ispconfig3
 */
# @charset utf-8
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
 
if ( ! defined( 'WPISPCONFIG3_PLUGIN_DIR' ) ) {
	define( 'WPISPCONFIG3_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPISPCONFIG3_PLUGIN_URL' ) ) {
	define( 'WPISPCONFIG3_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once 'ispconfig.php';

// autoload php files starting with "ispconfig_register_[...].php" when class is used
spl_autoload_register(function($class) { 
    $cls = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], "$1_$2", $class));
    $f = $cls .'.php';
    // only include 'ispconfig_register' files
    if(preg_match("/^ispconfig_register/", $cls)) {
        //error_log('Loading file '. $f .' from class ' . $class);
        include $f;
    }
});

if(!class_exists( 'WPISPConfig3' ) ) {
    add_action('init', array( 'WPISPConfig3', 'init' ) );

    register_activation_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'install' ) );
    register_deactivation_hook(plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'deactivate' ));
    register_uninstall_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'uninstall' ) );

    class WPISPConfig3 {
        /**
         * @var language domain
         */
        const TEXTDOMAIN = 'wpispconfig3';
        /**
         * @var option key stored in wordpress 'wp_options' table
         */
        const OPTION_KEY = 'WPISPConfig3_Options';
        /**
         * @var default options when loaded the first time
         * Once the options are being loaded using load_options method the default options will be overwritten.
         */
        public static $OPTIONS = [
            'soapusername' => 'remote_user',
            'soappassword' => 'remote_user_pass',
            'soap_location' => 'http://localhost:8080/remote/index.php',
            'soap_uri' => 'http://localhost:8080/remote/',
            'confirm'   => 0,
            'confirm_subject'=> 'Your ISPConfig account has been created',
            'confirm_body'   => "Your payment has been received and your account has been created\n
                                Username: #USERNAME#
                                Password: #PASSWORD#\n
                                Domain: #DOMAIN#\n
                                Login with your account on http://#HOSTNAME#:8080",
            'default_domain' => 'yourdomain.tld',
            'sender_name' => 'Your Sevice name'
        ];

        /**
         * initialize the text domain and load the constructor
         */
        public static function init() {
            WPISPConfig3 :: load_textdomain_file();
            new self();
        }
        
        public function __construct() {
            // load the options from database and make available in WPISPConfig3::$OPTIONS
            $this->load_options();

            // initialize the Ispconfig class
            Ispconfig::init();

            // load the ISPConfig3 invoicing module (PREMIUM)
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php')) {
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php');
                IspconfigWc::init();
            }
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website.php'))
            {
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website.php');
                IspconfigWebsite::init();
            }
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database.php'))
            {
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database.php');
                IspconfigDatabase::init();
            }
            
            // action hook to load the scripts and style sheets (frontend)
            add_action('wp_enqueue_scripts', array($this, 'wpdocs_theme_name_scripts') );
            
            // skip the rest if its a frontend request
            if ( ! is_admin() )	return;
            
            // below is for backend only
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        }
        /**
         * Load the neccessary JS and stylesheets 
         * HOOK: wp_enqueue_scripts
         */
        public function wpdocs_theme_name_scripts(){
            wp_enqueue_style( 'style-name', WPISPCONFIG3_PLUGIN_URL . 'style/ispconfig.css' );
            wp_enqueue_script('ispconfig-script', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig.js?_' . time());
        }
        
        /**
         * Display the ISPConfig3 admin menu
         *
         * @access public
         * @return void
         */
        public function admin_menu() {
            // show the main menu 'WP-ISPConfig 3' in backend
            add_menu_page(__('WP-ISPConfig 3', 'wp-ispconfig3'), __('WP-ISPConfig 3', 'wp-ispconfig3'), 'null', 'ispconfig3_menu',  null, WPISPCONFIG3_PLUGIN_URL.'img/ispconfig.png', 3);
            // display the settings menu entry 
            add_submenu_page('ispconfig3_menu', __('Settings'), __('Settings'), 'edit_themes', 'ispconfig_settings',  array($this, 'DisplaySettings') );
            // if woocommerce and invoicing module for ISPConfig is avialble, load it and display invoices menu entry
            
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php'))
            {
                add_submenu_page('ispconfig3_menu', __('Invoices', 'wp-ispconfig3'), __('Invoices', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_invoices',  array('IspconfigWcBackend', 'DisplayInvoices') );
            }
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website.php'))
            {
                add_submenu_page('ispconfig3_menu', __('Websites', 'wp-ispconfig3'), __('Websites', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_websites',  array('IspconfigWebsite', 'DisplayWebsites') );
            }
            if (file_exists(WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database.php'))
            {
                add_submenu_page('ispconfig3_menu', __('Databases', 'wp-ispconfig3'), __('Databases', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_databases',  array('IspconfigDatabase', 'DisplayDatabases') );
            }
        }
        
        /**
         * When option update post data are being submitted
         */
        private function onUpdateSettings(){
            if ( 'POST' === $_SERVER[ 'REQUEST_METHOD' ] ) {
                if ( get_magic_quotes_gpc() ) {
                    $_POST = array_map( 'stripslashes_deep', $_POST );
                }
                
                self::$OPTIONS = $_POST;
                
                if ( $this->update_options() ) {
                    ?><div class="updated"><p> <?php _e( 'Settings saved', 'wp-ispconfig3' );?></p></div><?php
                }
            }
        }
        
        /**
         * Display the settings for ISPConfig (and modules - using action hook 'ispconfig_options') 
         */
        public function DisplaySettings(){
            $this->onUpdateSettings();
                       
            $cfg = self::$OPTIONS;
            ?>
            <div class="wrap">
                <h2><?php _e('WP-ISPConfig 3 Settings', 'wp-ispconfig3' );?></h2>
                <form method="post" action="">
                <div id="poststuff" class="metabox-holder has-right-sidebar">
                    <div id="post-body">
                        <div id="post-body-content">
                            <ul id="ispconfig-tabs" class="category-tabs">
                                <li class="tabs"><a href="#ispconfig-general"><?php _e('General', 'wp-ispconfig3') ?></a></li>
                                <?php do_action('ispconfig_option_tabs') ?>
                            </ul>
                            <div class="postbox inside">
                                <div id="ispconfig-general" class="inside tabs-panel" style="display: block;">
                                    <h3><?php _e('General', 'wp-ispconfig3') ?></h3>
                                    <h4><?php _e( 'SOAP Settings', 'wp-ispconfig3') ?></h4>
                                     <?php 
                                        self::getField('soapusername', 'SOAP Username:');
                                        self::getField('soappassword', 'SOAP Password:', 'password');
                                        self::getField('soap_location', 'SOAP Location:');
                                        self::getField('soap_uri', 'SOAP URI:');
                                    ?>
                                    <h4>Account creation</h4>
                                    <?php
                                        self::getField('confirm', 'Send Confirmation','checkbox');
                                        self::getField('confirm_subject', 'Confirmation subject');
                                        self::getField('confirm_body', 'Confirmation Body', 'textarea');
                                        self::getField('default_domain', 'Default Domain');
                                        self::getField('sender_name', 'Sender name');
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
                    </div>
                </div>
                </form>

            </div><?php
        }
        
        public static function getField($name, $title, $type = 'text', $args = []){
            $xargs = [  'container' => 'p', 
                        'required' => false,
                        'attr' => [], 
                        'label_attr' => ['style' => 'width: 200px; display:inline-block;vertical-align:top;'], 
                        'input_attr' => []
                    ];

            if($type == null) $type = 'text';

            foreach ($xargs as $k => $v) {
                if(!empty($args[$k])) $xargs[$k] = $args[$k];
            }

            echo '<' . $xargs['container'];
            foreach ($xargs['attr'] as $k => $v) {
                echo ' '.$k.'="'.$v.'"';
            }
            echo '>';
            echo '<label';
            foreach ($xargs['label_attr'] as $k => $v)
                echo ' '. $k . '="'.$v.'"';

            echo '>';
            _e($title, 'wp-ispconfig3');
            if($xargs['required']) echo '<span style="color: red;"> *</span>';
            echo '</label>';

            $attrStr = '';
            foreach ($xargs['input_attr'] as $k => $v)
                $attrStr.= ' '.$k.'="'.$v.'"';

            if(isset(self::$OPTIONS[$name]))
                $optValue = self::$OPTIONS[$name];
            else
                $optValue = '';

            if($type == 'text' || $type == 'password')
                echo '<input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$optValue.'"'.$attrStr.' />';
            else if($type == 'textarea') {
                echo '<textarea name="'.$name.'" style="width:25em;height: 150px" '.$attrStr.'>'  . strip_tags($optValue) . '</textarea>';
            }
            else if($type == 'checkbox')
                echo '<input type="'.$type.'" name="'.$name.'" value="1"' . (($optValue == '1')?'checked':'') .''.$attrStr.' />';
            else if($type == 'rte') {
                echo '<div '.$attrStr.'>';
                wp_editor($optValue, $name, ['teeny' => true, 'editor_height'=>200, 'media_buttons' => false]);
                echo '</div>'; 
            }           
            echo '</' . $xargs['container'] .'>';
        }
        
        /**
            * load_textdomain_file
            *
            * @access protected
            * @return void
            */
        protected static function load_textdomain_file() {
            # load plugin textdomain
            load_plugin_textdomain( 'wp-ispconfig3', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang' );			
        }

        /**
            * load_options
            *
            * @access protected
            * @return void
            */
        protected function load_options() {
            $opt = get_option( self :: OPTION_KEY );
            if(!empty($opt)) {
                self::$OPTIONS = $opt;
            }
        }

        /**
         * update_options
         *
         * @access protected
         * @return bool True, if option was changed
         */
        public function update_options() {
            return update_option( self :: OPTION_KEY, self::$OPTIONS );
        }
        
        /**
         * installation
         *
         * @access public
         * @static
         * @return void
         */
        public static function install() {
            // run the installer if ISPConfig invoicing module (if available)
            if(file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php')){
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php' );
                IspconfigWcBackend::install();
            }
        }

        /**
         * when plugin gets deactivated
         *
         * @access public
         * @static
         * @return void
         */
        public static function deactivate(){
            // run the deactivate method from ISPConfig invoicing module (if available)
            if(file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php')){
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php' );
                IspconfigWcBackend::deactivate();
            }
        }

        /**
            * uninstallation
            *
            * @access public
            * @static
            * @global $wpdb, $blog_id
            * @return void
            */
        public static function uninstall() {
            delete_option( self :: OPTION_KEY );
        }
    }
}
<?php
/*
 * Plugin Name: WP-ISPConfig3
 * Description: ISPConfig3 plugin allows you to register customers through wordpress frontend using shortcodes
 * Version: 1.0.3
 * Author: ole1986 <ole.k@web.de>
 * Author URI: https://github.com/ole1986/wp-ispconfig3
 * Text Domain: wp-ispconfig3
 */
# @charset utf-8

//define ('WPLANG', 'de_DE');
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! function_exists( 'add_filter' ) )
	exit;
if ( ! defined( 'WPISPCONFIG3_PLUGIN_DIR' ) ) {
	define( 'WPISPCONFIG3_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPISPCONFIG3_PLUGIN_URL' ) ) {
	define( 'WPISPCONFIG3_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPISPCONFIG3_VERSION' ) ) {
	define( 'WPISPCONFIG3_VERSION', '1.0.0' );
}

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
    add_action( 'init', array( 'WPISPConfig3', 'init' ) );
    add_action('plugins_loaded', array('WPISPConfig3', 'plugins_loaded'));
    register_activation_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'install' ) );
    register_deactivation_hook(plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'deactivate' ));
    register_uninstall_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'uninstall' ) );

    class WPISPConfig3 {
        const TEXTDOMAIN = 'wpispconfig3';
        const OPTION_KEY = 'WPISPConfig3_Options';

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

        public static function init() {
            WPISPConfig3 :: load_textdomain_file();
            new self( TRUE );
        }
        
        public function __construct( $hook_in = FALSE ) {            
            $this->load_options();
            
            add_action('wp_enqueue_scripts', array($this, 'wpdocs_theme_name_scripts') );
                       
            IspconfigRegisterClient::init();
            
            // load the ISPConfig invoicing module by using WooCommerce hooks
            if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) 
                 && file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php')) {
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php' );
                IspconfigWc::init();
            }
            
            if ( ! is_admin() )	return;

            if ( $hook_in ) {
                add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            }
        }

        public function wpdocs_theme_name_scripts(){
            wp_enqueue_style( 'style-name', WPISPCONFIG3_PLUGIN_URL . 'style/wordpress.css' );
            wp_enqueue_script('ispconfig-script', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig.js');
        }
        
        /**
         * admin menu
         *
         * @access public
         * @return void
         */
        public function admin_menu() {
            // show the main menu 'WP-ISPConfig 3' in backend
            add_menu_page(__('WP-ISPConfig 3', 'wp-ispconfig3'), __('WP-ISPConfig 3', 'wp-ispconfig3'), 'null', 'ispconfig3_menu',  null, WPISPCONFIG3_PLUGIN_URL.'img/ispconfig.png', 3);
            // display the settings menu entry 
            add_submenu_page('ispconfig3_menu', __('Settings', 'wp-ispconfig3'), __('Settings', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_settings',  array($this, 'DisplaySettings') );
            // if woocommerce and invoicing module for ISPConfig is avialble, load it and display invoices menu entry
            if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) 
                 && file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php')) {
                add_submenu_page('ispconfig3_menu', __('Invoices', 'wp-ispconfig3'), __('Invoices', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_invoices',  array('IspconfigWcBackend', 'DisplayInvoices') );
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
            $this->load_options();
            $this->onUpdateSettings();
                       
            $cfg = self::$OPTIONS;
            ?>
            <div class="wrap">
                <h2><?php _e( 'WP-ISPConfig 3 Settings', 'wp-ispconfig3' );?></h2>
                <form method="post" action="">
                <div id="poststuff" class="metabox-holder has-right-sidebar">
                    <div id="post-body">
                        <div id="post-body-content">
                            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                                <div class="postbox inside">
                                    <h3><?php _e( 'SOAP Settings', 'wp-ispconfig3' );?></h3>
                                    <div class="inside" style="display: table;margin: 10px 0;">
                                        <?php 
                                        echo self::getField('soapusername', 'SOAP Username:');
                                        echo self::getField('soappassword', 'SOAP Password:', 'password');
                                        echo self::getField('soap_location', 'SOAP Location:');
                                        echo self::getField('soap_uri', 'SOAP URI:');
                                        ?>
                                    </div>
                                    <h3>Account creation</h3>
                                    <div class="inside">
                                    <?php
                                        echo WPISPConfig3::getField('confirm', 'Send Confirmation','checkbox');
                                        echo WPISPConfig3::getField('confirm_subject', 'Confirmation subject');
                                        echo WPISPConfig3::getField('confirm_body', 'Confirmation Body', 'textarea');
                                        echo WPISPConfig3::getField('default_domain', 'Default Domain');
                                        echo WPISPConfig3::getField('sender_name', 'Sender name');
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
                </div>
                </form>

            </div><?php
        }
        
        public static function getField($name, $title, $type = 'text'){
            if($type == 'text' || $type == 'password')
                return '<p><label style="width: 160px; display:inline-block;">'. __( $title, 'wp-ispconfig3') .'</label><input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.self::$OPTIONS[$name].'" /></p>';
            else if($type == 'textarea')
                return "<p><label style='width:160px;display:inline-block;vertical-align:top;height:100px'>". __( $title, 'wp-ispconfig3') . '</label> <textarea name="'.$name.'" style="width:25em;height: 150px">'  . strip_tags(self::$OPTIONS[$name]) . '</textarea></p>';
            else if($type == 'checkbox')
                return '<p><label style="width:160px;display:inline-block;">'.__( $title, 'wp-ispconfig3').'</label> <input type="'.$type.'" name="'.$name.'" value="1"' . ((self::$OPTIONS[$name])?'checked':'') .' /></p>';
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
         * Used to provide a download function as invoice preview
         */
        public function plugins_loaded(){
            global $pagenow, $wpdb;
            if (current_user_can('ispconfig_invoice') && $pagenow=='admin.php' && isset($_GET['invoice'])) {
                if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && file_exists(WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php'))
                {
                    require_once( WPISPCONFIG3_PLUGIN_DIR . 'wc/ispconfig_wc.php' );
                    IspconfigWcBackend::OutputInvoice();
                }
                    
            }
        }

        /**
         * installation
         *
         * @access public
         * @static
         * @return void
         */
        public static function install() {
            // run the installer if ISPConfig invoicing module is available
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
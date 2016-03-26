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

if(!class_exists( 'WPISPConfig3' ) ) {
    add_action( 'init', array( 'WPISPConfig3', 'init' ) );
    add_action('plugins_loaded', array('WPISPConfig3', 'plugins_loaded'));
    register_activation_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'install' ) );
    register_uninstall_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'uninstall' ) );

    class WPISPConfig3 {
        const TEXTDOMAIN = 'wpispconfig3';
        const OPTION_KEY = 'WPISPConfig3_Options';

        protected $options = array(
            'soapusername' => 'remote_user',
            'soappassword' => 'remote_user_pass',
            'soap_location' => 'http://localhost:8080/remote/index.php',
            'soap_uri' => 'http://localhost:8080/remote/',
        );

        public static function init() {
            WPISPConfig3 :: load_textdomain_file();
            new self( TRUE );
        }
        
        public function __construct( $hook_in = FALSE ) {            
            $this->load_options();
            
            add_action('wp_enqueue_scripts', array($this, 'wpdocs_theme_name_scripts') );
            
            require( WPISPCONFIG3_PLUGIN_DIR . 'ispconfig_register.php' );
            
            IspconfigRegisterClient::init($this->options);
            
            // load the ISPConfig invoicing module by using WooCommerce hooks
            if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) 
                 && file_exists(WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php')) {
                require( WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php' );
                IspconfigWc::init($this->options);
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
                 && file_exists(WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php')) {
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
                $this->options = $_POST;
                
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
            
            $this->load_options();
            
            $cfg = $this->options;
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
                                        echo $this->getField('soapusername', 'SOAP Username:');
                                        echo $this->getField('soappassword', 'SOAP Password:', 'password');
                                        echo $this->getField('soap_location', 'SOAP Location:');
                                        echo $this->getField('soap_uri', 'SOAP URI:');
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
        
        private function getField($name, $title, $type = 'text'){
            return '<div><label style="width: 160px; display:inline-block;">'. __( $title, 'wp-ispconfig3') .'</label><input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$this->options[$name].'" /></div>';
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
            * mce_localisation
            *
            * @access public
            * @param array $mce_external_languages
            * @return array
            */
        public function mce_localisation( $mce_external_languages ) {

            if ( file_exists( self :: $dir . 'lang/mce_langs.php' ) )
                $mce_external_languages[ 'inpsydeOembedVideoShortcode' ] = self :: $dir . 'lang/mce-langs.php';
            return $mce_external_languages;
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
                $this->options = $opt;
            }
        }

        /**
         * update_options
         *
         * @access protected
         * @return bool True, if option was changed
         */
        public function update_options() {
            return update_option( self :: OPTION_KEY, $this->options );
        }
        
        /**
         * Used to provide a download function as invoice preview
         */
        public function plugins_loaded(){
            global $pagenow, $wpdb;
            if (current_user_can('ispconfig_invoice') && $pagenow=='admin.php' && isset($_GET['invoice'])) {
                if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && file_exists(WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php'))
                {
                    require_once( WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php' );
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
            if(file_exists(WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php')){
                require_once( WPISPCONFIG3_PLUGIN_DIR . 'woocommerce/ispconfig_wc.php' );
                IspconfigWcBackend::install();
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
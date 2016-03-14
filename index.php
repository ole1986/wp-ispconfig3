<?php
/*
 * Plugin Name: WP-ISPConfig3
 * Description: This plugin allows you to manage some features of ISPConfig by using the remote api
 * Version: 1.0.0
 * Author(s): Ole Koeckemann <ole.k@web.de>, etruel (wp-ispconfig)
 * Author URI: http://www.github.com/wp-ispconfig3
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

    register_uninstall_hook( plugin_basename( __FILE__ ), array( 'WPISPConfig3', 'uninstall' ) );

    class WPISPConfig3 {

        const TEXTDOMAIN = 'wpispconfig3';

        /**		 * Option Key		 */
        const OPTION_KEY = 'WPISPConfig3_Options';

        protected static $default_options = array(
            'soapusername' => 'remote_user',
            'soappassword' => 'remote_user_pass',
            'soap_location' => 'http://localhost:8080/remote/index.php',
            'soap_uri' => 'http://localhost:8080/remote/',
            'confirm_mail' => '1',
        );

        protected $options = array();

        public static function init() {
            WPISPConfig3 :: load_textdomain_file();
            new self( TRUE );
        }
        
        public function __construct( $hook_in = FALSE ) {
            $this->load_options();
            
            require( WPISPCONFIG3_PLUGIN_DIR . 'ispconfig_register.php' );
            IspconfigRegisterClient::init($this->options);
            
            // load some Woocommerce hooks
            IspconfigWcHooks::init($this->options);
            
            if ( ! is_admin() )	return;
            
            if ( $hook_in ) {
                add_action( 'admin_init', array( $this, 'admin_init' ) );
                add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            }
        }

        public function admin_init() {
            
        }

        /**
         * admin menu
         *
         * @access public
         * @return void
         */
        public function admin_menu() {		
            $page= add_menu_page(__('WP-ISPConfig3'), __('WP-ISPConfig3'), 'edit_themes', 'ispconfig_allinone',  array( IspconfigRegisterClient::$Self, 'Display' ), WPISPCONFIG3_PLUGIN_URL.'/prou.png', 3.2); 
            $page= add_submenu_page('ispconfig_allinone', __('Settings', 'wp-ispconfig3'), __('Settings', 'wp-ispconfig3'), 'edit_themes', 'ispconfig_settings',  array( $this, 'add_admin_submenu_page') );
        }
        
        public function register_form_sc( $atts, $content = null ){
            ob_start();
            require( WPISPCONFIG3_PLUGIN_DIR . 'ispconfig-register.php' );
            return ob_get_clean();
        }
        
        public function add_admin_submenu_page () {
            if ( 'POST' === $_SERVER[ 'REQUEST_METHOD' ] ) {
                if ( get_magic_quotes_gpc() ) {
                    $_POST = array_map( 'stripslashes_deep', $_POST );
                }

                # evaluation goes here
                $this->options = $_POST;

                # saving
                if ( $this->update_options() ) {
                    ?><div class="updated"><p> <?php _e( 'Settings saved', 'wp-ispconfig3' );?></p></div><?php
                }
            }
            
            $this->load_options();
            $cfg = $this->options;
            ?>
            <div class="wrap">
                <h2><?php _e( 'WP-ISPConfig3 Settings', 'wp-ispconfig3' );?></h2>
                <form method="post" action="">
                <div id="poststuff" class="metabox-holder has-right-sidebar">
                    <div id="post-body">
                        <div id="post-body-content">
                            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                                <div class="postbox inside">
                                    <h3><?php _e( 'Remote server data', 'wp-ispconfig3' );?></h3>
                                    <div class="inside">
                                        <div><strong><?php _e( 'Complete necessary data to connect to ISPConfig remote server.', 'wp-ispconfig3' );?></strong><br />
                                            <div style="display: table;margin: 10px 0;">
                                            <?php 
                                            echo $this->getField('soapusername', 'SOAP Username:');
                                            echo $this->getField('soappassword', 'SOAP Password:', 'password');
                                            echo $this->getField('soap_location', 'SOAP Location:');
                                            echo $this->getField('soap_uri', 'SOAP URI:');
                                            ?>
                                            <p><?php echo __( 'Send Confirmation:', 'wp-ispconfig3') ?> <input type="checkbox" name="confirm_mail" value="1" <?php echo ($cfg['confirm_mail'])?'checked':'' ?> /></p>
                                            </div>
                                        </div>
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
        
        public function getField($name, $title, $type = 'text'){
            return '<div><label>'. __( $title, 'wp-ispconfig3') .'</label><input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$this->options[$name].'" /></div>';
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

            if ( ! get_option( self :: OPTION_KEY ) ) {
                if ( empty( self :: $default_options ) )
                    return;
                $this->options = self :: $default_options;
                add_option( self :: OPTION_KEY, $this->options , '', 'yes' );
            }
            else {
                $this->options = get_option( self :: OPTION_KEY );
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
            * activation
            *
            * @access public
            * @static
            * @return void
            */
        public static function activate() {

        }

        /**
            * deactivation
            *
            * @access public
            * @static
            * @return void
            */
        public static function deactivate() {

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
            global $wpdb, $blog_id;
            if ( is_network_admin() ) {
                if ( isset ( $wpdb->blogs ) ) {
                    $blogs = $wpdb->get_results(
                        $wpdb->prepare(
                            'SELECT blog_id ' .
                            'FROM ' . $wpdb->blogs . ' ' .
                            "WHERE blog_id <> '%s'",
                            $blog_id
                        )
                    );
                    foreach ( $blogs as $blog ) {
                        delete_blog_option( $blog->blog_id, self :: OPTION_KEY );
                    }
                }
            }
            delete_option( self :: OPTION_KEY );
        }
    }
}
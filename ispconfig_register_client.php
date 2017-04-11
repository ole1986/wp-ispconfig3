<?php 
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

/**
 * Example to register a new customer by using the class Ispconfig
 */
class IspconfigRegisterClient {
    public static $Self;

    public static function init() {
        if(!self::$Self) self::$Self = new self();
    }
    
    /**
     * Called when user submits the data from register form - see Ispconfig::Display() for more details
     */
    protected function onPost(){
        if ( 'POST' !== $_SERVER[ 'REQUEST_METHOD' ] ) return;
        
        try{
            // at least chekc if the client limit template exists in ISPConfig
            $templates = Ispconfig::$Self->withSoap()->GetClientTemplates();

            $filtered = array_filter($templates, function($v){ return $v['template_id'] == $_POST['template']; });
            if(empty($filtered)) throw new Exception('No Limit template found for ID ' . $_POST['template']);

            $opt = ['company_name' => $_POST['empresa'], 
                    'contact_name' => $_POST['cliente'],
                    'email' => $_POST['email'],
                    'domain' => $_POST['domain'],
                    'username' => $_POST['username'],
                    'password' => $_POST['password'],
                    'template_master' => $_POST['template']
            ];
            
            $client = Ispconfig::$Self->GetClientByUser($opt['username']);
            
            if(!empty($client)) throw new Exception('The user already exist. Please choice a different name');
            
            // add the customer
            Ispconfig::$Self->AddClient($opt)
                            ->AddWebsite( ['domain' => $opt['domain'], 'password' => $_POST['password']] );
            
            echo "<div class='ispconfig-msg ispconfig-msg-success'>" . sprintf(__('Your account %s has been created', 'wp-ispconfig3'), $opt['username']) ."</div>";
            
            // send confirmation mail
            if(!empty(WPISPConfig3::$OPTIONS['confirm'])) {
                $sent = $this->SendConfirmation( $opt );
                if($sent) echo "<div class='ispconfig-msg ispconfig-msg-success'>" . __('You will receive a confirmation email shortly', 'wp-ispconfig3') . "</div>";
            }
            
            echo "<div class='ispconfig-msg'>" . __('You can now login here', 'wp-ispconfig3') .": <a href=\"https://".$_SERVER['HTTP_HOST'].":8080/\">click</a></div>";
            
            Ispconfig::$Self->closeSoap();

        } catch (SoapFault $e) {
            //WPISPConfig3::soap->__getLastResponse();
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: '.$e->getMessage() .'</div>';
        } catch (Exception $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">Exception: '.$e->getMessage() . "</div>";
        }
    }
    
    /**
     * Display the formular and submit button
     * Usually called through shortcode
     */
    public function Display($opt = null){
        $defaultOptions = ['title' => 'WP-ISPConfig3', 'button' => 'Click to create Client', 'subtitle' => 'New Client (incl. Website and Domain)','showtitle' => true];
        
        if(is_array($opt))
            $opt = array_merge($defaultOptions, $opt);
        else 
            $opt = $defaultOptions;
            
        ?>
        <div class="wrap">
            <h2><?php if($opt['showtitle']) _e( $opt['title'], 'wp-ispconfig3' ); ?></h2>
            <?php 
                $this->onPost();
                $cfg = WPISPConfig3::$OPTIONS;
            ?>
            <form method="post" class="ispconfig" action="">
            <div id="poststuff" class="metabox-holder has-right-sidebar">
                <div id="post-body">
                    <div id="post-body-content">
                        <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                            <div class="postbox inside">
                                <h3><?php _e( $opt['subtitle'], 'wp-ispconfig3' );?></h3>
                                <div class="inside">
                                    <div>
                                    <?php 
                                        WPISPConfig3::getField('domain', 'Domain:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('cliente', 'Full Name:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('email', 'e-Mail:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('empresa', 'Company Name:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('username', 'Username:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('password', 'Password:', 'password', ['container' => 'div']);
                                        WPISPConfig3::getField('ip', 'Client IP:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('ns1', 'NameServer 1:', 'text', ['container' => 'div']);
                                        WPISPConfig3::getField('ns2', 'NameServer 2:', 'text', ['container' => 'div']);
                                    ?>
                                    <div>
                                    <label><?php echo __( 'Template:' ); ?></label>
                                    <select name="template">
                                        <option value="1">5GB Webspace</option>
                                        <option value="2">2GB Webspace</option>
                                        <option value="3">10GB Webspace</option>
                                        <option value="4">Free Webspace</option>
                                    </select>
                                    </div>
                                </div>
                                <p></p>
                                <p><input type="submit" class="button-primary" name="submit" value="<?php _e($opt['button']);?>" /></p>
                                <p></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </form>

            </div>
        <?php
    }
}
?>
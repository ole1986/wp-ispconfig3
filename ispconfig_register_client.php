<?php 
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

/**
 * Example to register a new customer by using the abstraction class IspconfigRegister
 */
class IspconfigRegisterClient extends IspconfigRegister {
    public static $Self;

    public static function init() {
        if(!self::$Self) self::$Self = new self();
    }

    public function __construct(){
        parent::__construct();
        // support for shortcode using "[ispconfig class=IspconfigRegisterClient ...]"
        $this->withShortcode();
        // enable SOAP requests for ISPconfig
        $this->withSoap();
    }
    
    /**
     * Called when user submits the data from register form - see IspconfigRegister::Display() for more details
     */
    protected function onPost(){
        if ( 'POST' !== $_SERVER[ 'REQUEST_METHOD' ] ) return;
        
        try{
            $this->session_id = $this->soap->login( WPISPConfig3::$OPTIONS['soapusername'], WPISPConfig3::$OPTIONS['soappassword']);
            
            $opt = ['company_name' => $_POST['empresa'], 
                    'contact_name' => $_POST['cliente'],
                    'email' => $_POST['email'],
                    'domain' => $_POST['domain'],
                    'username' => $_POST['username'],
                    'password' => $_POST['password'],
                    'template_master' => $_POST['template']
            ];
            
            $this->GetClientByUser($opt['username']);
            
            if(!empty($this->client)) throw new Exception('The user already exist. Please choice a different name');
            
            // add the customer
            $this->AddClient($opt);
            
            // add the first page for the customer
            $this->AddWebsite( ['domain' => $opt['domain'], 'password' => $_POST['password']] );
            
            // Logout from ISPconfig
            $this->soap->logout($this->session_id);
                        
            echo "<div class='ispconfig-msg ispconfig-msg-success'>Das Konto '".$opt['username']."' wurde erstellt!</div>";
            
            // send confirmation mail
            if(!empty(WPISPConfig3::$OPTIONS['confirm'])) {
                $sent = $this->SendConfirmation( $opt );
                if($sent) echo "<div class='ispconfig-msg ispconfig-msg-success'>An email confirmation has been sent</div>";
            }
            
            echo "<div class='ispconfig-msg'>The registration was successful - click <a href=\"https://".$_SERVER['HTTP_HOST'].":8080/\">here</a> to login</div>";
            
        } catch (SoapFault $e) {
            //echo $this->soap->__getLastResponse();
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: '.$e->getMessage() .'</div>';
        } catch (Exception $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">Exception: '.$e->getMessage() . "</div>";
        }
    }
    
    protected function getField($name, $title, $type = 'text'){
        return '<div><label>'. __( $title ) .'</label><input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.WPISPConfig3::$OPTIONS[$name].'" /></div>';
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
                                        echo $this->getField('domain', 'Domain:');
                                        echo $this->getField('cliente', 'Full Name:');
                                        echo $this->getField('email', 'e-Mail:');
                                        echo $this->getField('empresa', 'Company Name:');
                                        echo $this->getField('username', 'Username:');
                                        echo $this->getField('password', 'Password:');
                                        echo $this->getField('ip', 'Client IP:');
                                        echo $this->getField('ns1', 'NameServer 1:');
                                        echo $this->getField('ns2', 'NameServer 2:');
                                    ?>
                                    <div>
                                    <label><?php echo __( 'Template:' ); ?></label>
                                    <select name="template">
                                        <option value="1">5GB Webspace</option>
                                        <option value="2">2GB Webspace</option>
                                        <option value="3">10GB Webspace</option>
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
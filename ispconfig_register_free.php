<?php 
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

/**
 * Nordhosting TK - Register a free user through ISPConfig SOAP with including subdomain and shell account
 */
class IspconfigRegisterFree extends IspconfigRegister {
    public static $Self;

    public static function init($options){
        if(!self::$Self)
            self::$Self = new self($options);
    }
    
    public function __construct($options){
        $this->options = $options;
        // support for shortcode using "[ispconfig class=IspconfigRegisterFree ...]"
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
            if(empty($_POST['cliente'])) throw new Exception("Der Name darf nicht leer sein");
            if(substr_count($_POST['cliente'], ' ') < 1) throw new Exception("Bitte geben Sie Ihren vollständigen Namen an"); 
            
            
            if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) throw new Exception("Die eingegebene Email Adresse ist ungültig");
            
            if(empty($_POST['username'])) throw new Exception("Der Benutzername darf nicht leer sein");
            if(preg_match('/[^\x20-\x7f]/', $_POST['username'])) throw new Exception("Der Benutzername enthält ungültige Zeichen");
            if(strlen($_POST['username']) < 4) throw new Exception("Der Benutzername ist zu kurz");
            if(strlen($_POST['username']) > 15) throw new Exception("Der Benutzername ist zu lang");
            
            if(empty($_POST['password']) || $_POST['password'] !== $_POST['password_confirm'] ) throw new Exception("Bitte überprüfen Sie das Passwort");
            
            
            
            $this->session_id = $this->soap->login($this->options['soapusername'], $this->options['soappassword']);
            
            $opt = ['company_name' => $_POST['empresa'], 
                    'contact_name' => $_POST['cliente'],
                    'email' => $_POST['email'],
                    'username' => $_POST['username'],
                    'password' => $_POST['password'],
                    'template_master' => 4
            ];
            
            
            $this->GetClientByUser($opt['username']);
            
            if(!empty($this->client)) throw new Exception('The user already exist. Please choice a different name');
            
            // add the customer
            $this->AddClient($opt);
            
            // add the first page for the customer
            $domain = $_POST['username'] . '.nordhost.tk';
            $this->AddWebsite( ['domain' => $domain, 'password' => $_POST['password']] );
            
            // give the free user a shell
            $this->AddShell(['username' => $opt['username'] . '_shell', 'username_prefix' => $opt['username'] . '_', 'password' => $_POST['password'] ] );
            
            // Logout from ISPconfig
            $this->soap->logout($this->session_id);
                        
            echo "<div class='ispconfig-msg ispconfig-msg-success'>Das Konto '".$opt['username']."' wurde erstellt!</div>";
            
            // send confirmation mail
            if(!empty($this->options['confirm_mail'])) {
                
                $subject = 'Bestellbestaetigung - Nordhosting Webspace';
                $message = "Dies ist eine Bestätigung Ihrer Bestellung auf www.nordhosting.tk:\r\n\r\n";
                $message.= sprintf("Benutzer: %s\r\n", $opt['username']);
                $message.= sprintf("Password: %s\r\n", $opt['password']);
                $message.= "URL: http://www.nordhosting.tk:8080/";
                
                $sent = $this->SendConfirmation($opt, $subject, $message,'Nordhosting <no-reply@nordhosting.tk>');
                if($sent) echo "<div class='ispconfig-msg ispconfig-msg-success'>Eine Bestätigung wurde per E-Mail versendet</div>";
            }
            
            echo "<div class='ispconfig-msg'>Die Anmeldung erfolgt <a href=\"http://".$_SERVER['HTTP_HOST'].":8080/\">hier</a></div>";
            
        } catch (SoapFault $e) {
            //echo $this->soap->__getLastResponse();
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: '.$e->getMessage() .'</div>';
        } catch (Exception $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">'.$e->getMessage() . "</div>";
        }
    }
    
    protected function getField($name, $title, $type = 'text', $mandatory = false){
        if(empty($type)) $type = 'text';
        $req = ($mandatory)?'<span style="color: red;"> *</span>':'';
        return '<div><label>'. __( $title ) . $req .'</label><input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$this->options[$name].'" /></div>';
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
        <style>
            form.ispconfig label {
                display: inline-block;
                width: 160px;
                margin-bottom: 0.5em;
                margin-top: 0.5em;
                white-space: nowrap;
            }
            div.ispconfig-msg {
                border-style: solid;
                margin-bottom: 0.5em;
                margin-top: 0.5em;
                background-color: white;
                font-weight: bold;
                border-color: #F3F3F3;
                border-width: 2px 2px 2px 5px;
                box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
                padding: 5px 12px;
            }
            div.ispconfig-msg-success { border-left-color: #46b450 !important; }
            div.ispconfig-msg-error { border-left-color: #C3273A !important; }
        </style>
        <div class="wrap">
            <h2><?php if($opt['showtitle']) _e( $opt['title'], 'wp-ispconfig3' ); ?></h2>
            <?php 
                $this->onPost();
                $cfg = &$this->options;
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
                                        echo $this->getField('cliente', 'Vor- und Zuname:',null, true);
                                        echo $this->getField('email', 'e-Mail:', null, true);
                                        echo $this->getField('empresa', 'Firmenname:');
                                        echo $this->getField('username', 'Benutzer:', null, true);
                                        echo $this->getField('password', 'Passwort:', 'password', true);
                                        echo $this->getField('password_confirm', 'Passwort bestätigen:', 'password', true);
                                    ?>
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
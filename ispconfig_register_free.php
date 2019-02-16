<?php 
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

/**
 * Provide a html form to allow users register a new client account (free edition).
 */
class IspconfigRegisterFree extends Ispconfig {
    /**
     * Used to provide subdomain registration instead of domain registration in frontend using the below template ID
     */
    public static $FreeTemplateID = 4;
    
    public $forbiddenUserEx;
    
    /**
     * List of Client Limit Template from ISPConfig 
     */
    public $products = [];
    
    public function __construct()
    {
        // contains any of the below word is forbidden in username
        $this->forbiddenUserEx = 'www|mail|ftp|smtp|imap|download|upload|image|service|offline|online|admin|root|username|webmail|blog|help|support';
        // exact words forbidden in username
        $this->forbiddenUserEx .= '|^kb$|^wiki$|^api$|^static$|^dev$|^mysql$|^search$|^media$|^status$';
        // start with words forbidden in username
        $this->forbiddenUserEx .= '|^mobile';
    }
    
    /**
     * Called when user submits the data from register form - see IspconfigRegister::Display() for more details
     */
    protected function onPost()
    {
        if ( 'POST' !== $_SERVER[ 'REQUEST_METHOD' ] ) return;
        
        try{
            if(!$this->onCaptchaPost()) throw new Exception("Wrong or invalid captcha");
            
            $client = $this->validateName($_POST['client']);
            $username = $this->validateUsername($_POST['username']);
            $email = $this->validateMail($_POST['email'], $_POST['email_confirm']);
            
            // check the domain part
            if (intval($_POST['product_id']) == self::$FreeTemplateID ) {
                // add the first page for the customer
                $domain = $username . '.'. WPISPConfig3::$OPTIONS['default_domain'];
            } else {
                $domain = $this->validateDomain($_POST['domain']);
            }
            
            // check password
            $this->validatePassword($_POST['password'], $_POST['password_confirm']);
            
            $foundTemplate = $this->products[$_POST['product_id']];
            
            $opt = ['company_name' => $_POST['company'], 
                    'contact_name' => $client,
                    'domain' => $domain,
                    'street' => $_POST['street'],
                    'zip' => $_POST['zip'],
                    'city' => $_POST['city'],
                    'email' => $email,
                    'username' => $username,
                    'password' => $_POST['password']
            ];
            
            $client = $this->GetClientByUser($opt['username']);
            
            if (!empty($client)) {
                throw new Exception('The user you have entered already exists');
            }
            
            // add the customer
            $this->AddClient($opt)->AddWebsite(['domain' => $opt['domain'], 'password' => $_POST['password'],  'hd_quota' => $foundTemplate['limit_web_quota'], 'traffic_quota' => $foundTemplate['limit_traffic_quota'] ]);
            
            // give the free user a shell
            $this->AddShell(['username' => $opt['username'] . '_shell', 'username_prefix' => $opt['username'] . '_', 'password' => $_POST['password'] ]);
            
            echo "<div class='ispconfig-msg ispconfig-msg-success'>" . sprintf(__('Your account %s has been created', 'wp-ispconfig3'), $opt['username']) ."</div>";
            
            // send confirmation mail
            if (!empty(WPISPConfig3::$OPTIONS['confirm'])) {
                $sent = $this->SendConfirmation($opt);
                if ($sent) {
                    echo "<div class='ispconfig-msg ispconfig-msg-success'>" . __('You will receive a confirmation email shortly', 'wp-ispconfig3') . "</div>";
                }
            }

            echo "<div class='ispconfig-msg'>" . __('You can now login here', 'wp-ispconfig3') .": <a href=\"https://".$_SERVER['HTTP_HOST'].":8080/\">click</a></div>";
            
        } catch (SoapFault $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: '. $e->getMessage() .'</div>';
        } catch (Exception $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">'. $e->getMessage() . "</div>";
            $_POST['password'] = $_POST['password_confirm'] = '';
        }
    }
        
    /**
     * Display the formular and submit button
     * Usually called through shortcode
     *
     * @param array $opt options
     */
    public function Display($opt = null)
    {
        $defaultOptions = ['title' => 'WP-ISPConfig3', 'button' => 'Click to create Client', 'subtitle' => 'New Client (incl. Website and Domain)','showtitle' => true];
        
        // load the Client templates from ISPCONFIG
        try{
            $templates = $this->withSoap()->GetClientTemplates();
        } catch(SoapFault $e){
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: '. $e->getMessage() .'</div>';
            return;
        }
        
        foreach ($templates as $k => $v) {
            $this->products[$v['template_id']] = $v;
        }

        if (is_array($opt)) {
            $opt = array_merge($defaultOptions, $opt);
        } else {
            $opt = $defaultOptions;
        }

        ?>
        <div class="wrap">
            <h2><?php if($opt['showtitle']) _e($opt['title'], 'wp-ispconfig3'); ?></h2>
            <?php 
                $this->onPost();
            ?>
            <form method="post" class="ispconfig" action="<?php echo get_permalink(); ?>">
            <div id="poststuff" class="metabox-holder has-right-sidebar">
                <div id="post-body">
                    <div id="post-body-content">
                        <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                            <div class="postbox inside">
                                <h3><?php _e($opt['subtitle'], 'wp-ispconfig3'); ?></h3>
                                <div class="inside">
                                    <div style="margin-left: 0.3em;margin-bottom: 0.5em;font-weight: bold;">
                                        <label>Product: </label>
                                        <select name="product_id" data-ispconfig-selectproduct style="width: 340px;">
                                            <?php
                                            foreach ($this->products as $k => $v) {
                                                $s = (isset($_GET['product']) && $_GET['product'] == $k)?'selected':'';
                                                $free = ($k == self::$FreeTemplateID)?'data-isfree="1"':'';
                                                echo '<option value="'.$k.'" '.$s.' '.$free.'>'.$v['template_name'].'</option>';
                                            }   
                                            ?>
                                        </select>
                                    </div>
                                    <div id="domain" style="margin-left: 0.3em;margin-bottom: 0.5em;font-weight: bold;">
                                        <label>Domain: <span style="color: red;"> *</span></label><input type="text" data-ispconfig-checkdomain class="regular-text" style="width: 340px" name="domain" value="">
                                    </div>
                                    <div id="domainMessage" class="ispconfig-msg" style="display:none;"></div>
                                    <div id="subdomain" style="margin-left: 0.3em;margin-bottom: 0.5em;font-weight: bold;">
                                        <label>Subdomain:</label><span style="font-weight: normal;">http://</span><span id="domain_part">username</span><span style="font-weight: normal;">.<?php echo WPISPConfig3::$OPTIONS['default_domain']; ?></span>
                                    </div>
                                    <div style="margin-left: .2em">
                                    <?php
                                        WPISPConfig3::getField('client', 'Full name:',null, ['container' => 'div','required' => true]);
                                        WPISPConfig3::getField('company', 'Company:', null, ['container' => 'div']);
                                        WPISPConfig3::getField('street', 'Street', null, ['container' => 'div', 'required' => true]);
                                        echo "<div style='height:1px'>&nbsp;</div>";
                                        WPISPConfig3::getField('zipcode', 'Postal code', null, ['container' => 'div']);
                                        WPISPConfig3::getField('city', 'City', null, ['container' => 'div', 'required' => true]);
                                        WPISPConfig3::getField('email', 'e-Mail:', 'email', ['container' => 'div', 'required' => true]);
                                        WPISPConfig3::getField('email_confirm', 'e-Mail confirm:', 'email', ['container' => 'div',  'required' => true]);
                                        WPISPConfig3::getField('username', 'Username:', null, ['container' => 'div', 'required' => true, 'input_attr' => ['maxLength' => 20, 'data-ispconfig-subdomain' => '1']]);
                                        echo "<div style='height:1px'>&nbsp;</div>";
                                        WPISPConfig3::getField('password', 'Password:', 'password', ['container' => 'div', 'required' => true]);
                                        WPISPConfig3::getField('password_confirm', 'Password confirm:', 'password', ['container' => 'div', 'required' => true]);
                                    ?>
                                </div>
                                <div>&nbsp;</div>
                                <?php $this->Captcha() ?>
                                <p style="text-align: right"><input type="submit" class="button-primary" name="submit" value="<?php _e($opt['button']);?>" /></p>
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
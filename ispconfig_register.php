<?php 
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

// autoload php files starting with "ispconfig_register_[...].php" when class is used
spl_autoload_register(function($class) { 
    $cls = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], "$1_$2", $class));
    $f = $cls .'.php';
    // only include 'ispconfig_' files
    if(preg_match("/^ispconfig_/", $cls)) {
        //error_log('Loading file '. $f .' from class ' . $class);
        include $f;
    }
});

/**
 * Abstract class to provide soap requests and shortcodes
 * 
 * PLEASE NOTE: 
 * You can easily add features by creating a "ispconfig_register_<yourfeature>.php" file
 * shortcode can than be use the folowing in RTF Editor: [ispconfig class=IspconfigRegisterYourfeature]
 *
 * An Example can be found in the file: ispconfig_register_client.php
 */
abstract class IspconfigRegister {
    protected $soap;
    protected $options;
    
    protected $session_id;   
    protected $client_id;
    protected $domain_id;
    protected $shell_id;
    
    private $random_id = 0;
    
    abstract static function init($options);
    
    /**
     * Initialize the SoapClient for ISPConfig
     */
    public function withSoap(){
        $this->soap = new SoapClient(null, array('location' => $this->options['soap_location'], 'uri' => $this->options['soap_uri'], 'trace' => 1, 'exceptions' => 1));
    }
    
    /** 
     * Enable shortcode for the calling class
     */
    public function withShortcode(){
        add_shortcode( 'ispconfig', array($this,'shortcode') );
        $this->withAjax();
    }
    
    public function withAjax(){
        // used to request whois through ajax
        add_action('wp_ajax_ispconfig_whois', array($this, 'AJAX_ispconfig_whois_callback'));   
    }
    
    public function registerAjax(){
        echo "<script>";
        // ajax used for whois request - AJAX_WhoisCallback is called
        echo "var ispconfig_whois = function(domain, callback){ jQuery.post('". admin_url()  ."admin-ajax.php', {'action': 'ispconfig_whois', 'domain': domain}, callback); };";
        echo "</script>";
    }
        
    public function AJAX_ispconfig_whois_callback(){
        $dom = strtolower($_POST['domain']);

        $result = shell_exec("whois $dom");
        
        if(preg_match("/^(No whois server is known|This TLD has no whois server)/m", $result))
            echo -1;
        else if(preg_match("/^(Status: AVAILABLE|Status: free|NOT FOUND|".$dom." no match|No match for \"(.*?)\"\.)$/im", $result))
            echo 1;
        else
            echo 0;
        wp_die();
    }
    
    /**
     * Provide shortcode execution by calling the class constructor defined "class=..." attribute
     */
    public function shortcode($attr, $content = null){
        // init
        if(empty($attr)) return 'No parameters defined in shortcode';
        if(empty($attr['class'])) return 'No CLASS parameter defined in shortcode'; 
        
        $cls = $attr['class'];
        $cls::init($this->options);
        
        $defaultAttr = ['showtitle' => false, 'title' => 'New Client', 'subtitle' => 'Register a new client'];
        $attr = array_merge($defaultAttr, $attr);
        
        ob_start();
        $cls::$Self->Display($attr);
        return ob_get_clean();
    }
    
    /**
     * SOAP: Get Client by username
     */
    public function GetClientByUser($username){
        $this->client = $this->soap->client_get_by_username($this->session_id, $username);
    }
    
    /**
     * SOAP: Get a list of arrays containing all Client/Reseller limit templates
     */
    public function GetClientTemplates() {
        return $this->soap->client_templates_get_all($this->session_id);
    }
    
    /**
     * SOAP: Add a new client into ISPConfig
     */
    public function AddClient($options = []){
        $defaultOptions = array(
            'company_name' => '',
            'contact_name' => '',
            'customer_no' => '',
            'vat_id' => '1',
            'street' => '',
            'zip' => '',
            'city' => '',
            'state' => '',
            'country' => 'DE',
            'telephone' => '',
            'mobile' => '',
            'fax' => '',
            'email' => '',
            'internet' => '',
            'icq' => '',
            'notes' => '',
            'default_mailserver' => 1,
            'limit_maildomain' => -1,
            'limit_mailbox' => -1,
            'limit_mailalias' => -1,
            'limit_mailaliasdomain' => -1,
            'limit_mailforward' => -1,
            'limit_mailcatchall' => -1,
            'limit_mailrouting' => 0,
            'limit_mailfilter' => -1,
            'limit_fetchmail' => -1,
            'limit_mailquota' => -1,
            'limit_spamfilter_wblist' => 0,
            'limit_spamfilter_user' => 0,
            'limit_spamfilter_policy' => 1,
            'default_webserver' => 1,
            'limit_web_ip' => '',
            'limit_web_domain' => -1,
            'limit_web_quota' => -1,
            'web_php_options' => 'no,fast-cgi,cgi,mod,suphp',
            'limit_web_subdomain' => -1,
            'limit_web_aliasdomain' => -1,
            'limit_ftp_user' => -1,
            'limit_shell_user' => 0,
            'ssh_chroot' => 'no,jailkit,ssh-chroot',
            'limit_webdav_user' => 0,
            'default_dnsserver' => 1,
            'limit_dns_zone' => -1,
            'limit_dns_slave_zone' => -1,
            'limit_dns_record' => -1,
            'default_dbserver' => 1,
            'limit_database' => -1,
            'limit_cron' => 0,
            'limit_cron_type' => 'url',
            'limit_cron_frequency' => 5,
            'limit_traffic_quota' => -1,
            'username' => '',
            'password' => '',
            'language' => 'de',
            'usertheme' => 'default',
            'template_master' => 0,
            'template_additional' => '',
            'created_at' => 0
        );
      
        $options = array_merge($defaultOptions, $options);
        
        // check for required fields
        if(!array_key_exists('username', $options)) throw new Exception("Error missing or invalid username");
        if(!array_key_exists('password', $options)) throw new Exception("Error missing or invalid password");
        if(!array_key_exists('email', $options)) throw new Exception("Error missing email");
        if(!filter_var($options['email'], FILTER_VALIDATE_EMAIL)) throw new Exception("Error invalid email");
        
        // SOAP REQUEST TO INSERT INTO ISPCONFIG
        $this->client_id = $this->soap->client_add($this->session_id, $this->random_id, $options);
    }
    
    /**
     * SOAP: Add a new Website into ISPConfig
     */
    public function AddWebsite($options){
        $defaultOptions = array(
            'server_id'	=> '1',
            'domain' => $domain,
            'ip_address' => '*',
            'type' => 'vhost',
            'parent_domain_id' => 0,
            'vhost_type' => '',
            'hd_quota' => -1,
            'traffic_quota' => -1,
            'cgi' => 'n',
            'ssi' => 'n',
            'suexec' => 'n',
            'errordocs' => 1,
            'is_subdomainwww' => 1,
            'subdomain' => 'www',
            'php' => 'php-fpm', 
            'php_fpm_use_socket' => 'y',
            'ruby' => 'n', 
            'redirect_type' => '',
            'redirect_path' => '',
            'ssl' => 'n',
            'ssl_state' => '',
            'ssl_locality' => '',
            'ssl_organisation' => '',
            'ssl_organisation_unit' => '',
            'ssl_country' => '',
            'ssl_domain' => '',
            'ssl_request' => '',
            'ssl_cert' => '',
            'ssl_bundle' => '',
            'ssl_action' => '',
            'stats_password' => $password,
            'stats_type' => 'webalizer',
            'allow_override' => 'All',
            'apache_directives' => '',
            'php_open_basedir' => '/', 
            'custom_php_ini' => '', 
            'backup_interval' => '',
            'backup_copies' => 1,
            'active' => 'y',
            'traffic_quota_lock' => 'n',
            'pm' => 'dynamic',
            'pm_process_idle_timeout'=>10,
            'pm_max_requests'=>0
        );
        
        $options = array_merge($defaultOptions, $options);
        
        $this->domain_id = $this->soap->sites_web_domain_add($this->session_id, $this->client_id, $options, $readonly = false);
        return $this->domain_id;
    }
    
    /**
     * SOAP: Add a new shell user into ISPConfig
     */
    public function AddShell($options){
        $defaultOptions = array(
			'server_id' => 1,
			'parent_domain_id' => $this->domain_id,
			'username' => '',
			'password' => '',
			'quota_size' => -1,
			'active' => 'y',
			'puser' => 'web' . $this->domain_id,
			'pgroup' => 'client' . $this->client_id,
			'shell' => '/bin/bash',
			'dir' => '/home/clients/client' . $this->client_id . '/web' . $this->domain_id,
			'chroot' => 'jailkit'
        );
        
        $options = array_merge($defaultOptions, $options);
        
        $this->shell_id = $this->soap->sites_shell_user_add($this->session_id, $this->client_id, $options);
        return $this->shell_id;
    }
    
    /** 
     * Provide an option to send a confirmation email
     */
    public function SendConfirmation($options, $subject = 'Order confirmation - yourhost', $message = 'The order has been confirmed', $sender = 'NO REPLY <no-reply@yourhost>'){
        if(!$this->client_id) return;
        if(!filter_var($options['email'], FILTER_VALIDATE_EMAIL)) return;
        
		$header = 'From: '. $sender;

		return mail($options['email'], $subject, $message, $header);
    }
        
    public function Captcha($title = 'Catpcha'){
        if(!isset($_COOKIE['captcha_uid']))
            $uid = uniqid();
        else
            $uid = $_COOKIE['captcha_uid'];
        
        $m = [];
        for($i = 0; $i <= 5; $i++) {
            $op = (rand(0,1))?true:false;
            if($op)
                $m[$i] = ['a' => rand(1, 5), 'b' => rand(1, 15), 'op' => $op];
            else
                $m[$i] = ['a' => rand(1, 15), 'b' => rand(1, 5), 'op' => $op];
        }
        
        $choice = rand(0,5);
        
        if($m[$choice]['op'])
            $result = $m[$choice]['a'] + $m[$choice]['b'];
        else
            $result = $m[$choice]['a'] - $m[$choice]['b'];
        
        set_transient( 'wp_ispconfig_register_'.$uid.'_captcha', ['result' => $result, 'key' => $choice], 360);
        
        foreach($m as $k => $v) {
            if($v['op'])
                $str = $v['a'] . ' + ' . $v['b'] . ' = ?';
            else
                $str = $v['a'] . ' - ' . $v['b'] . ' = ?';
            
            echo "<div id=\"captcha_problem_{$k}\" style=\"display:none\"><label>{$title} {$str}</label><input type=\"text\" name=\"captcha[{$k}]\" maxlength=\"2\"></div>";
        }
        echo "<input type=\"hidden\" name=\"captcha_uid\" value=\"{$uid}\" />";

        echo "<script>jQuery(function(){ var t = new Date(); t.setSeconds(t.getSeconds() + 360); document.cookie=\"captcha_uid={$uid};expires=\"+t.toUTCString();   jQuery('#captcha_problem_{$choice}').show(); })</script>";
    }
    
    public function onCaptchaPost(){
        if(!isset($_POST['captcha_uid'])) return false;
        
        $cachedData = get_transient( 'wp_ispconfig_register_'.$_POST['captcha_uid'].'_captcha' );
        if($cachedData === false) return false;
        
        delete_transient( 'wp_ispconfig_register_captcha' );
        
        $result = $cachedData['result'];
        $key = $cachedData['key'];
                
        if(!isset($_POST['captcha'])) return false;
        if(!isset($_POST['captcha'][$key])) return false;
        
        if($result === false) return false;
        
        if($result != $_POST['captcha'][$key])
            return false;
            
        return true;
    }
    
    protected function validateName($input){
        if(empty($input)) throw new Exception( __("The name cannot be empty", 'wp-ispconfig3') );
        if(substr_count($input, ' ') < 1) throw new Exception( __("Please enter your name in full-style", 'wp-ispconfig3') ); 
            
        return $input;
    }
    
    protected function validateUsername($u){
        if(empty($u)) throw new Exception(__("The username cannot be empty", 'wp-ispconfig3'));
        if(preg_match('/[^A-z0-9]/', $u)) throw new Exception( __("The username contains invalid characters", 'wp-ispconfig3'));
        if(strlen($u) < 4) throw new Exception( __("The username is too short",'wp-ispconfig3'));
        if(strlen($u) > 20) throw new Exception( __("The username is too long",'wp-ispconfig3') );
        
        if(!empty($this->forbiddenUserEx)) if(preg_match('/'.$this->forbiddenUserEx .'/i', $u)) throw new Exception( __("The username is not allowed", 'wp-ispconfig3'));
        
        return strtolower($u);
    }
    
    protected function validatePassword($input, $input_confirm){
        if(strlen($input) < 10) throw new Exception(__("The password is too short", 'wp-ispconfig3'));
        if(strlen($input) > 30) throw new Exception(__("The password is too long", 'wp-ispconfig3'));
        
        if(preg_match('/[^\x20-\x7f]/', $input)) throw new Exception(__('The password contains invalid characters', 'wp-ispconfig3'));
        
        if($input !== $input_confirm) throw new Exception( __("The password does not match", 'wp-ispconfig3'));
    }
    
    protected function validateDomain($input){
        if (!preg_match("/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,4}$/", $domain_name))
            throw new Exception(__("The domain name is invalid", 'wp-ispconfig3'));
        return strtolower($input);
    }
    
    protected function validateMail($input, $input_confirm){
        if(!filter_var($input, FILTER_VALIDATE_EMAIL)) throw new Exception( __("The email address is invalid", 'wp-ispconfig3'));
        if($input !== $input_confirm) throw new Exception( __("The email address does not match", 'wp-ispconfig3'));
        
        return strtolower($input);
    }
}
?>
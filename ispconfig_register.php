<?php 
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

// autoload php files starting with "ispconfig_register_[...].php" when class is used
spl_autoload_register(function($class) { 
    $cls = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], "$1_$2", $class));
    $f = $cls .'.php';
    error_log('Loading file '. $f .' from class ' . $class);
    include $f;
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
    
    public function withSoap(){
        $this->soap = new SoapClient(null, array('location' => $this->options['soap_location'], 'uri' => $this->options['soap_uri'], 'trace' => 1, 'exceptions' => 1));
    }
    
    public function withShortcode(){
        add_shortcode( 'ispconfig', array($this,'shortcode') );
    }
    
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
    
    public function GetClientByUser($username){
        $this->client = $this->soap->client_get_by_username($this->session_id, $username);
    }
    
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
    
    public function SendConfirmation($options, $subject = 'Order confirmation - yourhost', $message = 'The order has been confirmed', $sender = 'NO REPLY <no-reply@yourhost>'){
        if(!$this->client_id) return;
        if(!filter_var($options['email'], FILTER_VALIDATE_EMAIL)) return;
        
		$header = 'From: '. $sender;

		return mail($options['email'], $subject, $message, $header);
    }
}
?>
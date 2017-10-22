<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

if ( ! defined( 'WPISPCONFIG3_PLUGIN_WC_DIR' ) )
    define( 'WPISPCONFIG3_PLUGIN_WC_DIR', plugin_dir_path( __FILE__ ) );

class IspconfigWc {
    public static $Self;

    public static $WEBTEMPLATE_PARAMS = [
        /* 5GB Webspace */
        1 => ['pm_max_children' => 2],
        /* 10GB Webspace */
        2 => ['pm_max_children' => 3, 'pm_max_spare_servers' => 3],
        /* 30GB Webspace */
        3 => ['pm_max_children' => 5, 'pm_min_spare_servers'=> 2, 'pm_max_spare_servers' => 5],
    ];
        
    public static function init(){
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        if(!is_plugin_active('woocommerce/woocommerce.php') ) return;

        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_wc_product.php';
        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_wc_product_hour.php';
        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_wc_product_webspace.php';

        if(!self::$Self)
            self::$Self = new self();
    }
    
    public function __construct(){      
        // contains any of the below word is forbidden in username
        $this->forbiddenUserEx = 'www|mail|ftp|smtp|imap|download|upload|image|service|offline|online|admin|root|username|webmail|blog|help|support';
        // exact words forbidden in username
        $this->forbiddenUserEx .= '|^kb$|^wiki$|^api$|^static$|^dev$|^mysql$|^search$|^media$|^status$';
        // start with words forbidden in username
        $this->forbiddenUserEx .= '|^mobile';


        if(!is_admin()) {
            // CHECKOUT: Add an additional field allowing the customer to enter a domain name
            add_filter('woocommerce_checkout_fields' , array($this, 'wc_checkout_nocomments') );
            add_action('woocommerce_checkout_before_customer_details', array($this, 'wc_checkout_field') );
            add_action('woocommerce_checkout_process',  array($this, 'wc_checkout_process') );
            add_action('woocommerce_checkout_order_processed', array($this, 'wc_order_processed') );
        }
        
        // ORDER-PAID: When Order has been paid (can also happen manually as ADMIN)
        add_filter('woocommerce_payment_complete', array( $this, 'wc_payment_complete' ) );
        // INVOICE-PAID: When the invoice has been paid through "My account"
        add_action( 'valid-paypal-standard-ipn-request', array( $this, 'wc_payment_paypal_ipn' ), 10, 1 );
        
        // display the domain inside WC-InvoicePdf metabox
        add_action('wcinvoicepdf_invoice_metabox', [$this, 'wc_invoice_metabox']);
    }

    public function wc_invoice_metabox($post_id){
        $domain = get_post_meta($post_id, 'Domain', true);       
        ?>
        <p>
            <label class="post-attributes-label">Domain: </label>
            <?php echo $domain ?>
        </p>
        <?php
    }

    public function wc_payment_paypal_ipn($posted){
        if ( ! empty( $posted['custom'] ) &&  ($custom=json_decode($posted['custom'])) && is_object($custom) && isset($custom->invoice_id) ) {
            error_log("### WC_Gateway_Paypal called for IspconfigInvoice");
            $invoice = new IspconfigInvoice( intval($custom->invoice_id) );
            if(!empty($invoice->ID))
            {
                $invoice->Paid();
                $invoice->Save();
                error_log("### IspconfigInvoice({$invoice->ID}) saved");
            }
            exit;
        }
    }

    /**
     * CHECKOUT: remove the comment box
     */
    public function wc_checkout_nocomments($fields){
        unset($fields['order']['order_comments']);
        return $fields;
    }
    
    
    /**
     * CHECKOUT: Add an additional field for the domain name being entered by customer (incl. validation check)
     */
    public function wc_checkout_field() {
        if(WC()->cart->is_empty()) return 0;
        $items =  WC()->cart->get_cart();

        $checkout = WC()->checkout();

        foreach($items as $p) {
            if(is_subclass_of($p['data'], 'WC_ISPConfigProduct'))
                $p['data']->OnCheckout($checkout);
        }
    }
    
    /**
     * CHECKOUT: Save the domain field entered by the customer
     */
    public function wc_order_processed( $order_id ) {
        if(WC()->cart->is_empty()) return 0;
        $items =  WC()->cart->get_cart();
        
        foreach($items as $item_key => $item) {
            if(is_subclass_of($item['data'], 'WC_ISPConfigProduct'))
                $item['data']->OnCheckoutSubmit($order_id, $item_key,$item);
        }
    }
    
    /**
     * CHECKOUT: Validate the domain entered by the customer
     */
    public function wc_checkout_process(){
        if(WC()->cart->is_empty()) return 0;
        $items =  WC()->cart->get_cart();

        foreach($items as $p) {
            if(is_subclass_of($p['data'], 'WC_ISPConfigProduct'))
                $p['data']->OnCheckoutValidate();
        }       
    }

    public function wc_payment_complete($order_id) {
        error_log("### ORDER PAYMENT COMPLETED - REGISTERING TO ISPCONFIG ###");
        
        $order = new WC_Order($order_id);

        $invoice = new IspconfigInvoice($order);
        $invoice->makeNew();
        $invoice->Save();
        
        $this->registerFromOrder( $order );
    }
    
    /**
     * ORDER: When order has changed to status to "processing" assume its payed and REGISTER the user in ISPCONFIG (through SOAP)
     */
    private function registerFromOrder($order){        
        $items = $order->get_items();
        $product = $order->get_product_from_item( array_pop($items) );
        $templateID = $product->getISPConfigTemplateID();
        
        if(empty($templateID)) {
            $order->add_order_note('<span style="font-weight:bold;">ISPCONFIG NOTICE: No ISPConfig template found - registration skipped</span>');
            return;
        }
        
        if($order->get_customer_id() == 0) {
            $order->add_order_note('<span style="color: red">ISPCONFIG ERROR: Guest account is not supported. User action required!</span>');
            return;
        }
        
        if($order->get_item_count() <= 0)
        {
            $order->add_order_note('<span style="color: red">ISPCONFIG ERROR: No product found</span>');
            wp_update_post( array( 'ID' => $order->id, 'post_status' => 'wc-cancelled' ) );
            return;
        }
        
        try{
            $userObj = get_user_by('ID', $order->get_customer_id());
            $password = substr(str_shuffle('!@#$%*&abcdefghijklmnpqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ23456789'), 0, 12);
            
            $username = $userObj->get('user_login'); 
            $email =  $userObj->get('user_email');
            
            $domain = get_post_meta($order->id, 'Domain', true);
            $client = $order->get_formatted_billing_full_name();
            
            // overwrite the domain part for free users to only have subdomains
            if($templateID == 4) {
                if(empty(WPISPConfig3::$OPTIONS['default_domain']))
                    throw new Exception("Failed to create free account on template ID: $templateID"); 
                $domain = "free{$order->id}." . WPISPConfig3::$OPTIONS['default_domain'];
                $username = "free{$order->id}";
            }

            // fetch all templates from ISPConfig
            $limitTemplates = Ispconfig::$Self->withSoap()->GetClientTemplates();
            // filter for only the TemplateID defined in self::$TemplateID
            $limitTemplates = array_filter($limitTemplates, function($v, $k) use($templateID) { return ($templateID == $v['template_id']); }, ARRAY_FILTER_USE_BOTH);
            
            if(empty($limitTemplates)) throw new Exception("No client template found with ID '{$this->TemplateID}'");
            $foundTemplate = array_pop($limitTemplates);
            
            $opt = ['company_name' => '', 
                    'contact_name' => $client,
                    'street' => '',
                    'zip' => '',
                    'city' => '',
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'usertheme' => 'DarkOrange',
                    'template_master' => $templateID
            ];

            $webOpt = [ 'domain' => $domain, 'password' => $password, 
                        'hd_quota' => $foundTemplate['limit_web_quota'], 
                        'traffic_quota' => $foundTemplate['limit_traffic_quota'] ];
            
            if(isset(self::$WEBTEMPLATE_PARAMS[$templateID])) {
                foreach (self::$WEBTEMPLATE_PARAMS as $k => $v) {
                    $webOpt[$k] = $v;
                }
            }
            
            $client = Ispconfig::$Self->GetClientByUser($opt['username']);
            
            // TODO: skip this error when additional packages are being bought (like extra webspace or more email adresses, ...)
            if(!empty($client)) throw new Exception("The user " . $opt['username'] . ' already exists in ISPConfig');
            
            // ISPCONFIG SOAP: add the customer and website for the same client id
            Ispconfig::$Self->AddClient($opt)->AddWebsite($webOpt);

            // ISPCONFIG SOAP: give the user a shell (only for non-free products)
            if($templateID != 4)
                Ispconfig::$Self->AddShell(['username' => $opt['username'] . '_shell', 'username_prefix' => $opt['username'] . '_', 'password' => $password ] );
            
            // send confirmation mail
            if(!empty(WPISPConfig3::$OPTIONS['confirm'])) {
                $opt['domain'] = $domain;
                $this->SendConfirmation($opt);
            }
            
            $order->add_order_note('<span style="color: green">ISPCONFIG: User '.$username.' added to ISPCONFIG. Limit Template: '. $foundTemplate['template_name'] .'</span>');

            wp_update_post( array( 'ID' => $order->id, 'post_status' => 'wc-on-hold' ) );
            
            Ispconfig::$Self->closeSoap();

            return;
        } catch (SoapFault $e) {
            $order->add_order_note('<span style="color: red">ISPCONFIG SOAP ERROR (payment): ' . $e->getMessage() . '</span>');
        } catch(Exception $e){
            $order->add_order_note('<span style="color: red">ISPCONFIG ERROR (payment): ' . $e->getMessage() . '</span>' );
        }
        wp_update_post( array( 'ID' => $order->id, 'post_status' => 'wc-cancelled' ) );
    }
}
?>
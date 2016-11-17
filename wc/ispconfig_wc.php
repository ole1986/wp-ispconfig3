<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit; 

if ( ! defined( 'WPISPCONFIG3_PLUGIN_WC_DIR' ) )
    define( 'WPISPCONFIG3_PLUGIN_WC_DIR', plugin_dir_path( __FILE__ ) );

include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_wc_backend.php';

class IspconfigWc extends IspconfigWcBackend {
    public static $Self;

    public static $WEBTEMPLATE_PARAMS = [
        /* 5GB Webspace */
        1 => ['pm_max_children' => 2],
        /* 10GB Webspace */
        2 => ['pm_max_children' => 3, 'pm_max_spare_servers' => 3],
        /* 30GB Webspace */
        3 => ['pm_max_children' => 5, 'pm_min_spare_servers'=> 2, 'pm_max_spare_servers' => 5],
    ];
    
    protected $default_options = [
        'wc_payment_reminder' => 1,
        'wc_payment_message' => "Dear Administrator,\n\nThe following invoices are not being paid yet: %s\n\nPlease remind the customer(s) for payment",
        'wc_recur_reminder' => 0,
        'wc_recur_message' => "Dear Customer,\n\nattached you can find your invoice %s\n\nKind Regards,\n Your hosting Team",
        'wc_recur_test' => 0,
        'wc_mail_sender' => 'Invoice <invoice@domain.tld>',
        'wc_mail_reminder' => 'yourmail@domain.tld',
        'wc_pdf_title' => 'YourCompany - %s',
        'wc_pdf_logo' => '/plugins/wp-ispconfig3/logo.png',
        'wc_pdf_addressline' => 'Your address in a single line',
        'wc_pdf_condition' => "Some conditional things related to invoices\nLine breaks supported",
        'wc_pdf_info' => 'Info block containing created date here: %s'
    ];
    
    public static function init(){
        if(!self::IsWooCommerceAvailable()) return;

        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_invoice.php';
        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_invoice_pdf.php';
        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_invoice_list.php';
        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_wc_product_hour.php';
        include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_wc_product_webspace.php';

        add_rewrite_endpoint( 'invoices', EP_ROOT | EP_PAGES );

        if(!self::$Self)
            self::$Self = new self();
    }
    
    public function __construct(){
        parent::__construct();

        WPISPConfig3::$OPTIONS = array_merge(WPISPConfig3::$OPTIONS, $this->default_options);

        // enable soap for this
        $this->withSoap();
        $this->withAjax();
        
        // contains any of the below word is forbidden in username
        $this->forbiddenUserEx = 'www|mail|ftp|smtp|imap|download|upload|image|service|offline|online|admin|root|username|webmail|blog|help|support';
        // exact words forbidden in username
        $this->forbiddenUserEx .= '|^kb$|^wiki$|^api$|^static$|^dev$|^mysql$|^search$|^media$|^status$';
        // start with words forbidden in username
        $this->forbiddenUserEx .= '|^mobile';


        if(!is_admin()) {
            // CHECKOUT: Add an additional field allowing the customer to enter a domain name
            add_filter('woocommerce_checkout_fields' , array($this, 'wc_checkout_nocomments') );
            add_action('woocommerce_after_order_notes', array($this, 'wc_checkout_field') );
            add_action('woocommerce_checkout_process',  array($this, 'wc_checkout_process') );
            add_action('woocommerce_checkout_update_order_meta', array($this, 'wc_checkout_field_update_order_meta') );

            // display invoice menu entry in "MY Account" (customer)
            
            add_filter('woocommerce_account_menu_items', array($this, 'wc_invoice_menu'));
            add_action('woocommerce_account_invoices_endpoint', array($this, 'wc_invoice_content'));
        }
        
        // ORDER-PAYED: When Order has been paid (can also happen manually as ADMIN)
        add_filter('woocommerce_payment_complete', array( $this, 'wc_payment_complete' ) );
    }
        
    /**
     * CART: Verify the product selected Limit Template 
     */
    private function getTemplateIDFromCart(){
        if(WC()->cart->is_empty()) return 0;
        $items =  WC()->cart->get_cart();
        $item = array_pop($items);
        
        return $item['data']->getISPConfigTemplateID();
    }

    public function wc_invoice_menu($items){
        $result = array_slice($items, 0, 2);
        $result['invoices'] = __('Invoices', 'wp-ispconfig3');
        
        $result = array_merge($result, array_slice($items, 2) );

        return $result;
    }

    public function wc_invoice_content(){
        global $wpdb, $current_user;

        if(isset($_GET['invoice'])) {
            $this->showPaymentForInvoice(intval($_GET['invoice']));
            return;
        }
            


        $query = "SELECT i.*, u.user_login AS customer_name, u.user_email AS user_email, u.ID AS user_id, p.ID AS order_id, p.post_status, pm.meta_value AS ispconfig_period 
                    FROM {$wpdb->prefix}ispconfig_invoice AS i 
                    LEFT JOIN wp_users AS u ON u.ID = i.customer_id
                    LEFT JOIN wp_posts AS p ON p.ID = i.wc_order_id
                    LEFT JOIN wp_postmeta AS pm ON (p.ID = pm.post_id AND pm.meta_key = 'ispconfig_period')
                    WHERE i.customer_id = {$current_user->ID}";

        $result = $wpdb->get_results($query, ARRAY_A);
        ?>
        <table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_invoices account-orders-table">
            <thead>
                <tr>
                    <th><?php _e('Invoice', 'wp-ispconfig3') ?></th>
                    <th><?php _e('Order', 'woocommerce') ?></th>
                    <th><?php _e('Created at', 'woocommerce') ?></th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($result as $k => $v) { ?>
                <tr>
                    <td><a href="<?php echo get_site_url() . '/wp-admin/admin.php?invoice=' . $v['ID'] ?>"><?php echo $v['invoice_number'] ?></a></td>
                    <td><?php echo $v['order_id'] ?></td>
                    <td><?php echo strftime("%Y-%m-%d", strtotime($v['created'])) ?></td>
                    <td>
                        <?php if(($v['status'] & 2) == 0): ?>
                        <a href="<?php echo '?invoice='.$v['ID']; ?>" class="button view"><?php _e('Pay Now', 'wp-ispconfig3') ?></a>
                        <?php elseif(!empty($v['paid_date'])): ?>
                        <strong><?php echo __('Paid at', 'wp-ispconfig3') . ' ' . strftime("%Y-%m-%d",strtotime($v['paid_date'])) ?> </strong>
                        <?php else: ?>
                        <strong><?php _e('Paid', 'wp-ispconfig3') ?> </strong>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
    }

    public function showPaymentForInvoice($invoiceID){
        $invoice = new IspconfigInvoice($invoiceID);
        $order = $invoice->order;
        ?>

        <?php if($order->payment_method == 'bacs'): $bacs = new WC_Gateway_BACS(); ?>      
        <h3><?php  _e('Invoice', 'wp-ispconfig3'); ?> <?php echo $invoice->invoice_number ?></h3>
        Zahlung via <?php echo $order->payment_method_title ?>

        <p><?php _e('Order', 'woocommerce') ?># <?php echo $invoice->invoice_number ?></p> 
        <?php $bacs->thankyou_page($order->id); ?>
        <h3>Betrag: <?php echo $order->get_total() .' ' . $order->get_order_currency(); ?></h3>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="<?php echo get_site_url() . '/wp-admin/admin.php?invoice=' . $invoiceID; ?>" class="button view"><?php  _e('Show');  ?></a>
        </div>
        <?php
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
    public function wc_checkout_field( $checkout ) {
        $templateID = $this->getTemplateIDFromCart();
        
        if($templateID >= 1 && $templateID <= 3) {
            $this->registerAjax();
    
            woocommerce_form_field( 'order_domain', [
            'type'              => 'text',
            'label'             => 'Ihre Wunschdomain',
            'placeholder'       => '',
            'custom_attributes' => ['data-ispconfig-checkdomain'=>'1']
            ], $checkout->get_value( 'order_domain' ));
        }
        
        echo '<div id="domainMessage" class="ispconfig-msg" style="display:none;"></div>';
        echo '<div><sup>Bitte beachten Sie das die Domainregistrierung innerhalb von 24 Stunden nach Zahlungseingang erfolgt</sup></div>';
    }
    
    /**
     * CHECKOUT: Save the domain field entered by the customer
     */
    public function wc_checkout_field_update_order_meta( $order_id ) {
        if ( ! empty( $_POST['order_domain'] ) ) {
            update_post_meta( $order_id, 'Domain', sanitize_text_field( $_POST['order_domain'] ) );
        }
    }
    
    /**
     * CHECKOUT: Validate the domain entered by the customer
     */
    public function wc_checkout_process(){
        $templateID = $this->getTemplateIDFromCart();
        
        // all products require a DOMAIN to be entered
        if($templateID >= 1 && $templateID <= 3) {
            try{
                $dom = $this->validateDomain( $_POST['order_domain'] );
                $available = $this->isDomainAvailable($dom);
                if($available == 0) {
                    wc_add_notice( __("The domain is not available", 'wp-ispconfig3'), 'error');
                } else if($available == -1) {
                    wc_add_notice( __("The domain might not be available", 'wp-ispconfig3'), 'notice');
                }
            } catch(Exception $e){
                wc_add_notice( $e->getMessage(), 'error');
            }
        }
    }

    public function wc_payment_complete($order_id) {
        error_log("### ORDER PAYMENT COMPLETED - REGISTERING TO ISPCONFIG ###");
        
        $order = new WC_Order($order_id);

        $invoice = new IspconfigInvoice($order);
        $invoice->document = IspconfigInvoicePdf::init()->BuildInvoice($invoice);
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
        
        if($order->customer_user == 0) {
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
            $userObj = get_user_by('ID', $order->customer_user);
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
                
            // connect to ISPCONFIG SOAP
            $this->session_id = $this->soap->login(WPISPConfig3::$OPTIONS['soapusername'], WPISPConfig3::$OPTIONS['soappassword']);
            
            // fetch all templates from ISPConfig 
            $limitTemplates = $this->GetClientTemplates();
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
            
            $this->GetClientByUser($opt['username']);
            
            // TODO: skip this error when additional packages are being bought (like extra webspace or more email adresses, ...)
            if(!empty($this->client)) throw new Exception("The user " . $opt['username'] . ' already exists in ISPConfig');
            
            // ISPCONFIG SOAP: add the customer
            $this->AddClient($opt);
            // ISPCONFIG SOAP: add the webiste
            $this->AddWebsite( $webOpt );
            // ISPCONFIG SOAP: give the user a shell (only for non-free products)
            if($templateID != 4)
                $this->AddShell(['username' => $opt['username'] . '_shell', 'username_prefix' => $opt['username'] . '_', 'password' => $password ] );
            // ISPCONFIG SOAP: Logout from ISPconfig
            $this->soap->logout($this->session_id);
            
            // send confirmation mail
            if(!empty(WPISPConfig3::$OPTIONS['confirm'])) {
                $opt['domain'] = $domain;
                $this->SendConfirmation($opt);
            }
                
            
            $order->add_order_note('<span style="color: green">ISPCONFIG: User '.$username.' added to ISPCONFIG. Limit Template: '. $foundTemplate['template_name'] .'</span>');

            wp_update_post( array( 'ID' => $order->id, 'post_status' => 'wc-on-hold' ) );
            
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
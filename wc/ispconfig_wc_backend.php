<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

global $wpdb;

class IspconfigWcBackend extends IspconfigRegister {
    public function __construct(){
        parent::__construct();
        
        // enable changing the due date through ajax
        add_action( 'wp_ajax_update_invoice_due_date', array(&$this,'update_invoice_due_date_callback') );

        // the rest after this is for NON-AJAX requests
        if(defined('DOING_AJAX') && DOING_AJAX) return;

        if(is_admin()) {
            // used to trigger on invoice creation located in ispconfig_create_pdf.php
            IspconfigInvoicePdf::init()->Trigger();
            // OPTIONS: Extend the ISPConfig options with some addition properties using 'ispconfig_options' hook
            add_action('ispconfig_options', array($this, 'ispconfig_options'));
            add_action( 'admin_enqueue_scripts', [&$this, 'load_js'] );
            
            // BACKEND: Extend the WooCommerce products with ISPConfig templates (as selections)
            add_action('woocommerce_product_options_general_product_data', array($this, 'custom_product_data') );
            add_action('woocommerce_process_product_meta', array($this, 'custom_product_data_save') );
            // BACKEND: Order action for invoices
            add_action( 'woocommerce_order_actions', array( $this, 'wc_order_meta_box_actions' ) );
            add_action( 'woocommerce_order_action_ispconfig_save_invoice', array( $this, 'SaveInvoiceFromOrder' ) );
            add_action( 'woocommerce_order_action_ispconfig_preview_invoice', array( $this, 'PreviewInvoiceFromOrder' ) );
            add_action( 'woocommerce_order_action_ispconfig_save_offer', array( $this, 'SaveOfferFromOrder' ) );
            add_action( 'woocommerce_order_action_ispconfig_preview_offer', array( $this, 'PreviewOfferFromOrder' ) );
        } else {
            // Schedules are executed as NON ADMIN
            add_action('invoice_reminder', [&$this, 'invoice_reminder']);
        }
    }
    
    public function load_js(){
        wp_enqueue_script( 'my_custom_script', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig-admin.js?_' . time() );
    }

    /**
     * BACKEND: Additional setting for WooCommerce transactions
     */
    public function ispconfig_options(){
        if(!self::IsWooCommerceAvailable())
            echo "<div class='inside' style='color: red; font-size: 110%; font-weight: bold;'>PLEASE NOTE: WooCommerce is not available</div>";
        ?>
        <h3>WooCommerce</h3>
        <div class="inside">
        <h4>Invoice PDF</h4>
        <?php
        WPISPConfig3::getField('wc_pdf_title', 'Document Title');
        WPISPConfig3::getField('wc_pdf_logo', 'Logo Image');
        WPISPConfig3::getField('wc_pdf_addressline', 'Address line');
        WPISPConfig3::getField('wc_pdf_condition', 'Conditions', 'textarea');
        WPISPConfig3::getField('wc_pdf_info', 'Info Block', 'textarea');
        WPISPConfig3::getField('wc_pdf_block1', 'Block #1', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
        WPISPConfig3::getField('wc_pdf_block2', 'Block #2', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
        WPISPConfig3::getField('wc_pdf_block3', 'Block #3', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
        ?>
        </div>
        <div class="inside">
        <h4>Scheduled Tasks</h4>
        <?php
        WPISPConfig3::getField('wc_mail_reminder', 'Admin Email<br />(for payment reminders)');
        WPISPConfig3::getField('wc_mail_sender', 'Sender Email<br />(customer see this)');

        WPISPConfig3::getField('wc_payment_reminder', 'Payment Reminder<br />(' . WPISPConfig3::$OPTIONS['wc_mail_reminder'] . ')','checkbox');
        WPISPConfig3::getField('wc_payment_message', 'Reminder Message<br />(for admins only)','textarea');

        WPISPConfig3::getField('wc_recur_reminder', 'Recurring Reminder<br />(for customers)','checkbox');
        WPISPConfig3::getField('wc_recur_test', 'Test Recurring<br />(use admin email instead)','checkbox');
        WPISPConfig3::getField('wc_recur_message', 'Recurring Message<br />(for customers)', 'textarea');
        ?>
        <input type="hidden" name="wc_enable" value="1" />
        </div>
        <?php
    }

    public function update_invoice_due_date_callback(){
        global $wpdb;

        $result = '';
        if(!empty($_POST['invoice_id'])) {
            $invoice = new IspconfigInvoice(intval($_POST['invoice_id']));

            if(!empty($_POST['due_date']))
                $invoice->due_date = $result = date('Y-m-d H:i:s', strtotime($_POST['due_date']));
            if(!empty($_POST['paid_date']))
                $invoice->paid_date = $result = date('Y-m-d H:i:s', strtotime($_POST['paid_date']));

            $invoice->Save();
        }

        echo json_encode($result);
        wp_die();
    }
    
    /**
     * BACKEND: Connect to ISPConfig through SOAP to display Client Limit templates in product list
     */
    public function custom_product_data(){
        echo '<div class="options_group ispconfig"><h2>ISPConfig</h2>';
        try {
            $this->session_id = $this->soap->login(WPISPConfig3::$OPTIONS['soapusername'], WPISPConfig3::$OPTIONS['soappassword']);
            $templates = $this->GetClientTemplates();
            
            $options = [0 => 'None'];
            foreach($templates as $v) {
                $options[$v['template_id']] = $v['template_name'];
            }
            woocommerce_wp_select(['id' => '_ispconfig_template_id', 'label' => '<strong>Client Limit Template</strong>', 'options' => $options]);
            
            $this->soap->logout($this->session_id);
        } catch(SoapFault $e) {
            echo "<div style='color:red; margin: 1em;'>ISPConfig SOAP Request failed: " . $e->getMessage() . '</div>';
        }
  
        echo '</div>';
    }
    
    /**
     * BACKEND: Used to save the template ID for later use (Cart/Order)
     */
    public function custom_product_data_save($post_id){
        if(!empty($_POST['_ispconfig_template_id']))
            update_post_meta($post_id, '_ispconfig_template_id', $_POST['_ispconfig_template_id']);
    }
    
    /**
     * BACKEND: Add an additional action for orders to ...
     */
    public function wc_order_meta_box_actions($actions){
        $actions['ispconfig_save_invoice'] = __('ISPCONFIG: Save Invoice','wp-ispconfig3');
        $actions['ispconfig_preview_invoice'] = __('ISPCONFIG: Preview Invoice','wp-ispconfig3');
        $actions['ispconfig_preview_offer'] = __('ISPCONFIG: Preview Offer','wp-ispconfig3');
        return $actions;
    }
    
    public function PreviewOfferFromOrder($order){
        $invoice = new IspconfigInvoice($order);
        // display invoice as stream and die
        IspconfigInvoicePdf::init()->BuildInvoice($invoice, true, true);
        die;
    }

    public function PreviewInvoiceFromOrder($order){
        // load the invoice by passing the WC_Order object
        $invoice = new IspconfigInvoice($order);
        // display invoice as stream and die
        IspconfigInvoicePdf::init()->BuildInvoice($invoice, false, true);
        die;
    }

    /**
     * BACKEND: Fires the action previously added in 'wc_order_meta_box_actions'
     */
    public function SaveInvoiceFromOrder($order){
        error_log("SaveInvoiceFromOrder: " . print_r($order, true));
        $invoice = new IspconfigInvoice($order);
        $invoice->makeNew();

        if($invoice->Save()){
            $order->add_order_note("Invoice ".$invoice->invoice_number." created");
        }
       
        return $invoice;
    }
    
    /**
     * Show the invoice list in admin menu
     */
    public static function DisplayInvoices(){
        if(!self::IsWooCommerceAvailable()) {
            echo '<div class="wrap"><div class="notice notice-error"><p>WooCommerce is not installed</p></div></div>';
            return;
        }

        $invList = new ISPConfigInvoiceList();
        
        $a = $invList->current_action();
        $invList->prepare_items();
        ?>
        <div class='wrap'>
            <h1>Invoices</h1>
            <h2></h2>
            <form action="" method="GET">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                <input type="hidden" name="action" value="filter" />
                Filter Customer:
                <select name="id">
                    <option value="">[any]</option>
                <?php  
                $users = get_users(['role' => 'customer']);
                foreach ($users as $u) {
                    $company = get_user_meta($u->ID, 'billing_company', true);
                    $selected = (isset($_GET['id']) && $u->ID == $_GET['id'])?'selected':'';
                    echo '<option value="'.$u->ID.'" '.$selected.'>'. $company . ' (' .$u->user_login.')</option>';
                }
                ?>
                </select>
                <input type="checkbox" id="recur_only" name="recur_only" value="1" <?php echo (!empty($_GET['recur_only'])?'checked':'') ?> /> <label for="recur_only">Recurring only</label>
                <input type="submit" value="filter">
                <input type="button" value="Reset" onclick="document.location.href='?page=<?php echo $_REQUEST['page'] ?>'">
            </form>
            <?php $invList->display(); ?>
        </div>
        <?php
    }

    public static function IsWooCommerceAvailable(){
        return in_array( 'woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }
        
    public function invoice_reminder(){
        $this->payment_reminder();
        $this->payment_recur_reminder();
    }

    /**
     * SCHEDULE: Daily reminder on invoices reached the due date
     */
    private function payment_reminder(){
        global $wpdb;

        if(WPISPConfig3::$OPTIONS['wc_payment_reminder'] != '1') {
            error_log("invoice_payment_reminder DISABLED");
            return;
        }

        if(!filter_var(WPISPConfig3::$OPTIONS['wc_mail_reminder'], FILTER_VALIDATE_EMAIL)) {
            error_log("invoice_payment_reminder INVALID EMAIL");
            return;
        }

        error_log("invoice_payment_reminder started");

        $res = $wpdb->get_results("SELECT i.*, u.display_name, u.user_login FROM {$wpdb->prefix}".IspconfigInvoice::TABLE." AS i 
                                LEFT JOIN {$wpdb->posts} AS p ON (p.ID = i.wc_order_id)
                                LEFT JOIN {$wpdb->users} AS u ON u.ID = i.customer_id
                                WHERE i.deleted = 0 AND (i.status & 2) = 0 AND DATE(i.due_date) <= CURDATE()", OBJECT);
            
        // remind admin when customer has not yet paid the invoices
        if(!empty($res)) {
            $subject = sprintf("Payment reminder - %s outstanding invoice(s)", count($res));

            $content = '';
            foreach ($res as $k => $v) {
                
                $userinfo = "'{$v->display_name}' ($v->user_login)";
                $u = get_userdata($v->customer_id);
                if($u) $userinfo = "'{$u->first_name} {$u->last_name}' ($u->user_email)";

                $content .= "\n\n" . __('Invoice', 'wp-ispconfig3').": {$v->invoice_number}\n". __('Customer', 'woocommerce') .": $userinfo\n" . __('Due at', 'wp-ispconfig3') .": " . date('d.m.Y', strtotime($v->due_date));
            }
            // attach the pdf documents via string content
            add_action('phpmailer_init', function($phpmailer) use($res){
                foreach($res as $v) {
                    $phpmailer->AddStringAttachment($v->document, $v->invoice_number . '.pdf');
                }
            });

            $message = sprintf(WPISPConfig3::$OPTIONS['wc_payment_message'], $content);

            error_log("invoice_payment_reminder - Sending reminder to: " . WPISPConfig3::$OPTIONS['wc_mail_reminder']);
            $res = wp_mail(WPISPConfig3::$OPTIONS['wc_mail_reminder'], 
                        $subject,
                        $message,
                        'From: '. WPISPConfig3::$OPTIONS['wc_mail_sender']);
            error_log("wp_mail (using PHPMailer): " . $res);
        }
    }

    /**
     * SCHEDULE: Reminder on recurring invoices (month/year) - daily checked
     */
    private function payment_recur_reminder(){
        global $wpdb;

        if(WPISPConfig3::$OPTIONS['wc_recur_reminder'] != '1') {
            error_log("invoice_recur_reminder DISABLED");
            return;
        }

        error_log("invoice_recur_reminder started");

        $res = $wpdb->get_results("SELECT p.ID,p.post_date_gmt, pm.meta_value AS payment_period FROM {$wpdb->posts} p 
                                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                                WHERE pm.meta_key = 'ispconfig_period' AND p.post_status = 'wc-completed'", OBJECT);
        
        // remind admin about new recurring invoices
        if(!empty($res)) {
            $reminders = 0;
            
            $curDate = new DateTime();

            $messageBody = WPISPConfig3::$OPTIONS['wc_recur_message'];

            foreach ($res as $v) {
                 $d = new DateTime($v->post_date_gmt);

                if($v->payment_period == 'y') {
                    // yearly
                    $postDate = $d->format('md');
                    $dueDate = $curDate->format('md');
                } else if($v->payment_period == 'm') {
                    // monthly
                    $postDate = $d->format('d');
                    $dueDate = $curDate->format('d');
                } else {
                    continue;
                }

                if(isset($dueDate, $postDate) && $dueDate == $postDate) {
                    // send the real invoice
                    $order = new WC_Order($v->ID);
                    $invoice = new IspconfigInvoice($order);
                    $invoice->makeRecurring();

                    add_action('phpmailer_init', function($phpmailer) use($invoice){
                        $phpmailer->clearAttachments();
                        $phpmailer->AddStringAttachment($invoice->document, $invoice->invoice_number . '.pdf');
                    });

                    // CHECK IF IT IS TEST - DO NOT SEND TO CUSTOMER THEN
                    if(WPISPConfig3::$OPTIONS['wc_recur_test'])
                        $recipient = WPISPConfig3::$OPTIONS['wc_mail_reminder'];
                    else
                        $recipient = $order->billing_email;
                    error_log("invoice_recur_reminder - Sending invoice ".$invoice->invoice_number." to: " . $recipient);

                    $success = wp_mail($recipient, 
                            __('Invoice', 'wp-ispconfig3') . ' ' . $invoice->invoice_number,
                            sprintf($messageBody, $invoice->invoice_number),
                            'From: '. WPISPConfig3::$OPTIONS['wc_mail_sender']);
                    
                    if($success)
                    {
                        
                        $invoice->Submitted();

                        $invoice->Save();
                        $order->add_order_note("Invoice ".$invoice->invoice_number." sent to: " . $recipient);
                        $reminders++;
                    }
                }
            }
        }
    }

    public static function install(){
       // install the invoice table
       include_once WPISPCONFIG3_PLUGIN_WC_DIR . 'ispconfig_invoice.php';
       IspconfigInvoice::install();

        // add cap allowing adminstrators to download invoices by default
        $role = get_role('administrator');
        $role->add_cap('ispconfig_invoice');

        // install WP schedule to remind due date
        if (! wp_next_scheduled ( 'invoice_reminder' )) {
            error_log("Installing schedule: invoice reminders");
	        wp_schedule_event(time(), 'daily', 'invoice_reminder');
        }

        // refresh rewrite rules
        flush_rewrite_rules();
    }

    public static function deactivate(){
        error_log("Uninstalling schedule: invoice reminders");
        wp_clear_scheduled_hook('invoice_reminder');
    }

    public static function uninstall(){
        
    }
}
?>
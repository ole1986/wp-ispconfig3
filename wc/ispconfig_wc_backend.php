<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

global $wpdb;

class IspconfigWcBackend {
    public function __construct(){
        // enable changing the due date through ajax
        add_action( 'wp_ajax_ispconfig_backend', array(&$this, 'doAjax') );

        // the rest after this is for NON-AJAX requests
        if(defined('DOING_AJAX') && DOING_AJAX) return;

        if(is_admin()) {
            // used to trigger on invoice creation located in ispconfig_create_pdf.php
            IspconfigInvoicePdf::init()->Trigger();
            // OPTIONS: Extend the ISPConfig options with some addition properties using 'ispconfig_options' hook
            add_action('ispconfig_option_tabs', [$this, 'ispconfig_option_tabs']);
            add_action('ispconfig_options', [$this, 'ispconfig_options']);

            add_action( 'admin_enqueue_scripts', [$this, 'load_js'] );

            add_action('add_meta_boxes', [$this, 'ispconfig_invoice_box'] );
            add_action('post_updated', [$this, 'ispconfig_invoice_submit']);           
        } else {
            // Schedules are executed as NON ADMIN
            add_action('invoice_reminder', [&$this, 'invoice_reminder']);
        }
    }

    public function ispconfig_invoice_box(){
        add_meta_box( 'ispconfig-invoice-box', 'ISPConfig 3', [$this, 'ispconfig_invoice_box_callback'], 'shop_order', 'side', 'high' );
    }

    public function ispconfig_invoice_box_callback() {
        global $post_id, $post;

        $domain = get_post_meta($post_id, 'Domain', true);

        $period = get_post_meta($post_id, '_ispconfig_period', true);

        $customer_email = get_post_meta($post_id, '_billing_email', true, '');

        ?>
        <p>
            <label class="post-attributes-label">Domain: </label>
            <?php echo $domain ?>
        </p>
        <p>
            <label class="post-attributes-label" for="ispconfig_period"><?php _e('Payment period', 'wp-ispconfig3') ?>:</label>
            <select id="ispconfig_period" data-id="<?php echo $post_id ?>" onchange="ISPConfigAdmin.UpdatePeriod(this)">
                <option value="">Off</option>
            <?php foreach(IspconfigInvoice::$PERIOD as $k => $v) { ?>
                <option value="<?php echo $k ?>" <?php echo ($k == $period)?'selected': '' ?> ><?php _e($v, 'wp-ispconfig3') ?></option>
            <?php } ?>
            </select>
        </p>
        <p class="ispconfig_scheduler_info <?php if(empty($period)) echo 'hidden'; ?>">
            <?php printf(__("A scheduler will submit the invoice to '%s'", 'wp-ispconfig3'), $customer_email); ?>
        </p>
        <p style="text-align: right">
            <button type="submit" name="ispconfig_invoice_action" class="button" value="preview">
                <?php printf(__('Preview %s'), '') ?>
            </button>
            <button type="submit" name="ispconfig_invoice_action" class="button" value="offer">
                <?php _e('Offer', 'wp-ispconfig3') ?>
            </button>
            <button type="submit" name="ispconfig_invoice_action" class="button button-primary" value="invoice">
                <?php _e( 'Invoice', 'wp-ispconfig3') ?>
            </button>
        </p>
        <?php
    }

    public function ispconfig_invoice_submit($post_id){
        global $post;

        if(!isset($_REQUEST['ispconfig_invoice_action'])) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        remove_action( 'post_updated', [$this, 'ispconfig_invoice_submit']);

        if( ! ( wp_is_post_revision( $post_id) || wp_is_post_autosave( $post_id ) ) ) {
            $order = new WC_Order($post);

            switch($_REQUEST['ispconfig_invoice_action'])
            {
                case 'preview':
                    $this->PreviewInvoiceFromOrder($order);
                    break;
                case 'offer':
                    $this->PreviewOfferFromOrder($order);
                    break;
                case 'invoice':
                    $this->SaveInvoiceFromOrder($order);
                    
                    break;
            }
        }
    }

    public function load_js(){
        wp_enqueue_script( 'my_custom_script', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig-admin.js?_' . time() );
    }

    public function ispconfig_option_tabs(){
        ?>
        <li class="hide-if-no-js"><a href="#ispconfig-invoice"><?php _e('Invoices', 'wp-ispconfig3')?></a></li>
        <li class="hide-if-no-js"><a href="#ispconfig-scheduler"><?php _e('Task Scheduler', 'wp-ispconfig3') ?></a></li>
        <li class="hide-if-no-js"><a href="#ispconfig-template"><?php _e('Templates', 'wp-ispconfig3') ?></a></li>
        <?php
    }

    /**
     * BACKEND: Additional setting for WooCommerce transactions
     */
    public function ispconfig_options(){
        if(!self::IsWooCommerceAvailable()) {
            echo "<div class='inside' style='color: red; font-size: 110%; font-weight: bold;margin-top:1em;'>WooCommerce is not available</div>";
            return;
        }
        ?>
        <div id="ispconfig-invoice" class="inside tabs-panel" style="display: none;">
            <h3><?php _e('Invoice template (PDF)', 'wp-ispconfig3') ?></h3>
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
        <div id="ispconfig-scheduler" class="inside tabs-panel" style="display: none;">
            <h3><?php _e('Run commands', 'wp-ispconfig3') ?></h3>
            <?php if( wp_get_schedule( 'invoice_reminder' )): ?>
                <div class="notice notice-success"><p>The scheduled task is properly installed and running</p></div>
            <?php else: ?>
                <div class="notice notice-error"><p>The scheduled task is NOT INSTALLED - Try to reactivate the plugin</p></div>
            <?php endif; ?>
            <p>
                <a href="javascript:void(0)" onclick="ISPConfigAdmin.RunReminder(this)" class="button">Run Payment Reminder</a><br />
                Execute the payment reminder being sent to <strong>Admin Email</strong>.<br />This reminder usually occurs DAILY whenever an invoice is due.
            </p>
            <p>
                <a href="javascript:void(0)" onclick="ISPConfigAdmin.RunRecurr(this)" class="button">Test Recurr Payment</a><br />
                Test the recurring payments (which is usually send to customer)<br />by overwriting the recipient addresses to <strong>Admin Email</strong>
            </p>
            <p>
                <a href="javascript:void(0)" onclick="ISPConfigAdmin.RunRecurrReminder(this)" class="button">Run Recurr Reminder</a><br />
                Run the recurring reminder now
            </p>
            <h3><?php _e('Sender info', 'wp-ispconfig3') ?></h3>
            <?php
            WPISPConfig3::getField('wc_mail_reminder', '<strong>Admin Email</strong><br />used for payment reminders and testing purposes');
            WPISPConfig3::getField('wc_mail_sender', '<strong>Sender Email</strong><br />Customer will see this address');
            ?>
            <h3><?php _e('Payments', 'wp-ispconfig3') ?></h3>
            <?php
            WPISPConfig3::getField('wc_payment_reminder', '<strong>'. __('Payment report', 'wp-ispconfig3') .'</strong><br />send a daily report of unpaid invoices to "Admin Email"','checkbox');
            WPISPConfig3::getField('wc_recur', '<strong>' . __('Recurring payments', 'wp-ispconfig3').'</strong><br />Submit every invoice to the customer based on the recurring payment period','checkbox');
            WPISPConfig3::getField('wc_recur_test', '<span style="color: red; font-weight: bold">Test Recurring</span><br />replace the recipient email with the admin email to test recurring PAYMENTS and REMINDERS','checkbox');
            WPISPConfig3::getField('wc_recur_reminder', '<strong>'. __('Payment reminder', 'wp-ispconfig3').'</strong><br />Send payment reminders to customer when invoice is due','checkbox');
            WPISPConfig3::getField('wc_recur_reminder_age', '<strong>' . __('First reminder (days)', 'wp-ispconfig3') . '</strong><br />The number of days (after due date) when a reminder should be sent to customer');
            WPISPConfig3::getField('wc_recur_reminder_interval', '<strong>'. __('Reminder interval', 'wp-ispconfig3') .'</strong><br />The number of days (after first occurence) a reminder should be resent to customer');
            WPISPConfig3::getField('wc_recur_reminder_max', '<strong>'. __('Max reminders', 'wp-ispconfig3') .'</strong><br />How many reminders should be sent for a single invoice to the customer');
            ?>
            <input type="hidden" name="wc_enable" value="1" />
        </div>
        <div id="ispconfig-template" class="inside tabs-panel" style="display: none;">
            <h3><?php _e('Templates', 'wp-ispconfig3') ?></h3>
            <p>
                Customize your templates being sent internally or to the customer<br />
                <strong>PLEASE NOTE: The changes will immediatly take effect once you pressed "Save"</strong>
            </p>
            <h3><?php _e('Payments', 'wp-ispconfig3') ?></h3>
            <?php
            $attr = [
                'label_attr' => [ 'style' => 'width: 200px; display:inline-block;vertical-align:top;'],
                'input_attr' => ['style' => 'margin-left: 1em; width:50em;height: 200px']
            ];
            WPISPConfig3::getField('wc_payment_message', '<strong>'. __('Payment report', 'wp-ispconfig3') .'</strong><br />Inform the administrator (see "Admin Email") about outstanding invoices','textarea', $attr);
            WPISPConfig3::getField('wc_recur_message', '<strong>' . __('Recurring payments', 'wp-ispconfig3').'</strong><br />Submit the recurring invoice to the customer containing this message', 'textarea', $attr);
            WPISPConfig3::getField('wc_recur_reminder_message', '<strong>'. __('Payment reminder', 'wp-ispconfig3').'</strong><br />Submit the recurring invoice to the customer containing this message', 'textarea', $attr);
            ?>
            <input type="hidden" name="wc_enable" value="1" />
        </div>
        <?php
    }

    public function doAjax(){
        global $wpdb;

        $result = '';
        if(!empty($_POST['invoice_id'])) {
            $invoice = new IspconfigInvoice(intval($_POST['invoice_id']));
            if(!empty($_POST['due_date']))
                $invoice->due_date = $result = date('Y-m-d H:i:s', strtotime($_POST['due_date']));
            if(!empty($_POST['paid_date']))
                $invoice->paid_date = $result = date('Y-m-d H:i:s', strtotime($_POST['paid_date']));

            $invoice->Save();
        } else if(!empty($_POST['order_id']) && isset($_POST['period'])) {
            if(!empty($_POST['period']))
                update_post_meta( intval($_POST['order_id']), '_ispconfig_period', $_POST['period']);
            else
                delete_post_meta( intval($_POST['order_id']), '_ispconfig_period');

            $result = $_POST['period'];
        } else if(!empty($_POST['payment_reminder'])) {
            $result = $this->payment_reminder();
        } else if(!empty($_POST['recurr'])) {
            if(!empty(WPISPConfig3::$OPTIONS['wc_recur_test']))
                $result = $this->payment_recur();
            else
                $result = -2;
        } else if(!empty($_POST['recurr_reminder'])) {
            $result = $this->payment_recur_reminder();
        }

        echo json_encode($result);
        wp_die();
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
     * BACKEND: Save and invoice base from the order
     */
    public function SaveInvoiceFromOrder($order){
        error_log("SaveInvoiceFromOrder");
        error_log( print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true));

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
            <h1><?php _e('Invoices', 'wp-ispconfig3') ?></h1>
            <h2></h2>
            <form action="" method="GET">
                <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                <input type="hidden" name="action" value="filter" />
                <label class="post-attributes-label" for="user_login">Filter Customer:</label>
                <select name="id" style="min-width: 200px">
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
        $this->payment_recur();
        $this->payment_recur_reminder();
    }

    /**
     * SCHEDULE: Daily reminder for administrators on invoices which are due
     */
    private function payment_reminder(){
        global $wpdb;

        if(empty(WPISPConfig3::$OPTIONS['wc_payment_reminder'])) {
            error_log("WARNING: Payment reminder for adminstrators is disabled");
            return -1;
        }
            

        if(!filter_var(WPISPConfig3::$OPTIONS['wc_mail_reminder'], FILTER_VALIDATE_EMAIL))
            return -2;

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
            $ok = wp_mail(WPISPConfig3::$OPTIONS['wc_mail_reminder'], 
                        $subject,
                        $message,
                        'From: '. WPISPConfig3::$OPTIONS['wc_mail_sender']);
            return $ok;
        }
        return 0;
    }

    /**
     * SCHEDULE: Submit the recurring invoices (based on the period) to customers email address (daily checked)
     */
    private function payment_recur(){
        global $wpdb;

        if(empty(WPISPConfig3::$OPTIONS['wc_recur'])) {
            error_log("WARNING: Recurring payment submission is disabled");
            return -1;
        }

        $res = $wpdb->get_results("SELECT p.ID,p.post_date_gmt, pm.meta_value AS payment_period FROM {$wpdb->posts} p 
                                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                                WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND pm.meta_key = '_ispconfig_period'", OBJECT);
        
        // remind admin about new recurring invoices
        if(!empty($res)) {
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
                    if(!empty(WPISPConfig3::$OPTIONS['wc_recur_test']))
                        $recipient = WPISPConfig3::$OPTIONS['wc_mail_reminder'];
                    else
                        $recipient = $order->get_billing_email();
                    error_log("INFO: Sending recurring invoice ".$invoice->invoice_number." to: " . $recipient);

                    $success = wp_mail($recipient, 
                            __('Invoice', 'wp-ispconfig3') . ' ' . $invoice->invoice_number,
                            sprintf($messageBody, $invoice->invoice_number),
                            'From: '. WPISPConfig3::$OPTIONS['wc_mail_sender']);
                    
                    if($success)
                    {
                        $invoice->Submitted();

                        $invoice->Save();
                        $order->add_order_note("Invoice ".$invoice->invoice_number." sent to: " . $recipient);
                    }
                }
            }
        }
        return 0;
    }
    /**
     * SCHEDULE: Recurring reminder being sent to customer when due date is older "wc_recur_reminder_age"
     */
    private function payment_recur_reminder() {
        global $wpdb;
        
        if(empty(WPISPConfig3::$OPTIONS['wc_recur_reminder'])) {
            error_log("WARNING: Recurring reminder on due invoices is disabled");
            return -1;
        }

        $age = intval(WPISPConfig3::$OPTIONS['wc_recur_reminder_age']);
        $interval = intval(WPISPConfig3::$OPTIONS['wc_recur_reminder_interval']);

        $max = intval(WPISPConfig3::$OPTIONS['wc_recur_reminder_max']);

        $messageBody = WPISPConfig3::$OPTIONS['wc_recur_reminder_message'];

        // fetch all invoices which have status = Sent (ignore all invoice which are already marked as paid)
        $sql = "SELECT * FROM {$wpdb->prefix}".IspconfigInvoice::TABLE." WHERE `status` = 1 AND DATE_ADD(NOW(), INTERVAL -{$age} DAY) > due_date AND reminder_sent < $max";

        $res = $wpdb->get_results($sql, OBJECT);

        if(!empty($res)) {
            foreach ($res as $v) {
                $due_date = new DateTime($v->due_date);
                $due_date->add(new DateInterval("P{$age}D"));

                $diff  = $due_date->diff(new DateTime());
                $diffDays = intval($diff->format("%a"));
                $rest = $diffDays % $interval;

                if($rest > 0) {
                    error_log("Skipping recurring reminder for {$v->invoice_number}");
                    continue;
                }

                $v->reminder_sent++;

                $order = new WC_Order($v->wc_order_id);

                if(!empty(WPISPConfig3::$OPTIONS['wc_recur_test']))
                    $recipient = WPISPConfig3::$OPTIONS['wc_mail_reminder'];
                else
                    $recipient = $order->get_billing_email();
                
                
                error_log("INFO: Sending recurring reminder for {$v->invoice_number} to $recipient | DIFF: $diffDays | REST: $rest");

                // attach invoice pdf into php mailer
                add_action('phpmailer_init', function($phpmailer) use($v){
                    $phpmailer->clearAttachments();
                    $phpmailer->AddStringAttachment($v->document, $v->invoice_number . '.pdf');
                });

                $success = wp_mail($recipient, 
                    __('Payment reminder', 'wp-ispconfig3') . ' ' . $v->invoice_number,
                    sprintf($messageBody, $v->invoice_number),
                    'From: '. WPISPConfig3::$OPTIONS['wc_mail_sender']
                );
        
                if($success)
                {
                    $order->add_order_note("Invoice reminder #{$v->reminder_sent} for ".$v->invoice_number." sent to " . $recipient);
                    $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->prefix}".IspconfigInvoice::TABLE." SET reminder_sent = {$v->reminder_sent} WHERE ID = %s", $v->ID ) );
                }
            }
        }

        return 0;
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
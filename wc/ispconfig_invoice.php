<?php

class IspconfigInvoice {
    /**
     * database table without WP prefix
     */
    const TABLE = 'ispconfig_invoice';
    const SUBMITTED = 1;
    const PAID = 2;
    const RECURRING = 4;
    /**
     * Possible status flags 
     */
    public static $STATUS = [
        1 => 'Submitted',
        2 => 'Paid',
        4 => 'Recur.Task'
    ];

    /**
     * allowed db columns
     */
     protected static $columns = [
         'customer_id' => 'bigint(20) NOT NULL DEFAULT 0',
         'wc_order_id' => 'bigint(20) NOT NULL',
         'offer_number'=> 'VARCHAR(50) NOT NULL',
         'invoice_number' => 'VARCHAR(50) NOT NULL',
         'document' => 'mediumblob NULL',
         'created' => 'datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\'',
         'status' => 'smallint(6) NOT NULL DEFAULT 0',
         'due_date' => 'datetime NULL',
         'paid_date' => 'datetime NULL',
         'deleted' => 'BOOLEAN NOT NULL DEFAULT FALSE'
    ];

    /**
     * constructor call with various load options given in parameter $id
     * @param {mixed} $id ID | WC_Order | stdClass 
     */
    public function __construct($id = null){
        $ok = false;
        if(!empty($id) && is_integer($id))
            $ok = $this->load($id);
        else if(!empty($id) && is_object($id) && get_class($id) == 'WC_Order')
            $ok = $this->loadFromOrder($id);
        else if(!empty($id) && is_object($id) && get_class($id) == 'stdClass')
            $ok = $this->loadFromStd($id);
        
        if(!$ok)
            $this->makeNew();
    }

    /**
     * mark current invoice as paid
     */
    public function Paid($withDate = true){
        $this->status |= self::PAID;
        if($withDate)
            $this->paid_date = date('Y-m-d H:i:s');
    }

    /**
     * mark current invoice as submitted
     */
    public function Submitted(){
        $this->status |= self::SUBMITTED;
    }

    public function Recurring(){
        $this->status |= self::RECURRING;
    }

    /**
     * dynamic property loader to lazy load objects
     */
    public function __get($name){
        if($name == 'order' && !empty($this->wc_order_id))
            return new WC_Order($this->wc_order_id);
    }

    /**
     * fetch the associated WC_Order through dynamic property 'order'
     */
    public function Order(){
        return $this->order;
    }

    /**
     * save the current invoice or overwrite when $this->ID is defined
     */
    public function Save(){
        global $wpdb;

        // check for order status
        if($this->order->get_status() == 'pending' && in_array($this->order->payment_method, ['paypal']) ) {
            $this->order->add_order_note("skipped invoice creation because paypal payment is not yet completed");
            return false;
        }

        $item = [];
        foreach(self::$columns as $k => $v){
            if($k == 'deleted') continue;
            if(!empty($this->ID) && $k == 'document') continue;
            if(isset($this->$k))
                $item[$k] = $this->$k;
        }

        $result = false;
        if(!empty($this->ID)) {
            // do not update the document only the meta data
            $result = $wpdb->update("{$wpdb->prefix}". self::TABLE, $item, ['ID' => $this->ID]);
        } else {
            $result = $wpdb->insert("{$wpdb->prefix}". self::TABLE, $item);
            $this->ID = $wpdb->insert_id;
        }

        return $result;
    }

    /**
     * mark the current invoice as deleted
     */
    public function Delete() {
        global $wpdb;

        if(!empty($this->ID))
            $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->prefix}".self::TABLE." SET deleted = 1 WHERE ID = %s", $this->ID ) );
    }

    private function load($id){
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE ID = %d LIMIT 1";
        $item = $wpdb->get_row($wpdb->prepare($query, $id), OBJECT);

        foreach (get_object_vars($item) as $key => $value) {
            $this->$key = $value;
        }

        return (!empty($this->ID))?true:false;
    }

    public function makeNew(){
        unset($this->ID);
        if(!empty($this->order) && is_object($this->order))
        {
            $this->invoice_number = date('Ym') . '-' . $this->order->id . '-R';
            $this->offer_number = date('Ym') . '-' . $this->order->id . '-R';
            $this->wc_order_id = $this->order->id;
            $this->customer_id = $this->order->customer_user;
        } else {
            $this->invoice_number = date("Ymd-His") . '-R';
            $this->offer_number = date("Ymd-His") . '-A';
        }
        $this->created = date('Y-m-d H:i:s');
        $this->due_date = date('Y-m-d H:i:s', strtotime("+14 days"));
        $this->paid_date = null;
        $this->status = 0;

        // (re)create the pdf
        $this->document = IspconfigInvoicePdf::init()->BuildInvoice($this);
    }

    public function makeRecurring(){
        $this->Recurring();
        if(!empty($this->order) && is_object($this->order))
        {
            // reset the payment status for recurring invoices (customer has to pay first)
            unset($this->order->_paid_date);
        }
        $this->makeNew();
    }

    private function loadFromStd($std){
        foreach(get_object_vars($std) as $k => $v){
            $this->{$k} = $v;
        }
        return (!empty($this->ID))?true:false;
    }

    private function loadFromOrder($order){
        global $wpdb;

        $this->order = $order;

        // load additional payment info
        $this->order->_paid_date = get_post_meta($order->id, '_paid_date', true);
        $this->order->ispconfig_period = get_post_meta($order->id, "ispconfig_period", true);


        // get the latest actual from when WC_Order is defined
        $query = "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE wc_order_id = %d AND deleted = 0 ORDER BY created DESC LIMIT 1";
        $item = $wpdb->get_row($wpdb->prepare($query, $order->id), OBJECT);

        if($item != null) {
            foreach (get_object_vars($item) as $key => $value) {
                $this->$key = $value;
            }
            return true;
        } else {
            $this->isFirst = true;
        }

        return false;
    }

    public static function GetStatus($s){
        $s = intval($s);
        $res = '';
        foreach (self::$STATUS as $key => $value) {
            if($s > 0 && ($key & $s)) 
                $res .= $value . ' | ';
        }
        return rtrim($res, ' | ');
    }

    public static function install(){
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}".self::TABLE." (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,";
        foreach(self::$columns as $col => $dtype) {
            $sql.= "$col $dtype,\n";
        }

        $sql.= "UNIQUE KEY id (id) ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
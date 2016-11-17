<?php

class IspconfigInvoice {

    const TABLE = 'ispconfig_invoice';

    public function __construct($id = null){
        if(!empty($id) && is_integer($id))
            $this->load($id);
        else if(!empty($id) && is_object($id))
            $this->loadFromObject($id);
            
        $this->loadDefault();
    }

    public function __get($name){
        if($name == 'order' && !empty($this->wc_order_id))
            return new WC_Order($this->wc_order_id);
    }

    public function Order(){
        return $this->order;
    }

    public function Save(){
        global $wpdb;

        // check for order status
        if($this->order->get_status() == 'pending' && in_array($this->order->payment_method, ['paypal']) ) {
            $this->order->add_order_note("skipped invoice creation because paypal payment is not yet completed");
            return false;
        }

        $item = get_object_vars($this);
        unset($item['order']);

        $result = false;
        if(!empty($this->ID))
            $result = $wpdb->update("{$wpdb->prefix}". self::TABLE, $item, ['ID' => $this->ID]);
        else {
            unset($item['ID']);
            $result = $wpdb->insert("{$wpdb->prefix}". self::TABLE, $item);
            $this->ID = $wpdb->insert_id;
        }

        return $result;
    }

    private function load($id){
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE ID = %d LIMIT 1";
        $item = $wpdb->get_row($wpdb->prepare($query, $id), OBJECT);
        
        $this->invoice_number = date("Ymd-His") . '-R';
        $this->offer_number = date("Ymd-His") . '-A';

        foreach (get_object_vars($item) as $key => $value) {
            $this->$key = $value;
        }
    }

    private function loadDefault(){
        $this->created = date('Y-m-d H:i:s');
        $this->due_date = date('Y-m-d H:i:s', strtotime("+14 days"));
        $this->paid_date = null;
    }

    private function loadFromObject($order){
        global $wpdb;

        // get the latest actual from when WC_Order is defined
        $query = "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE wc_order_id = %d ORDER BY created DESC LIMIT 1";
        $item = $wpdb->get_row($wpdb->prepare($query, $order->id), OBJECT);
        
        if($item != null) {
            foreach (get_object_vars($item) as $key => $value) {
                $this->$key = $value;
            }
        } else {
            $this->invoice_number = date('Ym') . '-' . $order->id . '-R';
            $this->offer_number = date('Ym') . '-' . $order->id . '-R';
            $this->wc_order_id = $order->id;
            $this->customer_id = $order->customer_user;
        }

        $this->order = $order;
        return true;
    }

    public static function install(){
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}".IspconfigInvoice::TABLE." (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) NOT NULL DEFAULT 0,
            wc_order_id bigint(20) NOT NULL,
            offer_number VARCHAR(50) NOT NULL,
            invoice_number varchar(50) NOT NULL,
            document mediumblob NULL,
            created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            status smallint(6) NOT NULL DEFAULT 0,
            due_date datetime NULL,
            paid_date datetime NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
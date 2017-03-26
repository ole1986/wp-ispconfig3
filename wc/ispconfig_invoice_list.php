<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

// load the wordpress list table class
if( ! class_exists( 'WP_List_Table' ) )
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

add_action( 'admin_head', array( 'ISPConfigInvoiceList', 'admin_header' ) );

class ISPConfigInvoiceList extends WP_List_Table {

    private $rows_per_page = 15;
    private $total_rows = 0;

    public function __construct(){
        parent::__construct();
    }

    public static function admin_header() {
        $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
        if( 'ispconfig_invoices' != $page )
            return; 

        echo '<style type="text/css">';
        echo '.wp-list-table .column-ID { width: 40px; }';
        echo '.wp-list-table .column-created { width: 150px; }';
        //echo '.wp-list-table .column-status { width: 100px; }';
        echo '.wp-list-table .column-due_date { width: 200px; }';
        
        echo '</style>';
    }

    public function get_sortable_columns(){
        $sortable = [
            'created' => ['created', true],
            'due_date' => ['due_date', true],
            'paid_date' => ['paid_date', true]
        ];
        return $sortable;
    }

    public function get_columns(){
        $columns = [
            'ID' => 'ID',
            'invoice_number' => __('Invoice', 'wp-ispconfig3'),
            'customer_name'  => __('Customer', 'woocommerce'),
            'order_id'   => __('Order', 'woocommerce'),
            'status' => __('Status'),
            'created'        => __('Created at', 'woocommerce'),
            'due_date'    => __('Due at', 'wp-ispconfig3'),
            'paid_date' => __('Paid at', 'wp-ispconfig3')
        ];
        return $columns;
    }
    
    function column_default( $item, $column_name ) {
        switch( $column_name ) { 
            case 'ID':
            case 'customer_name':
            case 'created':
            case 'due_date':
            case 'paid_date':
                return $item->$column_name;
            case 'status':
                return IspconfigInvoice::GetStatus($item->$column_name);
            default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

    function column_status($item) {
        $actions = [
            'sent' => sprintf('<a href="?page=%s&action=%s&id=%s">Mark Sent</a>', $_REQUEST['page'],'sent',$item->ID),
            'paid' => sprintf('<a href="?page=%s&action=%s&id=%s">Mark Paid</a>', $_REQUEST['page'],'paid',$item->ID)
        ];
        return sprintf('%s %s', IspconfigInvoice::GetStatus($item->status), $this->row_actions($actions) );
    }

    function column_order_id($item){
        $stat = wc_get_order_statuses();
        $recurr = '';
        if(!empty($item->ispconfig_period))
            $recurr = 'Recurring: ' . (($item->ispconfig_period == 'm')?'monthly':'yearly');
        return '<a href="post.php?post='.$item->order_id. '&action=edit" >#' . $item->order_id. ' ('.$stat[$item->post_status].')</a><br />' . $recurr;
    }

    function column_customer_name($item){
        $res = sprintf('<a href="user-edit.php?user_id=%d">%s</a>', $item->user_id, $item->customer_name);
        $res.="<br /> ". $item->user_email;
        return $res;
    }
    
    function column_invoice_number($item) {
        $actions = [
            'delete'    => sprintf('<a href="?page=%s&action=%s&id=%s" onclick="ISPConfigAdmin.ConfirmDelete(this)" data-name="%s">Delete</a>',$_REQUEST['page'],'delete',$item->ID, $item->invoice_number),
            'quote' => sprintf('<a href="?invoice=%s&preview=1" target="_blank">Show Quote</a>',$item->ID),
        ];
        return sprintf('<a target="_blank" href="?invoice=%s">%s</a> %s', $item->ID, $item->invoice_number, $this->row_actions($actions) );
    }

    function column_due_date($item){
        return '<a href="#" data-id="'.$item->ID.'" onclick="ISPConfigAdmin.EditDueDate(this)">'.$item->due_date.'</a>';
    }
    
    public function prepare_items() {
        global $wpdb;
        
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $query = "SELECT i.*, u.user_login AS customer_name, u.user_email AS user_email, u.ID AS user_id, p.ID AS order_id, p.post_status, pm.meta_value AS ispconfig_period 
                    FROM {$wpdb->prefix}".IspconfigInvoice::TABLE." AS i 
                    LEFT JOIN wp_users AS u ON u.ID = i.customer_id
                    LEFT JOIN wp_posts AS p ON p.ID = i.wc_order_id
                    LEFT JOIN wp_postmeta AS pm ON (p.ID = pm.post_id AND pm.meta_key = 'ispconfig_period')
                    WHERE i.deleted = 0";

        if(isset($_GET['page'], $_GET['action'],$_GET['id']) && $_GET['page'] == 'ispconfig_invoices') {
            $a = $_GET['action'];
            $invoice = new IspconfigInvoice( intval($_GET['id']) );
            switch($a) {
                case 'delete':
                    $invoice->Delete();
                    break;
                case 'sent':
                    $invoice->Submitted();
                    $invoice->Save();
                    break;
                case 'paid':
                    $invoice->Paid();
                    $invoice->Save();
                    break;
                case 'filter':
                    if(!empty($_GET['id']))
                        $query = $wpdb->prepare($query . " AND i.customer_id = %d", $_GET['id']);
                    if(!empty($_GET['recur_only']))
                        $query.= " AND pm.meta_value IS NOT NULL";
                    break;
            }
        }

        $this->applySorting($query);
        $this->applyPaging($query);
        
        $this->items = $wpdb->get_results($query, OBJECT);

        $this->postPaging();
    }

    private function applySorting(&$query){
        if(isset($_GET['orderby'], $_GET['order'])) {
            $orderby = $_GET['orderby'];
            $order = $_GET['order'];
        } else {
            // default sorting
            $_GET['orderby'] = $orderby = 'created';
            $_GET['order'] = $order = 'desc';
        }

        $query .= " ORDER BY $orderby $order";
    }

    private function applyPaging(&$query){
        // paging settings
        $page = $this->get_pagenum();
        $offset = $this->rows_per_page * $page - $this->rows_per_page;

        $query = str_replace('SELECT ', 'SELECT SQL_CALC_FOUND_ROWS ', $query);
        $query.= " LIMIT {$this->rows_per_page} OFFSET {$offset}";
   }

    private function postPaging(){
        global $wpdb;
        $total_rows = $wpdb->get_var("SELECT FOUND_ROWS();");

        $this->set_pagination_args( [
            'total_items' => $total_rows,	//WE have to calculate the total number of items
            'per_page'    => $this->rows_per_page      //WE have to determine how many items to show on a page
        ] );

        return $total_rows;
    }
}
?>
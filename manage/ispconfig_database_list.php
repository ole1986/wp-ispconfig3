<?php
defined('ABSPATH') || exit;

// load the wordpress list table class
if (! class_exists('WP_List_Table') ) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

add_action('admin_head', array( 'IspconfigDatabaseList', 'admin_header' ));

class IspconfigDatabaseList extends WP_List_Table
{

    private $rows_per_page = 15;
    private $total_rows = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public static function admin_header() 
    {
        $page = ( isset($_GET['page']) ) ? esc_attr($_GET['page']) : false;
        if ('ispconfig_databases' != $page ) {
            return;
        } 

        echo '<style type="text/css">';
        echo '.wp-list-table .column-database_id { width: 40px; }';
        echo '.wp-list-table .column-database_name { width: 350px; }';
        echo '.wp-list-table .column-database_user { width: 200px; }';
        echo '.wp-list-table .column-database_password { width: 200px; }';
        
        echo '</style>';
    }

    public function get_columns()
    {
        $columns = [
            'database_id' => 'ID',
            'database_name' => 'Database Name',
            'database_user' => 'Username',
            'database_password' => 'Password'
        ];
        return $columns;
    }
    
    function column_default( $item, $column_name ) 
    {
        switch($column_name) {
        default:
            return $item->$column_name;
        }
    }
    
    public function prepare_items() 
    {
        /*global $wpdb;*/
        $columns = $this->get_columns();

        $this->_column_headers = array($columns, [], []);

        if (!empty($_GET['user_login'])) {
            $databases = Ispconfig::$Self->withSoap()->GetClientDatabases($_GET['user_login']);
            $this->items = json_decode(json_encode((object) $databases), false);
            Ispconfig::$Self->closeSoap();
        }
    }
}

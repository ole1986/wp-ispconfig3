<?php
defined('ABSPATH') || exit;

// load the wordpress list table class
if (! class_exists('WP_List_Table')) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

add_action('admin_head', array( 'IspconfigWebsiteList', 'admin_header' ));

class IspconfigWebsiteList extends WP_List_Table
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
        if ('ispconfig_websites' != $page) {
            return;
        }

        echo '<style type="text/css">';
        echo '.wp-list-table .column-domain_id { width: 40px; }';
        echo '.wp-list-table .column-domain { width: 150px; }';
        //echo '.wp-list-table .column-status { width: 100px; }';
        echo '.wp-list-table .column-document_root { width: 200px; }';
        echo '.wp-list-table .column-active { width: 40px; }';
        
        echo '</style>';
    }

    public function get_columns()
    {
        $columns = [
            'domain_id' => 'Domain ID',
            'domain' => 'Domain',
            'document_root' => 'Document Root',
            'active' => 'Active'
        ];
        return $columns;
    }
    
    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'domain':
                return '<a href="http://'.$item->$column_name.'" target="_blank">'.$item->$column_name.'</a>';
            default:
                return $item->$column_name;
        }
    }

    function column_active($item)
    {
        if ($item->active === 'y') {
            $actions = [ 'deactivate' => '<a href="javascript:void(0)" data-id="'.$item->domain_id.'" onclick="ISPConfigAdmin.WebsiteStatus(this,\'inactive\')" target="_blank">Deactivate</a>' ];
        } else {
            $actions = [ 'activate' => '<a href="javascript:void(0)" data-id="'.$item->domain_id.'" onclick="ISPConfigAdmin.WebsiteStatus(this,\'active\')" target="_blank">Activate</a>' ];
        }
        
        return sprintf('%s %s', ($item->active == 'y') ? 'Yes' : 'No', $this->row_actions($actions));
    }
    
    public function prepare_items()
    {
        /*global $wpdb;*/
        $columns = $this->get_columns();

        $this->_column_headers = array($columns, [], []);

        if (!empty($_GET['user_login'])) {
            $sites = Ispconfig::$Self->withSoap()->GetClientSites($_GET['user_login']);
            
            if (!empty($sites)) {
                $this->items = json_decode(json_encode((object) $sites), false);
            }
            
            Ispconfig::$Self->closeSoap();
        }
    }
}

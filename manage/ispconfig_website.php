<?php

require_once WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website_list.php';

class IspconfigWebsite {
    public static $Self;

    public static function init() {
         if(!self::$Self)
            self::$Self = new self();
    }

    public function __construct(){

    }
    
    public static function DisplayWebsites(){
        $list = new IspconfigWebsiteList();
        
        //$a = $list->current_action();
        $list->prepare_items();

        ?>
        <div class='wrap'>
            <h1>Websites</h1>
            <h2></h2>
            <p>Please select a customer first, to their websites</p>
            <form action="" method="GET">
                <input type="hidden" name="page" value="ispconfig_websites" />
                <input type="hidden" name="action" value="filter" />
                Customer:
                <select name="user_login" style="min-width: 200px">
                    <option value="">[any]</option>
                <?php  
                $users = get_users(['role' => 'customer']);
                foreach ($users as $u) {
                    $company = get_user_meta($u->ID, 'billing_company', true);
                    $selected = (isset($_GET['user_login']) && $u->user_login == $_GET['user_login'])?'selected':'';
                    echo '<option value="'.$u->user_login.'" '.$selected.'>'. $company . ' (' .$u->user_login.')</option>';
                }
                ?>
                </select>
                <input type="submit" value="filter">
                <input type="button" value="Reset" onclick="document.location.href='?page=<?php echo $_REQUEST['page'] ?>'">
            </form>
            <?php $list->display(); ?>
        </div>
        <?php
    }
}
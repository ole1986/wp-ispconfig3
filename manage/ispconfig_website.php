<?php

require_once WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_website_list.php';

class IspconfigWebsite
{
    public static $Self;

    public static function init() 
    {
        if (!self::$Self) {
            self::$Self = new self();
        }
    }

    public function __construct()
    {
        // enable changing the due date through ajax
        add_action('wp_ajax_ispconfig_website', array(&$this, 'doAjax'));

        // the rest after this is for NON-AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) { return;
        }
    }

    public function doAjax()
    {
        $result = '';
        if (!empty($_POST['website_id']) && !empty($_POST['status'])) {
            $result = Ispconfig::$Self->withSoap()->SetSiteStatus($_POST['website_id'], $_POST['status']);
        }

        return json_encode($result);
        wp_die();
    }
    
    public static function DisplayWebsites()
    {
        $list = new IspconfigWebsiteList();
        
        $list->prepare_items();

        ?>
        <div class='wrap'>
            <h1>Websites</h1>
            <h2></h2>
            <p>Please select a customer to display the sites</p>
            <form action="" method="GET">
                <input type="hidden" name="page" value="ispconfig_websites" />
                <input type="hidden" name="action" value="filter" />
                <label class="post-attributes-label" for="user_login">Customer:</label>
                <select id="user_login" name="user_login" style="min-width: 200px">
                    <option value="">[select customer]</option>
                <?php  
                $users = get_users(['role' => 'customer']);
                foreach ($users as $u) {
                    $company = get_user_meta($u->ID, 'billing_company', true);
                    $selected = (isset($_GET['user_login']) && $u->user_login == $_GET['user_login'])?'selected':'';
                    echo '<option value="'.$u->user_login.'" '.$selected.'>'. $company . ' (' .$u->user_login.')</option>';
                }
                ?>
                </select>
                <input type="submit" value="Filter">
                <input type="button" value="Reset" onclick="document.location.href='?page=<?php echo $_REQUEST['page'] ?>'">
            </form>
            <?php $list->display(); ?>
        </div>
        <?php
    }
}
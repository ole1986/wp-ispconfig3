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
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
    }

    public function doAjax()
    {
        header('Content-Type: application/json');

        $ok = 0;
        if (!empty($_POST['website_id']) && !empty($_POST['status'])) {
            $ok = Ispconfig::$Self->withSoap()->SetSiteStatus($_POST['website_id'], $_POST['status']);
        }
        
        echo $ok;

        wp_die();
    }
    
    public static function DisplayWebsites()
    {
        $list = new IspconfigWebsiteList();
        $list->prepare_items();

        ?>
        <div class='wrap'>
            <h1><?php _e('Websites', 'wp-ispconfig3') ?></h1>
            <h2></h2>
            <p>Pick a user (must match the ISPconfig3 client username) to display all related websites</p>
            <form action="" method="GET">
                <input type="hidden" name="page" value="ispconfig_websites" />
                <input type="hidden" name="action" value="filter" />
                <label class="post-attributes-label" for="user_login">User login:</label>
                <select id="user_login" name="user_login" style="min-width: 200px">
                    <option value="">[select customer]</option>
                <?php
                $users = get_users(['role__in' => WPISPConfig3::$OPTIONS['user_roles']]);
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
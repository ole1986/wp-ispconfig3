<?php
require_once WPISPCONFIG3_PLUGIN_DIR . 'manage/ispconfig_database_list.php';

class IspconfigDatabase
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
    }
    
    public static function DisplayDatabases()
    {
        $list = new IspconfigDatabaseList();
        
        $list->prepare_items();

        ?>
        <div class='wrap'>
            <h1><?php _e('Databases', 'wp-ispconfig3') ?></h1>
            <h2></h2>
            <p>Pick a user (must match the ISPconfig3 client username) to display all related databases</p>
            <form action="" method="GET">
                <input type="hidden" name="page" value="ispconfig_databases" />
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
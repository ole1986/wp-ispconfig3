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
            <h1>Databases</h1>
            <h2></h2>
            <p>Please select a customer to display the databases</p>
            <form action="" method="GET">
                <input type="hidden" name="page" value="ispconfig_databases" />
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
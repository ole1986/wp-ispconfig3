<?php
defined('ABSPATH') || exit;

/**
 * Abstract class to provide soap requests and shortcodes
 *
 * PLEASE NOTE:
 * You can easily add features by creating a "ispconfig_register_<yourfeature>.php" file
 * shortcode can than be use the following in RTF Editor: [ispconfig class=IspconfigRegisterYourfeature]
 *
 * An Example can be found in the file: ispconfig_register_client.php
 */
class Ispconfig extends IspconfigAbstract
{
    /**
     * Provide shortcode execution by calling the class constructor defined "class=..." attribute
     */
    public function Display($attr, $content = null)
    {
        return '<a href="https://github.com/ole1986/wp-ispconfig3/wiki/Extending-wp-ispconfig3-with-custom-shortcodes">Learn more about custom shortcodes for WP-ISPConfig3</a>';
    }
}

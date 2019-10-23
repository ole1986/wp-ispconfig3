<?php
defined('ABSPATH') || exit;

/**
 * Default class used to check for domain using whois
 * Shortcode: [Ispconfig submit_url="..."]
 */
class Ispconfig extends IspconfigAbstract
{
    public function __construct()
    {
        parent::__construct();
        $this->withAjax();
    }

    /**
     * Support for ajax requests (in backend and frontend)
     */
    public function withAjax()
    {
        // used to request whois through ajax
        if (is_admin()) {
            add_action('wp_ajax_ispconfig_whois', [$this, 'AJAX_ispconfig_whois_callback']);
            add_action('wp_ajax_nopriv_ispconfig_whois', [$this, 'AJAX_ispconfig_whois_callback']);
        } else {
            add_action('wp_head', function () {
                echo "<script>var ajaxurl = '" . admin_url('admin-ajax.php') . "'</script>";
            });
        }
    }

    /**
     * Ajax callback for whois requests
     */
    public function AJAX_ispconfig_whois_callback()
    {
        $dom = strtolower($_POST['domain']);

        $ok = self::isDomainAvailable($dom);

        $result = ['text' => '', 'class' => ''];

        if ($ok < 0) {
            $result['text'] = __('The domain could not be verified', 'wp-ispconfig3');
        } elseif ($ok == 0) {
            $result['text'] = __('The domain is already registered', 'wp-ispconfig3');
            $result['class'] = 'ispconfig-msg-error';
        } else {
            $result['class'] = 'ispconfig-msg-success';
            $result['text'] = __('The domain is available', 'wp-ispconfig3');
        }

        echo json_encode($result);
        wp_die();
    }

    public function onPost()
    {
        if ('POST' !== $_SERVER[ 'REQUEST_METHOD']) {
            return false;
        }
    }

    /**
     * Provide shortcode execution by calling the class constructor defined "class=..." attribute
     */
    public function Display($attr, $content = null)
    {
        $result = '';

        if (empty($attr['submit_url'])) {
            $result .= '<div class="ispconfig-msg ispconfig-msg-error">The parameter \'submit_url\' is missing</div>';
        }

        $input_attr = ['id' => 'txtDomain'];
        ?>
        <script>
            function checkDomain() {
                var domain = jQuery('#txtDomain').val();
                jQuery.post(ajaxurl, { action: 'ispconfig_whois', 'domain': domain }, null, 'json').done(function(resp){
                    jQuery('.ispconfig-box').html('<div class="ispconfig-msg ' + resp.class + '">' + resp.text + '</div>');

                    if (resp.class !== 'ispconfig-msg-error') {
                        jQuery('#submit').show();
                        jQuery('#check').hide();
                    } else {
                        jQuery('#submit').hide();
                        jQuery('#check').show();
                    }
                });
            }
        </script>
        <div class="ispconfig-box"></div>
        <div>&nbsp;</div>
        <form action="<?php echo $attr['submit_url']; ?>" method="get">
            <?php WPISPConfig3::getField('domain', 'Check Domain', 'text', ['container' => 'div', 'input_attr' => $input_attr]); ?>
            <div>&nbsp;</div>
            <input id="check" type="button" value="Check Domain" onclick="checkDomain()" />
            <input id="submit" type="submit" value="Continue" style="display: none" />
        </form>
        <?php if (current_user_can('administrator')) : ?>
        <div style="font-size: 90%; margin-top: .5em">
            ADMIN NOTICE: <a href="https://github.com/ole1986/wp-ispconfig3/wiki/Extending-wp-ispconfig3-with-custom-shortcodes" target="_blank">Learn more about custom shortcodes for WP-ISPConfig3</a>
        </div>
        <?php endif; ?>
        <?php
    }
}

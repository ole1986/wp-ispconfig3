<?php
defined('ABSPATH') || exit;

/**
 * Default class used to check for domain using whois
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
        header('Content-Type: application/json');
        
        try {
            $dom = $this->validateDomain($_POST['domain']);
            $this->withSoap();

            $ok = $this->IsDomainAvailable($dom);
    
            $this->closeSoap();
    
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
        } catch (SoapFault $e) {
            $result['text'] = 'Soap connection issue';
            $result['class'] = 'ispconfig-msg-error';
        } catch (Exception $e) {
            $result['text'] = __('The domain name is invalid', 'wp-ispconfig3');
            $result['class'] = 'ispconfig-msg-error';
        }

        $result['value'] = $ok;

        echo json_encode($result);
        wp_die();
    }

    /**
     * Provide shortcode execution by calling the class constructor defined "class=..." attribute
     */
    public function Display($attr, $content = null)
    {
        ?>
        <div class="ispconfig-msg">Please use the WP-ISPConfig3 Blocks feature to validate domains</div>
        <?php if (current_user_can('administrator')) : ?>
        <div style="font-size: 80%; margin-top: .5em">
            ADMIN NOTICE: Custom shortcodes can still be achieved with the WP-ISPConfig3 plugin. <a href="https://github.com/ole1986/wp-ispconfig3/wiki/Extending-wp-ispconfig3-with-custom-shortcodes" target="_blank">Learn More</a>
        </div>
        <?php endif; ?>
        <?php
    }
}

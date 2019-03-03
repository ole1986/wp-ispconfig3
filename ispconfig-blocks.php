<?php

add_action('init', array( 'IspconfigBlock', 'init' ));
add_action('plugins_loaded', function () {
    load_plugin_textdomain('wp-ispconfig3-block', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

function array_map_assoc(callable $f, array $a)
{
    return array_column(array_map($f, array_keys($a), $a), 1, 0);
}

class IspconfigBlock
{
    private $postData = [];

    public static function init()
    {
        new self();
    }

    public function __construct()
    {
        add_action('enqueue_block_editor_assets', [$this, 'LoadAssets']);

        // dynamic gutenberg block rendering
        if (!is_admin()) {
            register_block_type('ole1986/ispconfig-block', ['render_callback' => [$this, 'Output']]);
        }
    }

    public function LoadAssets()
    {
        wp_enqueue_script('ole1986-ispconfig-blocks', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig-blocks.js', ['wp-blocks', 'wp-i18n', 'wp-editor'], true);
        wp_enqueue_style('ole1986-ispconfig-blocks', WPISPCONFIG3_PLUGIN_URL . 'style/ispconfig-blocks.css');
    }

    /**
     * Used to optionally fill input fields with existing data from ISPConfig3
     */
    private function onLoad($props, &$content)
    {
        if ('POST' === $_SERVER[ 'REQUEST_METHOD' ]) {
            return;
        }
        
        switch ($props['action']) {
            default:
            case 'action_create_client':
                $templateField = array_pop(array_filter($props['fields'], function ($field) {
                    return $field['id'] == 'client_template_master';
                }));

                if ($templateField != null) {
                    if (empty($templateField['value'])) {
                        $content .= $this->alert('No client template provided. Either delete the field or set a proper ISPConfig3 template id');
                        return;
                    }

                    Ispconfig::$Self->withSoap();
                    $templates = Ispconfig::$Self->GetClientTemplates();
    
                    $found = array_filter($templates, function ($template) use ($templateField) {
                        return $template['template_id'] == intval($templateField['value']);
                    });
    
                    if (empty($found)) {
                        $content .= $this->alert('No client template found for id ' . $templateField['value']);
                        return;
                    }
                }
                break;
            case 'action_create_website_new':
                if (!empty(WPISPConfig3::$OPTIONS['confirm'])) {
                    $content .= $this->alert('Login information cannot be supplied through email due to disabled confirmation setting.<br />Your can still fill up the form but may not be able to login');
                    return;
                }
                break;
            case 'action_update_client_bank':
            case 'action_update_client':
                $loginField = array_pop(array_filter($props['fields'], function ($field) {
                    return $field['id'] == 'client_username';
                }));

                if (empty($loginField['value'])) {
                    $content .= $this->alert('No client login defined');
                    return;
                }

                Ispconfig::$Self->withSoap();
                $user = Ispconfig::$Self->GetClientByUser($loginField['value'], true);
                
                $this->postData = array_map_assoc(function ($k, $v) {
                    return ['client_'. $k, $v];
                }, (array)$user);

                break;
            case 'action_locknow_client':
                $loginField = array_pop(array_filter($props['fields'], function ($field) {
                    return $field['id'] == 'client_username';
                }));

                $this->postData['client_username'] = $loginField['value'];
                $this->lockClient($props, $content);
                break;
        }
    }

    private function onPost($props, &$content)
    {
        if ('POST' !== $_SERVER[ 'REQUEST_METHOD' ]) {
            return false;
        }

        $ok = false;

        // sanitize text input of all $_POST data
        foreach ($_POST as $key => $value) {
            $this->postData[$key] = sanitize_text_field($value);
        }

        // recover the values of read-only fields
        $this->recoverFieldValues($props);

        try {
            switch ($this->postData['action']) {
                case 'action_create_client':
                    $ok = $this->createClient($props, $content);
                    break;
                case 'action_create_website':
                    $ok = $this->createWebsite($props, $content);
                    break;
                case 'action_create_website_new':
                    $ok = $this->createWebsiteAndClient($props, $content);
                    break;
                case 'action_create_mail':
                    $ok = $this->createMail($props);
                    break;
                case 'action_update_client':
                    $ok = $this->updateClient($props, $content);
                    break;
                case 'action_update_client_bank':
                    $ok = $this->updateClientDetails($props, $content);
                    break;
                case 'action_lock_client':
                    $ok = $this->lockClient($props, $content);
                    break;
                default:
                    throw new Exception("No action defined");
            }
        } catch (Exception $e) {
            $ok = false;
            $content .= $this->alert($e->getMessage());
        }
        
        if (!$ok) {
            return false;
        }

        return true;
    }

    /**
     * Recover all hidden/read-only fields with its original value
     * @param $props properties defined in ispconfig-blocks.js through gutenberg blocks
     */
    private function recoverFieldValues($props)
    {
        if (empty($props['fields'])) {
            return;
        }

        foreach ($props['fields'] as $field) {
            // read-only fields cannot be changed
            // so recover them from its original value
            if ($field['readonly'] || $field['hidden']) {
                $this->postData[$field['id']] = $field['value'];
            }
        }
    }

    private function computeField(&$field)
    {
        switch ($field['computed']) {
            default:
                break;
            case 'wp-locale':
                $lang = get_locale();
                if (!empty($lang)) {
                    $field['value'] = substr($lang, 0, 2);
                }
                break;
            case 'wp-user':
                $user = wp_get_current_user();
                $field['value'] = $user->user_login;
                $field['readonly'] = true;
                break;
            case 'wp-name':
                $user = wp_get_current_user();
                $field['value'] = $user->user_nicename;
                break;
            case 'wp-email':
                $user = wp_get_current_user();
                $field['value'] = $user->user_email;
                break;
        }
    }

    private function alert($message)
    {
        return '<div class="ispconfig-msg ispconfig-msg-error">' . $message . '</div>';
    }

    private function info($message)
    {
        return '<div class="ispconfig-msg">' . $message . '</div>';
    }

    private function success($message)
    {
        return '<div class="ispconfig-msg ispconfig-msg-success">' . $message . '</div>';
    }

    protected function createClient($props, &$content)
    {
        $postData = $this->postData;

        if (empty($postData['client_username'])) {
            $content .= $this->alert('No client username defined');
            return false;
        }

        if (empty($postData['client_contact_name'])) {
            $content .= $this->alert('No client contact name defined');
            return false;
        }

        if (empty($postData['client_email'])) {
            $content .= $this->alert('No client email defined');
            return false;
        }

        if (!is_email($postData['client_email'])) {
            $content .= $this->alert('N valid client email address defined');
            return false;
        }

        if (empty($postData['client_password'])) {
            $content .= $this->alert('No client password defined');
            return false;
        }

        if (strlen($postData['client_password']) < 5) {
            $content .= $this->alert('Client password is to short');
            return false;
        }

        Ispconfig::$Self->withSoap();

        $opt = [
            'contact_name' => $postData['client_contact_name'],
            'email' => $postData['client_email'],
            'username' => $postData['client_username'],
            'password' => $postData['client_password']
        ];

        if (!empty($postData['client_template_master'])) {
            // check if template exists when defined
            $templates = Ispconfig::$Self->GetClientTemplates();

            $found = array_filter($templates, function ($template) use ($postData) {
                return $template['template_id'] == intval($postData['client_template_master']);
            });

            if (empty($found)) {
                $content .= $this->alert('No client template found for id ' . $templateField['value']);
                return;
            }

            $opt['template_master'] = $postData['client_template_master'];
        }

        Ispconfig::$Self->AddClient($opt);

        $content .= $this->success('Client ' . $postData['client_contact_name'] . ' successfully added');

        return true;
    }

    protected function lockClient($props, &$content)
    {
        $postData = $this->postData;

        Ispconfig::$Self->withSoap();
        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }
        
        $opt = ['username' => strtolower($user['username']), 'locked' => 'y', 'canceled' => 'y'];

        Ispconfig::$Self->SetClientID($user['client_id']);
        Ispconfig::$Self->UpdClient($opt);

        $content .= $this->success("Client account " . $user['username'] . ' locked');

        return true;
    }

    protected function createWebsite($props, &$content)
    {
        $postData = $this->postData;

        if (empty($postData['website_domain'])) {
            $content .= $this->alert('No Webdomain defined');
            return false;
        }

        Ispconfig::$Self->withSoap();

        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }

        $content .= "<p style='color:green;'>Client found (". $user['client_id'] .")</p>";

        Ispconfig::$Self->SetClientID($user['client_id']);

        $domain = Ispconfig::$Self->AddWebsite(['domain' => $postData['website_domain']]);

        $content .= $this->success("Website " . $postData['website_domain'] . " successfully created (".$domain.")");

        return true;
    }

    protected function createWebsiteAndClient($props, &$content)
    {
        $postData = $this->postData;

        if (empty($postData['website_domain'])) {
            $content .= $this->alert('No Webdomain defined');
            return false;
        }

        $contactName = $postData['client_contact_name'];

        if (empty($contactName)) {
            $content .= $this->alert('No contact information supplied');
            return false;
        }

        if (!is_email($postData['client_email'])) {
            $content .= $this->alert('No valid email provided');
            return false;
        }

        $domain = strtolower($postData['website_domain']);
        if (!preg_match("/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/", $domain)) {
            $content .= $this->alert('No valid domain provided');
            return false;
        }

        $contactUser = preg_replace('/\W/', '', strtolower($contactName));

        $password = wp_generate_password();

        $opt = ['username' => $contactUser, 'password' => $password,'contact_name' => $contactName, 'company_name' => $postData['client_company_name'], 'email' => $postData['client_email']];

        Ispconfig::$Self->withSoap();
        Ispconfig::$Self->AddClient($opt)
                        ->AddWebsite(['domain' => $domain]);

        $content .= $this->success("Website and client account successfully created");
        
        if (!empty(WPISPConfig3::$OPTIONS['confirm'])) {
            $sent = Ispconfig::$Self->SendConfirmation(array_merge($opt, ['domain' => $domain]));
        }

        return true;
    }

    protected function createMail($props)
    {
    }

    protected function updateClient($props, &$content)
    {
        $postData = $this->postData;

        if (empty($postData['client_username'])) {
            $content .= $this->alert('No client login defined');
            return false;
        }

        Ispconfig::$Self->withSoap();
        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }
        
        Ispconfig::$Self->SetClientID($user['client_id']);
        Ispconfig::$Self->UpdClient(['username' => $postData['client_username'], 'company_name' => $postData['client_company_name'], 'contact_name' => $postData['client_contact_name']]);

        $content .= $this->success("Client information updated");

        return true;
    }

    protected function updateClientDetails($props, &$content)
    {
        $postData = $this->postData;

        if (empty($postData['client_username'])) {
            $content .= $this->alert('No client login defined');
            return false;
        }

        Ispconfig::$Self->withSoap();
        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }

        $data = [];

        foreach ($postData as $key => $value) {
            if ($key == 'client_username') {
                continue;
            }

            if (preg_match('/^client_/', $key)) {
                $newKey = substr($key, 7);
                $data[$newKey] = $value;
            }
        }
        
        $data['username'] = $user['username'];
        
        Ispconfig::$Self->SetClientID($user['client_id']);
        Ispconfig::$Self->UpdClient($data);

        $content .= $this->success("Client information updated");

        return true;
    }

    public function Output($props, $content)
    {
        // set the default action if none is set
        if (empty($props['action'])) {
            $props['action'] = 'action_create_client';
        }

        // compute fields
        if (is_array($props['fields'])) {
            foreach ($props['fields'] as &$field) {
                $this->computeField($field);
            }
        }

        // update field from other plugin using "add_filter" hooks
        $props['fields'] = apply_filters('ispconfig_block_' . $props['action'], $props['fields']);

        $this->onLoad($props, $content);

        // premature end when action is locknow
        if ($props['action'] == 'action_locknow_client') {
            return $content;
        }

        $onPost = $this->onPost($props, $content);

        ob_start();
    
        foreach ($props['fields'] as &$field) {
            $input_attr = [];

            if ($field['hidden']) {
                continue;
            }

            if ($field['readonly']) {
                $input_attr = ['readonly' => 'true'];
            }
           
            if (!empty($this->postData[$field['id']])) {
                $field['value'] = $this->postData[$field['id']];
            }
            
            WPISPConfig3::getField($field['id'], __($field['id'], 'wp-ispconfig3-block'), 'text', ['container' => 'div', 'value' => $field['value'], 'input_attr' => $input_attr]);
        }
    
        $result = ob_get_contents();
    
        ob_end_clean();
    
        $content.= "<form method='post'>";
        $content.= $result;

        if (!empty($props['action'])) {
            $content .= '<div style="margin-top: 1em">';
            $content .= '<input type="hidden" name="action" value="'.$props['action'] .'" />';
            $content .= '<input type="submit" class="button-primary" name="submit" value="'.__($props['action'], 'wp-ispconfig3-block') .'" />';
            $content .= '</div>';
        }
        $content.= "</form>";
        
        return $content;
    }
}

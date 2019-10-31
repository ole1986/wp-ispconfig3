<?php
add_action('init', array( 'IspconfigBlock', 'init' ), 20);

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
        register_block_type('ole1986/ispconfig-block', [
            'render_callback' => [$this, 'Output']
        ]);
    }

    public function LoadAssets()
    {
        wp_enqueue_script('ole1986-ispconfig-blocks', WPISPCONFIG3_PLUGIN_URL . 'js/ispconfig-blocks.js', [ 'wp-blocks', 'wp-i18n' ], WPISPCONFIG3_VERSION);
        wp_enqueue_style('ole1986-ispconfig-blocks', WPISPCONFIG3_PLUGIN_URL . 'style/ispconfig-blocks.css', null, WPISPCONFIG3_VERSION);
        
        // script translations requires the json files (in ped format)
        // to be available in the languages directory
        wp_set_script_translations('ole1986-ispconfig-blocks', 'wp-ispconfig3', WPISPCONFIG3_PLUGIN_DIR . 'languages');
    }

    /**
     * Used to optionally fill input fields with existing data from ISPConfig3
     */
    private function onLoad($props, &$content)
    {
        if ('POST' === $_SERVER[ 'REQUEST_METHOD' ]) {
            return true;
        }
        
        $loginField = array_pop(array_filter($props['fields'], function ($field) {
            return $field['id'] == 'client_username';
        }));


        if (isset($loginField) && $loginField['computed'] == 'session' && empty($_SESSION['ispconfig']['client_username'])) {
            $content .= $this->alert('The session has been expired for the client username');
            return false;
        }

        if (isset($loginField) && $loginField['computed'] == 'wp-user' && !is_user_logged_in()) {
            $content .= $this->alert(__('You need to sign in before continue', 'wp-ispconfig3'));
            return false;
        }


        switch ($props['action']) {
            default:
            case 'action_create_client':
                $templateField = array_pop(array_filter($props['fields'], function ($field) {
                    return $field['id'] == 'client_template_master';
                }));

                if (empty(WPISPConfig3::$OPTIONS['confirm'])) {
                    $content .= $this->alert('Login information cannot be supplied through email due to disabled confirmation setting.<br />Your can still fill up the form but may not be able to login');
                }

                if ($templateField != null) {
                    if (empty($templateField['value'])) {
                        $content .= $this->alert('No client template provided. Either delete the field or set a proper ISPConfig3 template id');
                        return true;
                    }

                    Ispconfig::$Self->withSoap();
                    $templates = Ispconfig::$Self->GetClientTemplates();
    
                    $found = array_filter($templates, function ($template) use ($templateField) {
                        return $template['template_id'] == intval($templateField['value']);
                    });
    
                    if (empty($found)) {
                        $content .= $this->alert('No client template found for id ' . $templateField['value']);
                        return true;
                    }
                }
                break;
            case 'action_create_website':
                if (empty($loginField['value'])) {
                    $content .= $this->alert('No client login defined');
                    return false;
                }
                break;
            case 'action_create_mail':
                $content .= $this->alert('Creating email accounts is not yet implemented');
                return true;
                break;
            case 'action_update_client_bank':
            case 'action_update_client':
                if (empty($loginField['value'])) {
                    $content .= $this->alert('No client login defined');
                    return true;
                }

                Ispconfig::$Self->withSoap();
                $user = Ispconfig::$Self->GetClientByUser($loginField['value'], true);
                
                $this->postData = array_map_assoc(function ($k, $v) {
                    return ['client_'. $k, $v];
                }, (array)$user);

                break;
            case 'action_locknow_client':
                return false;
                break;
        }

        return true;
    }

    private function onPost($props, &$content)
    {
        if ('POST' !== $_SERVER[ 'REQUEST_METHOD' ]) {
            return true;
        }

        if (empty($_POST)) {
            return true;
        }

        $ok = false;

        // sanitize text input of all $_POST data
        foreach ($_POST as $key => $value) {
            $this->postData[$key] = sanitize_text_field($value);
        }

        // recover the values of read-only fields
        $this->recoverFieldValues($props);

        // connect to ISPConfig REST API
        Ispconfig::$Self->withSoap();

        // validate the users input dependent on the given action
        $valid = $this->validateValues($this->postData['action'], $props, $content);

        if (!$valid) {
            // stop here, but display the controls for retry
            return true;
        }

        if ($props['submission']['action'] == 'continue' && $this->postData['action'] != 'action_check_domain') {
            if (!session_id()) {
                session_start();
            }

            $_SESSION['ispconfig'] = $this->postData;
            wp_redirect($props['submission']['url']);
            exit;
        }

        try {
            switch ($this->postData['action']) {
                case 'action_create_client':
                    $ok = $this->createClient($props, $content);
                    break;
                case 'action_create_website':
                    $ok = $this->createWebsite($props, $content);
                    break;
                case 'action_create_mail':
                    $ok = $this->createMail($props, $content);
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
                case 'action_unlock_client':
                    $ok = $this->unlockClient($props, $content);
                    break;
                case 'action_check_domain':
                    $isAvailable = Ispconfig::$Self->IsDomainAvailable($this->postData['domain_name']);
                    $ok = true;
                    if ($isAvailable) {
                        $content .= $this->success(__('The domain is available', 'wp-ispconfig3'));
                        if (!empty($props['submission']) && $props['submission']['action'] == 'continue' && !empty($props['submission']['url'])) {
                            $content .= $this->info('You will be redirected shortly...');
                            $content .= '<script>jQuery(function() { 
                                document.location.href = "'. sprintf('%s?domain=%s', $props['submission']['url'], $this->postData['domain_name']) .'"
                            })</script>';
                            $ok = false;
                        }
                    } else {
                        $content .= $this->alert(__('The domain is already registered', 'wp-ispconfig3'));
                    }
                    
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

        // clear session
        unset($_SESSION['ispconfig']);

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

    private function validateValues($action, $props, &$content)
    {
        $postData = $this->postData;

        switch ($action) {
            case 'action_create_client':
                if (empty($postData['client_username'])) {
                    $content .= $this->alert('No client username defined');
                    return false;
                }
               
                $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);
                
                if (empty($user)) {
                    // when the user does not exist, continue validating required
                    // field to create a new client
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
                }
                break;
            case 'action_create_website':
                if (empty($postData['website_domain'])) {
                    $content .= $this->alert('No website given');
                    return false;
                }
                break;
            case 'action_update_client':
                if (empty($postData['client_contact_name'])) {
                    $content .= $this->alert('No client contact name defined');
                    return false;
                }

                if (empty($postData['client_language'])) {
                    $content .= $this->alert('No language given');
                    return false;
                }

                if (!preg_match("/^[A-Za-z]{2}$/", $postData['client_language'])) {
                    $content .= $this->alert('No valid ISO 639-1 language code. (E.g. en)');
                    return false;
                }

                break;
            case 'action_update_client_bank':
                if (empty($postData['client_bank_account_owner'])) {
                    $content .= $this->alert('Missing bank account owner');
                    return false;
                }
        
                if (empty($postData['client_bank_name'])) {
                    $content .= $this->alert('Missing bank name');
                    return false;
                }
        
                if (!is_email($postData['client_bank_account_iban'])) {
                    $content .= $this->alert('Missing bank account number');
                    return false;
                }
                break;
            case 'action_check_domain':
                try {
                    $postData['domain_name'] = $this->postData['domain_name'] = Ispconfig::validateDomain($postData['domain_name']);
                } catch (Exception $e) {
                    $content .= $this->alert($e->getMessage());
                    return false;
                }
                break;
        }

        return true;
    }

    private function computeField(&$field)
    {
        switch ($field['computed']) {
            default:
                break;
            case 'session':
                if (!session_id()) {
                    session_start();
                }

                $field['value'] = $_SESSION['ispconfig'][$field['id']];
                $field['readonly'] = true;
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
            case 'get-data':
                if (!empty($_GET[$field['value']])) {
                    $field['value'] = $_GET[$field['value']];
                } else {
                    $field['value'] = '';
                }
                
                $field['computed'] = '';
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

    protected function unlockClient($props, &$content)
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

        $content .= $this->success("Client account " . $user['username'] . ' unlocked');

        return true;
    }

    protected function createWebsite($props, &$content)
    {
        $loginField = array_pop(array_filter($props['fields'], function ($field) {
            return $field['id'] == 'client_username';
        }));

        $postData = $this->postData;

        Ispconfig::$Self->withSoap();

        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user) && $loginField['computed'] == 'session') {
            // when the client info is shipped through session
            // use createClient to create the client first
            $this->postData = $_SESSION['ispconfig'];
            $ok = $this->createClient($props, $content);
            if (!$ok) {
                return false;
            }
            $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);
        }

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }

        $content .= $this->success("Client found (" . $user['client_id'] . ")");

        Ispconfig::$Self->SetClientID($user['client_id']);

        $domain = Ispconfig::$Self->AddWebsite(['domain' => $postData['website_domain']]);

        $content .= $this->success("Website " . $postData['website_domain'] . " successfully created (".$domain.")");

        return true;
    }

    protected function createMail($props, &$content)
    {
        $postData = $this->postData;

        $part = preg_split('/@/', $postData['mail_address']);

        if (count($part) < 2) {
            $this->alert('Invalid mail address');
            return;
        }

        try {
            $domain = Ispconfig::validateDomain($part[1]);
        } catch (Exception $e) {
            $this->alert($e->getMessage());
            return;
        }

        Ispconfig::$Self->withSoap();
        $result = Ispconfig::$Self->GetMailDomainByDomain($domain);

        $client = Ispconfig::$Self->GetClientByGroupID($result['sys_groupid']);

        print_r($client);
    }

    protected function updateClient($props, &$content)
    {
        $postData = $this->postData;

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
        $buttonClass = 'btn btn-default';
        $buttonPrimaryClass = 'btn btn-primary';
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

        $onLoad = $this->onLoad($props, $content);

        // premature end when action is locknow
        if (!$onLoad) {
            return $content;
        }

        $onPost = $this->onPost($props, $content);

        if (!$onPost) {
            return $content;
        }

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
            
            $type = "text";

            if ($field['password']) {
                $type = "password";
            }

            WPISPConfig3::getField($field['id'], __($field['id'], 'wp-ispconfig3'), $type, ['container' => 'div', 'value' => $field['value'], 'input_attr' => $input_attr]);
        }
    
        $result = ob_get_clean();
    
        $content.= "<form method='post'>";
        $content.= $result;

        if (!empty($props['action'])) {
            $button_title = __($props['action'], 'wp-ispconfig3');
            if (!empty($props['submission']['button_title'])) {
                $button_title = $props['submission']['button_title'];
            }
            
            $content .= '<div style="margin-top: 1em">';
            $content .= '<input type="hidden" name="action" value="'.$props['action'] .'" />';

            if (!empty($props['submission']['button_back'])) {
                $back_button_title = "Back";
                if (!empty($props['submission']['button_back_title'])) {
                    $back_button_title = $props['submission']['button_back_title'];
                }
                $content .= '<input type="button" class="'. $buttonClass .'" onclick="javascript:history.back();" value="'. $back_button_title .'" />&nbsp;';
            }
            
            $content .= '<input type="submit" class="'. $buttonPrimaryClass .'" name="submit" value="'. $button_title .'" />';
            $content .= '</div>';
        }
        $content.= "</form>";
        
        return $content;
    }
}

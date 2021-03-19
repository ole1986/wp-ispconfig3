<?php
class BlockException extends Exception
{

}

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

        if (WPISPConfig3::$OPTIONS['user_create_wordpress']) {
            // when user creation is enabled for wordpress, add it accordingly
            $user_id = wp_insert_user([
                'user_login' => $postData['client_username'],
                'user_pass' => $postData['client_password'],
                'user_email' => $postData['client_email']
            ]);
    
            if (is_wp_error($user_id)) {
                $content .= $this->alert('Failed to create wp user: ' . $user_id->get_error_message());
                return false;
            }
        }
        
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
                return false;
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

        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }
        
        $opt = ['username' => strtolower($user['username']), 'locked' => 'y', 'canceled' => 'y'];

        Ispconfig::$Self->UpdClient($opt);

        $content .= $this->success("Client account " . $user['username'] . ' locked');

        return true;
    }

    protected function unlockClient($props, &$content)
    {
        $postData = $this->postData;

        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }

        $opt = ['username' => strtolower($user['username']), 'locked' => 'y', 'canceled' => 'y'];
        Ispconfig::$Self->UpdClient($opt);

        $content .= $this->success("Client account " . $user['username'] . ' unlocked');

        return true;
    }

    protected function createWebsite($props, &$content)
    {
        $postData = $this->postData;

        if (!Ispconfig::$Self->GetClientID()) {
            $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);
            if (empty($user)) {
                throw new BlockException('Client user could not be found');
            }
        }

        $domain = Ispconfig::$Self->AddWebsite(['domain' => $postData['website_domain']]);

        $content .= $this->success("Website " . $postData['website_domain'] . " successfully created (".$domain.")");

        return true;
    }

    protected function createDatabase($props, &$content, $website_id = 0)
    {
        $postData = $this->postData;

        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);
        if (empty($user)) {
            throw new BlockException('Client user could not be found');
        }

        $prefix = preg_replace("/\.-/", "_", $user['username']);

        $dbuserid = Ispconfig::$Self->AddDatabaseUser([
            'database_user' => $prefix . '_' . $postData['database_user'],
            'database_password' => $postData['database_password']
        ]);

        $database = Ispconfig::$Self->AddDatabase([
            'website_id' => $website_id,
            'database_name' => $prefix . '_' . $postData['database_name'],
            'database_user_id' => $dbuserid
        ]);
        
        $content .= $this->success("Database " . $postData['database_name'] . " successfully created");
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
        } catch (BlockException $e) {
            $this->alert($e->getMessage());
            return;
        }

        $result = Ispconfig::$Self->GetMailDomainByDomain($domain);

        $client = Ispconfig::$Self->GetClientByGroupID($result['sys_groupid']);

        print_r($client);
    }

    protected function updateClient($props, &$content)
    {
        $postData = $this->postData;

        $user = Ispconfig::$Self->GetClientByUser($postData['client_username']);

        if (empty($user)) {
            $content .= $this->alert('Client ID not found');
            return false;
        }
        
        Ispconfig::$Self->UpdClient(['username' => $postData['client_username'], 'company_name' => $postData['client_company_name'], 'contact_name' => $postData['client_contact_name']]);

        $content .= $this->success("Client information updated");

        return true;
    }

    protected function updateClientDetails($props, &$content)
    {
        $postData = $this->postData;

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
        
        Ispconfig::$Self->UpdClient($data);

        $content .= $this->success("Client information updated");

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

                // assuming that a cumputed 'session' field
                // is always the 'client_username' from action_create_client
                $field['value'] = $_SESSION['ispconfig']['action_create_client']['postData'][$field['id']];
                $field['readonly'] = true;
                break;
            case 'generate':
                $field['value'] = substr(sha1(uniqid(rand(), true)), 0, 10);
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
    
    /**
     * Used to optionally fill input fields with existing data from ISPConfig3
     */
    private function onLoad($props, &$content)
    {
        if ('POST' === $_SERVER[ 'REQUEST_METHOD' ]) {
            return true;
        }

        $fields = array_filter($props['fields'], function ($field) {
            return isset($field['id']) && $field['id'] == 'client_username';
        });

        $loginField = array_pop($fields);

        $loginFieldIsEmptyAndNonEditable = empty($loginField['value']) && ($loginField['readonly'] || $loginField['hidden']);

        if ($loginFieldIsEmptyAndNonEditable) {
            $content .= $this->alert('No client login provided');
            return false;
        }

        if (isset($loginField) && $loginField['computed'] == 'session' && empty($_SESSION['ispconfig'])) {
            $content .= $this->alert('The session has been expired for the client username');
            return false;
        }

        if (isset($loginField) && $loginField['computed'] == 'wp-user' && !is_user_logged_in()) {
            $content .= $this->alert(__('You need to sign in before continue', 'wp-ispconfig3'));
            return false;
        }


        switch ($props['action']) {
            default:
                break;
            case 'action_create_client':
                $fields = array_filter($props['fields'], function ($field) {
                    return $field['id'] == 'client_template_master';
                });

                $templateField = array_pop($fields);

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
            case 'action_create_mail':
                $content .= $this->alert('Creating email accounts is not yet implemented');
                return true;
                break;
            case 'action_update_client_bank':
            case 'action_update_client':
                // fetch client info from ISPConfig REST API
                // to prefill the post data
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

    private function validatePostData($action, $props, &$content)
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
                    // check if the user already exist in wordpress
                    if (WPISPConfig3::$OPTIONS['user_create_wordpress'] && username_exists($postData['client_username'])) {
                        $content .= $this->alert('The given username already exists in the other system');
                        return false;
                    }
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
            case 'action_create_database':
                if (empty($postData['database_name'])) {
                    $content .= $this->alert('No database name given');
                    return false;
                }
                if (empty($postData['database_password'])) {
                    $content .= $this->alert('No database password given');
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
                    $postData['domain_name'] = $this->postData['domain_name'] = Ispconfig::$Self->validateDomain($postData['domain_name']);
                } catch (BlockException $e) {
                    $content .= $this->alert($e->getMessage());
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Process the SOAP action including those which are privously added into session
     * @return bool true when all actions executed, false when user action required or something faled
     */
    private function processAction($props, &$content, $skipSession = false)
    {
        $ok = false;
        $postData = $this->postData;

        if (!$skipSession && isset($_SESSION['ispconfig'])) {
            $pending = array_filter($_SESSION['ispconfig'], function ($v, $k) {
                return !isset($v['_processed']);
            }, ARRAY_FILTER_USE_BOTH);

            uksort($pending, function ($a, $b) {
                // always make sure client creation is take first
                if ($a == 'action_create_client') {
                    return -1;
                }
                return strcasecmp($a, $b);
            });

            foreach ($pending as $k => $v) {
                $this->postData = $v['postData'];

                // mark current action as processed before
                $_SESSION['ispconfig'][$k]['_processed'] = true;

                // recursive function call
                if (!$this->processAction($v['props'], $content, true)) {
                    // but abort when an action returned false
                    throw new BlockException("Process aborted");
                }
            }

            $this->postData = $postData;
        }
    
        switch ($this->postData['action']) {
            case 'action_create_client':
                $ok = $this->createClient($props, $content);
                break;
            case 'action_create_website':
                $isAvailable = Ispconfig::$Self->IsDomainAvailable($this->postData['website_domain']);
                if ($isAvailable) {
                    $ok = $this->createWebsite($props, $content);
                } else {
                    $content .= $this->alert(__('The domain is already registered', 'wp-ispconfig3'));
                }
                break;
            case 'action_create_database':
                $ok = $this->createDatabase($props, $content);
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
                    }
                } else {
                    $content .= $this->alert(__('The domain is already registered', 'wp-ispconfig3'));
                }
                break;
            default:
                throw new BlockException("No action defined for " . $this->postData['action']);
        }
        
        return $ok;
    }

    private function onPost($props, &$content)
    {
        if ('POST' !== $_SERVER[ 'REQUEST_METHOD' ]) {
            return false;
        }

        if (empty($_POST)) {
            return false;
        }

        // sanitize text input of all $_POST data
        foreach ($_POST as $key => $value) {
            $this->postData[$key] = sanitize_text_field($value);
        }

        // recover the values of read-only fields
        $this->recoverFieldValues($props);

        // connect to ISPConfig REST API
        Ispconfig::$Self->withSoap();

        // validate the users input dependent on the given action
        $valid = $this->validatePostData($this->postData['action'], $props, $content);

        if (!$valid) {
            // stop here, but keep session intact
            return false;
        }

        if (isset($props['submission']) && $props['submission']['action'] == 'continue' && $this->postData['action'] != 'action_check_domain') {
            if (!session_id()) {
                session_start();
            }

            $_SESSION['ispconfig'][$this->postData['action']] = [
                'props' => $props,
                'postData' => $this->postData
            ];

            wp_redirect($props['submission']['url']);
            exit;
        }

        $stack[$this->postData['action']] = [
            'props' => $props,
            'postData' => $this->postData
        ];

        if (isset($_SESSION['ispconfig'])) {
            $stack += $_SESSION['ispconfig'];
        }

        return $this->processAction($props, $content);
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

        try {
            $onLoad = $this->onLoad($props, $content);

            // abort the fields output when onLoad returns false
            if (!$onLoad) {
                // this may or may not contain previously added content
                // E.g. error messages
                return $content;
            }
    
            if ($this->onPost($props, $content)) {
                // submit email to address when post succeeded
                // and setting matches the current post action
                if (in_array($this->postData['action'], (array)WPISPConfig3::$OPTIONS['confirm_actions'])) {
                    $email = $this->postData['client_email'];
                    if (empty($email) && !empty($_SESSION['ispconfig']['action_create_client'])) {
                        // try from session
                        $email = $_SESSION['ispconfig']['action_create_client']['postData']['client_email'];
                    }

                    $options = $this->postData;

                    if (!empty($_SESSION['ispconfig'])) {
                        $sessionPostData = array_column($_SESSION['ispconfig'], 'postData');
                        $sessionPostData = array_merge(...$sessionPostData);
                        $options += $sessionPostData;
                    }

                    if (!empty($email)) {
                        $sent = Ispconfig::$Self->SendConfirmation($email, $options);
                        if ($sent) {
                            $content .= $this->info('Confirmation email sent to' . $email);
                        }
                    } else {
                        $content .= $this->info('No email has been submitted due to missing email address');
                    }
                }
                
                // clear session when post call was succesfully
                if (isset($_SESSION['ispconfig'])) {
                    unset($_SESSION['ispconfig']);
                }
                // only display alerts or notifications (if available) and skip the fields
                return $content;
            }
        } catch (BlockException $e) {
            unset($_SESSION['ispconfig']);
            $content .= $this->alert($e->getMessage());
            return $content;
        } catch (SoapFault $e) {
            unset($_SESSION['ispconfig']);
            $content .= $this->alert($e->getMessage());
            return $content;
        }

        $content.= "<form method='post'>";
        
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
    
        $content.= ob_get_clean();

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

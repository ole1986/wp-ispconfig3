<?php
defined('ABSPATH') || exit;

/**
 * Used to cancel and lock a client account by using shortcode only.
 * By using the below example shortcode the client account will immediatly disabled and locked on page is requested
 *
 * Example: [ispconfig class=IspconfigRegisterCancelled username=testclient]
 */
class IspconfigRegisterCancelled extends Ispconfig
{
    public function Display($attr)
    {
        $this->onPost($attr);
    }

    private function onPost($attr)
    {
        try {
            $client = $this->withSoap()->GetClientByUser($attr['username']);
            if (!empty($client)) {

                $client_id = $client['client_id'];

                $opt = ['username' => strtolower($attr['username']),
                    'locked' => 'y',
                    'canceled' => 'y',
                ];

                $this->UpdClient($opt, $client_id);
            }
            $this->closeSoap();
        } catch (SoapFault $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">SOAP Error: ' . $e->getMessage() . '</div>';
        } catch (Exception $e) {
            echo '<div class="ispconfig-msg ispconfig-msg-error">Exception: ' . $e->getMessage() . "</div>";
        }
    }
}

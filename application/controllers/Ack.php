<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Acknowledging a critical alert, from the link in the message.
 *
 * Reached as /ack/<token>. One path segment, because the link has to fit in an
 * SMS next to the message and routes.php is a vendor file an update would
 * overwrite - .htaccess already rewrites everything to index.php, so this
 * resolves with no routing entry at all.
 *
 * THE GET DOES NOT ACKNOWLEDGE. SMS, WhatsApp and Telegram all fetch links to
 * build previews, so a GET that acknowledged would mean every critical alert
 * arrived pre-acknowledged and the escalation ladder would never fire once.
 * The GET renders a page with a button; the POST is the acknowledgement.
 *
 * No login. The recipient is a phone number, not a session - asking a manager to
 * sign in on a phone at 2am to silence an alert is how alerts get ignored. The
 * token is the authorisation: short-lived, single-purpose, and able to do
 * nothing except settle the one alert it names.
 */
class Ack extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->helper('notify');
    }

    /**
     * CodeIgniter reads /ack/<token> as "call the method named <token>", which
     * does not exist, so the link 404s. _remap() catches every method name and
     * routes it here instead, which keeps the URL one segment long - the whole
     * point, since every character comes out of the SMS - without an entry in
     * routes.php, a vendor file an update would overwrite.
     *
     * A token can never collide with 'confirm': tokens are exactly 10
     * characters and 'confirm' is 7.
     */
    public function _remap($method, $params = array()) {
        if ($method === 'confirm') { return $this->confirm(); }
        if ($method === 'index')   { return $this->index(isset($params[0]) ? $params[0] : ''); }
        return $this->index($method);
    }

    /** GET /ack/<token> - shows the state and, when open, the button. */
    public function index($token = '') {
        $st = irNotifyAckState($token);
        $this->load->view('notification/ack', array(
            'state' => $st['state'],
            'row'   => $st['row'],
            'token' => (string) $token,
        ));
    }

    /**
     * POST /ack/confirm - the acknowledgement itself.
     *
     * Refuses anything that is not a POST, which is the whole defence against a
     * link preview: prefetchers issue GET and never POST.
     */
    public function confirm() {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            show_error(lang('notify_ack_post_only'), 405);
            return;
        }
        $token = (string) $this->input->post('token');

        /* A user id only if somebody happens to be signed in on this device. The
           usual case is nobody, and that is fine - acknowledged_ip is recorded
           instead so there is always something to go back to. */
        $user_id = $this->session->userdata('user_id');

        $st = irNotifyAckConfirm($token, $user_id, (string) $this->input->ip_address());

        $this->load->view('notification/ack', array(
            'state'     => $st['state'],
            'row'       => $st['row'],
            'token'     => $token,
            'just_done' => ($st['state'] === 'already'),
        ));
    }
}

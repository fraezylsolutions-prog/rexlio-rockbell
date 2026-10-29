<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Notifications - the queue side (N2, 2026-10-29).
 *
 * Everything an event point needs is irNotifyEnqueue(): it writes ONE row and
 * returns. It never sends, never opens a socket, never waits on a provider.
 * That is the whole point of the design - Stage 0 found the till already
 * freezing on a synchronous XHR wrapped around an SMS call with no timeout on
 * it, and this exists so that can never happen again.
 *
 * The worker (controllers/Notification_worker.php) is the only thing that
 * sends, it runs from cron, and it is the only file that talks to a provider.
 *
 * CHANNELS ARE DRIVERS. One function per channel, named irNotifyDriver<Name>,
 * taking the outbox row and returning array('ok' => bool, 'error' => string).
 * Adding a channel means adding one function and one settings form; nothing in
 * the queue, the worker or the hooks changes. N2 ships the email driver only.
 */

/* ------------------------------------------------------------------ switches */

if (!function_exists('irNotifyOn')) {
    /**
     * Is the whole module on? Every hook calls this first, so it must be cheap:
     * irModuleEnabled() reads tbl_modules once per request and caches.
     *
     * While this is FALSE nothing is queued and nothing is sent, whatever the
     * individual channels say. That is the master switch doing its job.
     */
    function irNotifyOn() {
        return irModuleEnabled('notifications');
    }
}

if (!function_exists('irNotifyChannelOn')) {
    /** Is this one channel on? The master switch wins over all of them. */
    function irNotifyChannelOn($channel) {
        if (!irNotifyOn()) { return FALSE; }
        return irModuleEnabled('notify_' . $channel);
    }
}

/* ------------------------------------------------------------------ settings */

if (!function_exists('irNotifySettings')) {
    /**
     * Module settings, kept as JSON on the master tbl_modules row.
     *
     * test_mode starts ON and that is deliberate: a fresh install that someone
     * switches on should talk to the person who switched it on, not to the
     * venue's customers. Turning it off is a conscious act.
     *
     * @param bool $reset drop the per-request cache after a write
     */
    function irNotifySettings($reset = FALSE) {
        static $cache = NULL;
        if ($cache !== NULL && !$reset) { return $cache; }
        $defaults = array(
            'test_mode'         => 1,
            'test_recipient'    => '',
            'max_attempts'      => 3,
            'backoff_minutes'   => array(1, 5, 15),   /* one entry per retry */
            'hold_expiry_hours' => 48,                /* a held message is dropped, never sent late */
            'batch_size'        => 50,                /* per worker run */
        );
        $rows = irModuleRows();
        $stored = array();
        if (isset($rows['notifications']) && !empty($rows['notifications']->settings)) {
            $decoded = json_decode($rows['notifications']->settings, TRUE);
            if (is_array($decoded)) { $stored = $decoded; }
        }
        $cache = array_merge($defaults, $stored);
        return $cache;
    }
}

if (!function_exists('irNotifySetting')) {
    function irNotifySetting($key, $fallback = NULL) {
        $s = irNotifySettings();
        return array_key_exists($key, $s) ? $s[$key] : $fallback;
    }
}

/* --------------------------------------------------------------------- queue */

if (!function_exists('irNotifyEnqueue')) {
    /**
     * Put one message in the outbox. This is ALL an event point ever does.
     *
     * Returns the row id, or 0 when nothing was queued (module off, channel off,
     * or no recipient). Returning 0 rather than throwing is deliberate: an event
     * point must never be able to break the thing that triggered it. Completing
     * a sale does not fail because a notification could not be queued.
     *
     * @param array $m event_key, channel, intended_recipient, body, and
     *                 optionally subject, payload, is_critical, rule_id,
     *                 outlet_id, company_id
     */
    function irNotifyEnqueue($m) {
        if (!irNotifyOn()) { return 0; }
        $channel = isset($m['channel']) ? $m['channel'] : '';
        if ($channel === '' || !irNotifyChannelOn($channel)) { return 0; }
        $to = isset($m['intended_recipient']) ? trim((string) $m['intended_recipient']) : '';
        if ($to === '') { return 0; }

        $s = irNotifySettings();
        /* In test mode every message goes to one address, and the row keeps BOTH
           so a test proves the routing as well as the sending. */
        $test = !empty($s['test_mode']);
        $actual = $test ? trim((string) $s['test_recipient']) : $to;
        if ($test && $actual === '') { return 0; }   /* test mode with nowhere to send */

        $CI = &get_instance();

        /* Queue-at-most-once. A caller that can be retried passes a dedupe_key;
           everybody else passes nothing and is unaffected, because MySQL allows
           any number of NULLs in a unique index.

           On a key that is already present this returns the EXISTING row id, not
           0. That distinction matters: push_online() treats a 0 as "could not be
           queued" and sends the SMS inline instead, so returning 0 here would
           text the customer exactly the duplicate this is meant to prevent. */
        $dedupe = isset($m['dedupe_key']) ? substr(trim((string) $m['dedupe_key']), 0, 190) : '';
        if ($dedupe !== '') {
            $seen = @$CI->db->query(
                "SELECT id FROM tbl_notification_outbox WHERE dedupe_key = "
                . $CI->db->escape($dedupe) . " LIMIT 1");
            if ($seen && $seen->num_rows()) { return (int) $seen->row()->id; }
        }

        $data = array(
            'event_key'          => substr((string) $m['event_key'], 0, 60),
            'channel'            => substr($channel, 0, 20),
            'rule_id'            => isset($m['rule_id']) ? (int) $m['rule_id'] : NULL,
            'intended_recipient' => substr($to, 0, 190),
            'actual_recipient'   => substr($actual, 0, 190),
            'subject'            => isset($m['subject']) ? substr((string) $m['subject'], 0, 255) : NULL,
            'body'               => (string) $m['body'],
            'payload'            => isset($m['payload'])
                                    ? (is_string($m['payload']) ? $m['payload'] : json_encode($m['payload']))
                                    : NULL,
            'status'             => 'pending',
            'is_critical'        => !empty($m['is_critical']) ? 1 : 0,
            'test_mode'          => $test ? 1 : 0,
            'outlet_id'          => isset($m['outlet_id']) ? (int) $m['outlet_id'] : NULL,
            'company_id'         => isset($m['company_id']) ? (int) $m['company_id'] : 1,
            'created_at'         => date('Y-m-d H:i:s'),
        );
        if ($dedupe !== '') { $data['dedupe_key'] = $dedupe; }

        /* Suppressed on purpose: a missing table (code deployed before the
           migration) must not fatal the sale that triggered it. */
        if (!@$CI->db->insert('tbl_notification_outbox', $data)) {
            /* Two tills retrying the same completion at the same moment both get
               past the SELECT above and one loses the unique index. That is the
               constraint doing its job, not a failure: look the winner up and
               report it as queued. Also covers the column not existing yet,
               where the row is simply queued without the key. */
            if ($dedupe !== '') {
                $seen = @$CI->db->query(
                    "SELECT id FROM tbl_notification_outbox WHERE dedupe_key = "
                    . $CI->db->escape($dedupe) . " LIMIT 1");
                if ($seen && $seen->num_rows()) { return (int) $seen->row()->id; }
                unset($data['dedupe_key']);
                if (@$CI->db->insert('tbl_notification_outbox', $data)) {
                    return (int) $CI->db->insert_id();
                }
            }
            return 0;
        }
        return (int) $CI->db->insert_id();
    }
}

/* ------------------------------------------------------------------- logging */

if (!function_exists('irNotifyLog')) {
    /**
     * The worker's own log. It needs one: ENVIRONMENT is 'production', which
     * hides errors, and a cron job with nowhere to complain fails silently -
     * which is the one failure mode a notification system must not have.
     *
     * Its own directory so it does not interleave with CodeIgniter's logs.
     */
    function irNotifyLog($line) {
        $dir = APPPATH . 'logs/notifications';
        if (!is_dir($dir)) { @mkdir($dir, 0755, TRUE); }
        $file = $dir . '/worker-' . date('Y-m-d') . '.log';
        @file_put_contents($file, date('Y-m-d H:i:s') . '  ' . $line . "\n", FILE_APPEND);
    }
}

/* ------------------------------------------------------------------- drivers */

if (!function_exists('irNotifyDriverEmail')) {
    /**
     * Email, through the SMTP settings the product already has.
     *
     * Two things here are not obvious and both were found by testing:
     *
     * sendEmailOnly() DOES return a boolean - false when PHPMailer refuses - so
     * the result is used. An earlier version of this driver ignored it and
     * reported every message as sent.
     *
     * And when SMTP is not enabled, sendEmailOnly() does not attempt anything
     * and still returns true (my_helper.php, the final return of the function).
     * For most of the product that is harmless. For a notification system it is
     * the worst possible answer: a queue full of rows marked sent that nobody
     * ever received. So this checks enable_status itself and calls that a
     * failure, with an error that says what to do about it.
     *
     * @return array ok, error
     */
    function irNotifyDriverEmail($row) {
        $to = trim((string) $row->actual_recipient);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => FALSE, 'error' => 'not a valid email address: ' . $to);
        }

        $company = getMainCompany();
        $smtp = (isset($company->email_settings) && $company->email_settings)
              ? json_decode($company->email_settings) : NULL;
        if (!$smtp || !isset($smtp->enable_status) || (int) $smtp->enable_status !== 1) {
            return array('ok' => FALSE,
                'error' => 'SMTP is not enabled - set it up under Settings > Email, or nothing is actually delivered');
        }
        if (empty($smtp->host_name) || empty($smtp->user_name)) {
            return array('ok' => FALSE, 'error' => 'SMTP is enabled but the host or user name is blank');
        }

        $subject = ($row->subject !== NULL && $row->subject !== '') ? $row->subject : 'Rexlio notification';
        try {
            $sent = sendEmailOnly($row->body, $to, '', '', $subject);
            if ($sent === FALSE) {
                return array('ok' => FALSE, 'error' => 'the mail server refused the message');
            }
            return array('ok' => TRUE, 'error' => '');
        } catch (Throwable $e) {
            return array('ok' => FALSE, 'error' => substr($e->getMessage(), 0, 480));
        }
    }
}

if (!function_exists('irNotifyDriverSms')) {
    /**
     * SMS. The module owns which provider is used - deliberately NOT the two
     * legacy tbl_companies columns, which contradict each other
     * (sms_enable_status is a dropdown where 5 means Nexmo and which nothing
     * reads at send time; sms_service_provider is a different dropdown with
     * different numbering and is what actually decided). Reading either would
     * inherit that, and an alert that silently went nowhere because of a
     * stale legacy value is the exact failure this module exists to prevent.
     *
     * provider 'legacy' is offered for an installation that already has a
     * working Twilio or mShastra setup and does not want to re-enter it.
     *
     * @return array ok, error
     */
    function irNotifyDriverSms($row) {
        $to = trim((string) $row->actual_recipient);
        if ($to === '') { return array('ok' => FALSE, 'error' => 'no number'); }
        /* refuse anything that is not a number BEFORE a gateway sees it - an
           address or a name reaching a provider is a message that gets charged */
        if (!preg_match('/^[0-9+][0-9 ()-]{5,}$/', $to)) {
            return array('ok' => FALSE, 'error' => 'not a phone number: ' . $to);
        }

        $cfg = irNotifyChannelSettings('sms');
        $provider = isset($cfg['provider']) ? $cfg['provider'] : '';

        try {
            if ($provider === 'termii') {
                return irNotifySendTermii($to, $row->body, $cfg);
            }
            if ($provider === 'legacy') {
                $sent = smsSendOnly($row->body, $to);
                return ($sent === FALSE)
                    ? array('ok' => FALSE, 'error' => 'the legacy provider did not accept it - see the sms lines in this log')
                    : array('ok' => TRUE, 'error' => '');
            }
            return array('ok' => FALSE,
                'error' => 'no SMS provider chosen in Settings > Notifications > SMS');
        } catch (Throwable $e) {
            return array('ok' => FALSE, 'error' => substr($e->getMessage(), 0, 480));
        }
    }
}

if (!function_exists('irNotifyDriver')) {
    /**
     * The driver for a channel, or NULL when that channel has none yet.
     * A channel with no driver leaves its messages pending rather than failing
     * them: the message is not wrong, the software is simply not finished.
     */
    function irNotifyDriver($channel) {
        $fn = 'irNotifyDriver' . ucfirst(strtolower($channel));
        return function_exists($fn) ? $fn : NULL;
    }
}

/* ------------------------------------------------------- firing an event -- */

if (!function_exists('irNotifyEvents')) {
    /**
     * Every event the system can raise, and how to describe it.
     *
     * 'short' is written for SMS and WhatsApp and must stay inside one 160
     * character page: plain ASCII, "N" rather than the naira sign, no emoji.
     * A pound of cleverness here costs real money per message.
     *
     * 'long' is for email and in-app, where there is room to be useful.
     * Both take the event's context through irNotifyFill().
     */
    function irNotifyEvents() {
        return array(
            'order_placed' => array(
                'label' => 'Order placed',
                'subject' => 'Order {sale_no} placed',
                'short' => 'Rexlio: order {sale_no} placed at {outlet}, N{total}, by {user}.',
                'long' => "Order {sale_no} has been placed.\n\nOutlet: {outlet}\nTable: {table}\nTotal: N{total}\nTaken by: {user}\nTime: {when}\n",
            ),
            'order_cancelled' => array(
                'label' => 'Order cancelled',
                'subject' => 'Order {sale_no} CANCELLED',
                'short' => 'Rexlio: order {sale_no} CANCELLED at {outlet}, N{total}, by {user}. Reason: {reason}',
                'long' => "Order {sale_no} has been cancelled.\n\nOutlet: {outlet}\nTotal: N{total}\nCancelled by: {user}\nReason: {reason}\nTime: {when}\n",
            ),
            'order_deleted' => array(
                'label' => 'Order deleted',
                'subject' => 'Order {sale_no} deleted',
                'short' => 'Rexlio: order {sale_no} DELETED at {outlet}, N{total}, by {user}.',
                'long' => "Order {sale_no} has been deleted.\n\nOutlet: {outlet}\nTotal: N{total}\nDeleted by: {user}\nTime: {when}\n",
            ),
            'register_closed' => array(
                'label' => 'Register closed',
                'subject' => 'Register closed at {outlet}',
                'short' => 'Rexlio: register closed at {outlet} by {user}. Takings N{total}.',
                'long' => "A register has been closed.\n\nOutlet: {outlet}\nCounter: {counter}\nClosed by: {user}\nTakings: N{total}\nTime: {when}\n",
            ),
            'register_force_closed' => array(
                'label' => 'Register FORCE-closed',
                'subject' => 'Register FORCE-CLOSED at {outlet}',
                'short' => 'Rexlio: register FORCE-CLOSED at {outlet} by {user}. Reason: {reason}',
                'long' => "A register was force-closed on somebody else's behalf.\n\nOutlet: {outlet}\nCounter: {counter}\nForced by: {user}\nReason: {reason}\nTime: {when}\n\nThis is worth checking: a force-close is an override, not a normal close.\n",
            ),
            'hotel_check_in' => array(
                'label' => 'Hotel check-in',
                'subject' => 'Check-in: room {room}',
                'short' => 'Rexlio: check-in room {room}, {guest}, {outlet}.',
                'long' => "A guest has checked in.\n\nRoom: {room}\nGuest: {guest}\nOutlet: {outlet}\nBy: {user}\nTime: {when}\n",
            ),
            'hotel_check_out' => array(
                'label' => 'Hotel check-out',
                'subject' => 'Check-out: room {room}',
                'short' => 'Rexlio: check-out room {room}, {guest}, {outlet}.',
                'long' => "A guest has checked out.\n\nRoom: {room}\nGuest: {guest}\nOutlet: {outlet}\nBy: {user}\nTime: {when}\n",
            ),
            'hotel_room_dirty' => array(
                'label' => 'Room needs cleaning',
                'subject' => 'Room {room} needs cleaning',
                'short' => 'Rexlio: room {room} needs cleaning at {outlet}.',
                'long' => "A room has been marked as needing attention.\n\nRoom: {room}\nStatus: {status}\nOutlet: {outlet}\nBy: {user}\nTime: {when}\n",
            ),
        );
    }
}

if (!function_exists('irNotifyFill')) {
    /** Replace {placeholders} from the event context. Anything absent reads "-". */
    function irNotifyFill($template, $ctx) {
        $out = $template;
        if (preg_match_all('/\{([a-z_]+)\}/', $template, $m)) {
            foreach ($m[1] as $key) {
                $v = isset($ctx[$key]) && $ctx[$key] !== '' && $ctx[$key] !== NULL ? $ctx[$key] : '-';
                $out = str_replace('{' . $key . '}', (string) $v, $out);
            }
        }
        return $out;
    }
}

if (!function_exists('irNotifyRecipientsFor')) {
    /**
     * Turn a rule's recipient rows into actual addresses for one channel.
     *
     * Resolved at send time rather than stored, so a rule never ends up pointing
     * at somebody who has left. A role with nobody in it yields nothing, which
     * is correct and silent - the alternative is a rule that looks configured
     * and quietly reaches no one.
     */
    function irNotifyRecipientsFor($rule_id, $channel) {
        $CI = &get_instance();
        $field = in_array($channel, array('sms', 'whatsapp', 'voice'), TRUE) ? 'phone' : 'email_address';
        $out = array();

        $rows = @$CI->db->query("SELECT recipient_type, recipient_value FROM tbl_notification_recipients
                                  WHERE rule_id = " . (int) $rule_id . " AND del_status = 'Live'
                                  ORDER BY escalation_order ASC, id ASC");
        if (!$rows) { return $out; }

        foreach ($rows->result() as $r) {
            if ($r->recipient_type === 'fixed') {
                $v = trim((string) $r->recipient_value);
                if ($v !== '') { $out[] = $v; }
                continue;
            }
            $where = ($r->recipient_type === 'role')
                   ? 'role_id = ' . (int) $r->recipient_value
                   : 'id = ' . (int) $r->recipient_value;
            $q = @$CI->db->query("SELECT `$field` AS v FROM tbl_users
                                   WHERE $where AND del_status = 'Live' AND active_status = 'Active'");
            if (!$q) { continue; }
            foreach ($q->result() as $u) {
                $v = trim((string) $u->v);
                if ($v !== '') { $out[] = $v; }
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('irNotifyFire')) {
    /**
     * Raise an event. THIS IS WHAT EVERY HOOK CALLS, and all it may do.
     *
     * It must be cheap and it must never throw: it is called from the middle of
     * saving a sale. The first line is the master switch, which irModuleEnabled
     * caches per request, so with the module off a hook costs one array lookup.
     * Everything after that is wrapped, because a notification problem must not
     * become a failed sale.
     *
     * @param string $event_key one of irNotifyEvents()
     * @param array  $ctx       placeholders for the templates
     * @return int how many messages were queued
     */
    function irNotifyFire($event_key, $ctx = array()) {
        if (!irNotifyOn()) { return 0; }

        try {
            $CI = &get_instance();
            $events = irNotifyEvents();
            if (!isset($events[$event_key])) { return 0; }
            $def = $events[$event_key];

            $outlet = isset($ctx['outlet_id']) ? (int) $ctx['outlet_id'] : 0;
            /* outlet_id 0 on a rule means every outlet */
            $rules = @$CI->db->query(
                "SELECT id, channel, is_critical FROM tbl_notification_rules
                  WHERE event_key = " . $CI->db->escape($event_key) . "
                    AND is_enabled = 1 AND del_status = 'Live'
                    AND (outlet_id = 0 OR outlet_id = " . $outlet . ")");
            if (!$rules || !$rules->num_rows()) { return 0; }

            if (!isset($ctx['when'])) { $ctx['when'] = date('Y-m-d H:i'); }
            $queued = 0;

            foreach ($rules->result() as $rule) {
                if (!irNotifyChannelOn($rule->channel)) { continue; }
                $short = in_array($rule->channel, array('sms', 'whatsapp', 'voice'), TRUE);
                $body = irNotifyFill($short ? $def['short'] : $def['long'], $ctx);
                $subject = irNotifyFill($def['subject'], $ctx);

                foreach (irNotifyRecipientsFor($rule->id, $rule->channel) as $to) {
                    $queued += irNotifyEnqueue(array(
                        'event_key' => $event_key,
                        'channel' => $rule->channel,
                        'rule_id' => $rule->id,
                        'intended_recipient' => $to,
                        'subject' => $subject,
                        'body' => $body,
                        'payload' => $ctx,
                        'is_critical' => (int) $rule->is_critical,
                        'outlet_id' => $outlet ?: NULL,
                    )) ? 1 : 0;
                }
            }
            return $queued;
        } catch (Throwable $e) {
            /* Swallowed on purpose. A sale must not fail because a notification
               could not be queued. The worker's log is where this surfaces. */
            @irNotifyLog('FIRE FAILED ' . $event_key . ': ' . $e->getMessage());
            return 0;
        }
    }
}

/* ------------------------------------------------- per-channel settings -- */

if (!function_exists('irNotifyChannelSettings')) {
    /**
     * Settings for one channel, kept as JSON on that channel's tbl_modules row.
     *
     * Per channel rather than one blob on the master row, so a provider key for
     * SMS is not sitting in the same field as the Telegram token, and so the
     * "view provider keys" permission can one day be applied per channel.
     */
    function irNotifyChannelSettings($channel) {
        static $cache = array();
        if (isset($cache[$channel])) { return $cache[$channel]; }
        $rows = irModuleRows();
        $key = 'notify_' . $channel;
        $out = array();
        if (isset($rows[$key]) && !empty($rows[$key]->settings)) {
            $decoded = json_decode($rows[$key]->settings, TRUE);
            if (is_array($decoded)) { $out = $decoded; }
        }
        $cache[$channel] = $out;
        return $out;
    }
}

if (!function_exists('irNotifyMaskSecret')) {
    /**
     * What a stored key looks like on screen to somebody without the
     * view_credentials permission. Enough to recognise which key is in there,
     * never enough to use it.
     */
    function irNotifyMaskSecret($value) {
        $v = (string) $value;
        if ($v === '') { return ''; }
        if (strlen($v) <= 8) { return str_repeat('*', strlen($v)); }
        return substr($v, 0, 4) . str_repeat('*', 8) . substr($v, -4);
    }
}

if (!function_exists('irNotifyPhone')) {
    /**
     * A Nigerian number in the form a gateway expects: international digits,
     * no plus, no spaces. 08031234567 becomes 2348031234567.
     *
     * Termii rejects the local 0-prefixed form, and a rejected message is still
     * a round trip and sometimes still a charge, so this is worth getting right
     * before the request leaves.
     *
     * @param string $raw
     * @param string $cc default country code, digits only
     * @return string normalised number, or '' when it cannot be made sense of
     */
    function irNotifyPhone($raw, $cc = '234') {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') { return ''; }
        $cc = preg_replace('/\D+/', '', (string) $cc);
        if ($cc === '') { $cc = '234'; }

        /* already international */
        if (strpos($d, $cc) === 0 && strlen($d) >= strlen($cc) + 9) { return $d; }
        /* 00234... */
        if (strpos($d, '00' . $cc) === 0) { return substr($d, 2); }
        /* local 0-prefixed: 0803... -> 234803... */
        if (strlen($d) === 11 && $d[0] === '0') { return $cc . substr($d, 1); }
        /* bare 10 digits: 803... -> 234803... */
        if (strlen($d) === 10 && $d[0] !== '0') { return $cc . $d; }
        /* anything else is returned as-is only when it is plausibly a full
           international number; otherwise refused rather than guessed at */
        return (strlen($d) >= 11 && strlen($d) <= 15) ? $d : '';
    }
}

/* ----------------------------------------------------------- SMS: Termii -- */

if (!function_exists('irNotifySendTermii')) {
    /**
     * Send one message through Termii.
     *
     * Chosen for Nigeria: local pricing, local deliverability, and a DND route
     * that actually reaches numbers on the do-not-disturb list - which most
     * Nigerian mobile numbers are, and which is why a "working" gateway can
     * still deliver nothing.
     *
     * Settings: api_key, sender_id, route (dnd|generic), country_code, api_base.
     * api_base exists so a test can point this at a local stand-in; it defaults
     * to Termii and is not something an installation needs to set.
     *
     * @return array ok, error
     */
    function irNotifySendTermii($to, $message, $cfg) {
        $key = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        $from = isset($cfg['sender_id']) ? trim((string) $cfg['sender_id']) : '';
        if ($key === '') { return array('ok' => FALSE, 'error' => 'no Termii API key saved'); }
        if ($from === '') { return array('ok' => FALSE, 'error' => 'no Termii sender ID saved'); }

        $number = irNotifyPhone($to, isset($cfg['country_code']) ? $cfg['country_code'] : '234');
        if ($number === '') { return array('ok' => FALSE, 'error' => 'cannot make sense of the number: ' . $to); }

        $base = !empty($cfg['api_base']) ? rtrim($cfg['api_base'], '/') : 'https://api.ng.termii.com';
        /* 'dnd' reaches numbers on the do-not-disturb list; 'generic' does not.
           Alerts are worthless if they silently stop at DND, so that is default. */
        $route = (isset($cfg['route']) && $cfg['route'] === 'generic') ? 'generic' : 'dnd';

        $payload = json_encode(array(
            'to'      => $number,
            'from'    => $from,
            'sms'     => (string) $message,
            'type'    => 'plain',
            'channel' => $route,
            'api_key' => $key,
        ));

        $ch = curl_init($base . '/api/sms/send');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POST           => TRUE,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            /* bounded, for the same reason everything else here is */
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ));
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === FALSE || $err !== '') {
            return array('ok' => FALSE, 'error' => 'Termii unreachable: ' . ($err !== '' ? $err : 'no response'));
        }
        $json = json_decode($body, TRUE);

        /* Termii answers 200 with a message_id on success. It also answers 200
           for some refusals, so the body decides, not the status code. */
        if ($code >= 200 && $code < 300 && is_array($json) && !empty($json['message_id'])) {
            return array('ok' => TRUE, 'error' => '', 'reference' => $json['message_id']);
        }
        $why = '';
        if (is_array($json)) {
            foreach (array('message', 'error', 'detail') as $k) {
                if (!empty($json[$k]) && is_string($json[$k])) { $why = $json[$k]; break; }
            }
        }
        if ($why === '') { $why = 'HTTP ' . $code . ' ' . substr((string) $body, 0, 160); }
        return array('ok' => FALSE, 'error' => 'Termii refused: ' . $why);
    }
}

/* ------------------------------------------- saving from the settings screen */

if (!function_exists('irNotifyKeepSecret')) {
    /**
     * Decide what a provider key should become after a form submit.
     *
     * This is the rule that protects a working installation from its own
     * settings screen. Anyone without the view_credentials permission sees the
     * key masked, so the masked string is what their browser posts back. If that
     * were saved, the real key would be destroyed by somebody who only came to
     * change the sender ID - and the next alert would fail with "Invalid API
     * key" for no visible reason, hours later, with nobody connecting the two.
     *
     * So: a blank box, an unchanged box, or the mask itself all mean LEAVE IT
     * ALONE. Removing a key is a deliberate separate act, never a side effect.
     *
     * Named and kept here rather than inline in the controller because the same
     * rule has to hold for the WhatsApp and Telegram keys, and because this is
     * exactly the kind of decision that deserves a test of its own.
     *
     * @param string $posted   what came back from the form
     * @param string $existing what is stored now
     * @param bool   $clear    the "delete the saved key" tick-box
     * @return string what to store
     */
    function irNotifyKeepSecret($posted, $existing, $clear = FALSE) {
        $existing = (string) $existing;
        if ($clear) { return ''; }
        $posted = (string) $posted;
        if (trim($posted) === '') { return $existing; }
        if ($posted === irNotifyMaskSecret($existing)) { return $existing; }
        return trim($posted);
    }
}

if (!function_exists('irNotifyParseRecipients')) {
    /**
     * Turn one line of a rule's recipient box into rows for the database.
     *
     * "role:2, user:11, boss@example.com, 08031234567" becomes four entries, in
     * the order they were typed - that order IS the escalation order, which is
     * why the box is a list rather than a set of pickers.
     *
     * A role or user is stored as a reference and resolved when the message is
     * sent (irNotifyRecipientsFor), so a rule never ends up pointing at somebody
     * who has left. Anything else is kept verbatim as a 'fixed' address: the
     * driver, not this function, decides whether it is a usable email or number,
     * because that depends on the channel it ends up on.
     *
     * @param string $raw
     * @return array list of array(recipient_type, recipient_value)
     */
    function irNotifyParseRecipients($raw) {
        $out = array();
        foreach (preg_split('/[,;\r\n]+/', (string) $raw) as $one) {
            $one = trim($one);
            if ($one === '') { continue; }
            if (preg_match('/^role\s*:\s*(\d+)$/i', $one, $m)) {
                $out[] = array('recipient_type' => 'role', 'recipient_value' => $m[1]);
            } elseif (preg_match('/^user\s*:\s*(\d+)$/i', $one, $m)) {
                $out[] = array('recipient_type' => 'user', 'recipient_value' => $m[1]);
            } else {
                $out[] = array('recipient_type' => 'fixed', 'recipient_value' => substr($one, 0, 190));
            }
        }
        return $out;
    }
}

if (!function_exists('irNotifyRuleSave')) {
    /**
     * Save one rule - "when THIS happens, tell THESE people THIS way" - and the
     * people it reaches. Sits next to irNotifyRecipientsFor(), which reads back
     * what this writes.
     *
     * Returns 0 without touching anything when the cell is untouched and has no
     * rule behind it: the settings screen posts the whole grid, and writing a
     * row for every empty cell would leave 48 pointless rules per company.
     *
     * Recipients are replaced wholesale rather than diffed - the list is short,
     * and since its order is the escalation order a diff would have to preserve
     * positions anyway. Retired rows are marked Delete, never removed, so an
     * outbox row from last week can still be explained afterwards.
     *
     * outlet_id is always 0, meaning every outlet; per-outlet rules are a later
     * stage, and the column is NOT NULL so the UNIQUE key actually bites.
     *
     * @return int the rule id, or 0 when there was nothing to save
     */
    function irNotifyRuleSave($event_key, $channel, $enabled, $critical, $raw, $company_id, $user_id) {
        $CI = &get_instance();
        $events = irNotifyEvents();
        if (!isset($events[$event_key])) { return 0; }

        $enabled = $enabled ? 1 : 0;
        $critical = $critical ? 1 : 0;
        $company_id = (int) $company_id ? (int) $company_id : 1;
        $people = irNotifyParseRecipients($raw);

        $row = $CI->db->where('event_key', $event_key)->where('channel', $channel)
                      ->where('outlet_id', 0)->where('company_id', $company_id)
                      ->get('tbl_notification_rules')->row();
        if (!$row && !$enabled && !$critical && !$people) { return 0; }

        $now = date('Y-m-d H:i:s');
        if ($row) {
            $CI->db->where('id', $row->id)->update('tbl_notification_rules', array(
                'is_enabled' => $enabled,
                'is_critical' => $critical,
                'del_status' => 'Live',
                'updated_by' => (int) $user_id,
                'updated_at' => $now,
            ));
            $rule_id = (int) $row->id;
        } else {
            $CI->db->insert('tbl_notification_rules', array(
                'event_key' => $event_key,
                'channel' => $channel,
                'is_enabled' => $enabled,
                'is_critical' => $critical,
                'outlet_id' => 0,
                'company_id' => $company_id,
                'created_at' => $now,
                'updated_by' => (int) $user_id,
                'updated_at' => $now,
            ));
            $rule_id = (int) $CI->db->insert_id();
        }

        $CI->db->where('rule_id', $rule_id)->where('del_status', 'Live')
               ->update('tbl_notification_recipients', array('del_status' => 'Delete'));
        $order = 0;
        foreach ($people as $one) {
            $one['rule_id'] = $rule_id;
            $one['escalation_order'] = $order++;
            $one['del_status'] = 'Live';
            $CI->db->insert('tbl_notification_recipients', $one);
        }
        return $rule_id;
    }
}

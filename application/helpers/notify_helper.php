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
        /* Suppressed on purpose: a missing table (code deployed before the
           migration) must not fatal the sale that triggered it. */
        if (!@$CI->db->insert('tbl_notification_outbox', $data)) { return 0; }
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
     * SMS, through the product's own smsSendOnly(). That function is bounded
     * and returns a real boolean as of N3.5 - before that it returned nothing
     * and could wait for ever, which is exactly why sending moved in here.
     *
     * The provider setting is still the legacy one for now. N4 gives the
     * module its own, as agreed, because the two legacy columns contradict
     * each other.
     *
     * @return array ok, error
     */
    function irNotifyDriverSms($row) {
        $to = trim((string) $row->actual_recipient);
        if ($to === '') { return array('ok' => FALSE, 'error' => 'no number'); }
        /* digits, + and spaces only - a stray name here would be sent to a
           provider and charged for */
        if (!preg_match('/^[0-9+][0-9 ()-]{5,}$/', $to)) {
            return array('ok' => FALSE, 'error' => 'not a phone number: ' . $to);
        }
        try {
            $sent = smsSendOnly($row->body, $to);
            if ($sent === FALSE) {
                return array('ok' => FALSE, 'error' => 'the SMS provider did not accept it - see the sms lines in this log');
            }
            return array('ok' => TRUE, 'error' => '');
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

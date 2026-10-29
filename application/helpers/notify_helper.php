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
            'ack_expiry_hours'  => 72,                /* N5(a): an old SMS must not silence a new alert */
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
            'created_at'         => irNotifyNow(),
        );
        if ($dedupe !== '') { $data['dedupe_key'] = $dedupe; }
        /* N5(a)/(b): only set when there is one, so the column stays absent from
           the INSERT on a site whose migration has not run yet. */
        if (!empty($m['ack_group'])) { $data['ack_group'] = substr((string) $m['ack_group'], 0, 40); }
        if (!empty($m['escalation_tier'])) { $data['escalation_tier'] = (int) $m['escalation_tier']; }

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
            }
            /* Last resort: drop the two optional columns and try again. They are
               the only fields here that a half-applied migration can be missing,
               and a message is worth more than its metadata - losing the dedupe
               key risks a duplicate, losing ack_group costs an Acknowledge
               button, and losing the row costs the alert itself. Retried even
               when there was no dedupe key, which the first version skipped. */
            if (isset($data['dedupe_key']) || isset($data['ack_group']) || isset($data['escalation_tier'])) {
                unset($data['dedupe_key'], $data['ack_group'], $data['escalation_tier']);
                if (@$CI->db->insert('tbl_notification_outbox', $data)) {
                    @irNotifyLog('queued ' . $data['event_key']
                        . ' without dedupe_key/ack_group - are the N3/N5 migrations applied?');
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
    function irNotifyRecipientsFor($rule_id, $channel, $tier = NULL) {
        $CI = &get_instance();
        $field = in_array($channel, array('sms', 'whatsapp', 'voice'), TRUE) ? 'phone' : 'email_address';
        $out = array();

        /* N5(b): $tier NULL means every tier at once, which is what a rule with
           no ladder has always done and must keep doing. A number means that one
           rung only - the ladder asks for rung 0 first and the worker asks for
           the next one when nobody has answered. */
        $only = ($tier === NULL) ? '' : ' AND escalation_order = ' . (int) $tier;

        $rows = @$CI->db->query("SELECT recipient_type, recipient_value FROM tbl_notification_recipients
                                  WHERE rule_id = " . (int) $rule_id . " AND del_status = 'Live'"
                                  . $only . "
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
                "SELECT id, channel, is_critical,
                        COALESCE(escalate_after_minutes, 0) AS escalate_after_minutes
                   FROM tbl_notification_rules
                  WHERE event_key = " . $CI->db->escape($event_key) . "
                    AND is_enabled = 1 AND del_status = 'Live'
                    AND (outlet_id = 0 OR outlet_id = " . $outlet . ")");
            if (!$rules) {
                /* the N5(b) column is missing, so this is a site whose migration
                   has not run. Ask again without it rather than lose the alert. */
                $rules = @$CI->db->query(
                    "SELECT id, channel, is_critical, 0 AS escalate_after_minutes
                       FROM tbl_notification_rules
                      WHERE event_key = " . $CI->db->escape($event_key) . "
                        AND is_enabled = 1 AND del_status = 'Live'
                        AND (outlet_id = 0 OR outlet_id = " . $outlet . ")");
            }
            if (!$rules || !$rules->num_rows()) { return 0; }

            if (!isset($ctx['when'])) { $ctx['when'] = date('Y-m-d H:i'); }
            $queued = 0;

            /* N5(a): ONE acknowledgeable alert per call to this function, which
               is to say one per real-world incident - not one per rule and not
               one per recipient.
               A register being force-closed is a single thing that happened. If
               it is SMSed to the manager and emailed to the owner, whichever of
               them answers has dealt with it, and the other must not keep being
               escalated. The first version keyed the group per rule, and the
               test caught it: the SMS and the email came out with two different
               groups, so acknowledging one left the other climbing its ladder.
               Created lazily, on the first critical rule, so a purely
               non-critical event costs nothing. */
            $ack_group = '';
            $ack_url   = '';

            foreach ($rules->result() as $rule) {
                if (!irNotifyChannelOn($rule->channel)) { continue; }
                $short = in_array($rule->channel, array('sms', 'whatsapp', 'voice'), TRUE);
                $body = irNotifyFill($short ? $def['short'] : $def['long'], $ctx);
                $subject = irNotifyFill($def['subject'], $ctx);

                if ((int) $rule->is_critical === 1 && $ack_group === '') {
                    $group = bin2hex(random_bytes(16));
                    $token = irNotifyAckCreate(
                        $group, $event_key, $subject,
                        $outlet ?: NULL,
                        isset($ctx['company_id']) ? (int) $ctx['company_id'] : 1
                    );
                    /* No token - the table is not migrated yet - means no LINK,
                       never no alert. The message still goes out. */
                    if ($token !== '') {
                        $ack_group = $group;
                        $ack_url   = irNotifyAckUrl($token);
                    }
                }
                /* Non-critical rules for the same event stay unlinked even when
                   a critical one has made a group: there is nothing to
                   acknowledge on a message nobody is chasing. */
                $is_crit   = ((int) $rule->is_critical === 1);
                $rule_body = irNotifyAckAppend($body, $rule->channel, $is_crit ? $ack_url : '');

                /* N5(b): a rule with a ladder notifies rung 0 and nobody else.
                   The rest are reached by the worker, one rung at a time, and
                   only while nobody has acknowledged.

                   A rule with NO ladder (interval 0, which is every rule that
                   exists today) still notifies everybody at once - passing NULL
                   is literally the old call. Getting this backwards would have
                   quietly stopped telling people things. */
                $ladder = ((int) $rule->escalate_after_minutes > 0) && $is_crit;
                $tier   = $ladder ? 0 : NULL;

                foreach (irNotifyRecipientsFor($rule->id, $rule->channel, $tier) as $to) {
                    $queued += irNotifyEnqueue(array(
                        'event_key' => $event_key,
                        'channel' => $rule->channel,
                        'rule_id' => $rule->id,
                        'intended_recipient' => $to,
                        'subject' => $subject,
                        'body' => $rule_body,
                        'payload' => $ctx,
                        'is_critical' => (int) $rule->is_critical,
                        'outlet_id' => $outlet ?: NULL,
                        /* only the critical rows join the group, so settling it
                           cannot mark a non-critical message as answered */
                        'ack_group' => $is_crit ? $ack_group : '',
                        'escalation_tier' => 0,
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
    function irNotifyRuleSave($event_key, $channel, $enabled, $critical, $raw, $company_id, $user_id, $escalate_minutes = 0) {
        $CI = &get_instance();
        $events = irNotifyEvents();
        if (!isset($events[$event_key])) { return 0; }

        $enabled = $enabled ? 1 : 0;
        $critical = $critical ? 1 : 0;
        /* N5(b): 0 means no ladder - tell everyone at once, which is what every
           rule did before this existed. Capped at a day; an interval longer
           than that is not an escalation, it is a reminder nobody will act on.
           Added as an optional argument so the existing seven-argument calls
           keep working unchanged. */
        $escalate_minutes = (int) $escalate_minutes;
        if ($escalate_minutes < 0 || $escalate_minutes > 1440) { $escalate_minutes = 0; }
        $company_id = (int) $company_id ? (int) $company_id : 1;
        $people = irNotifyParseRecipients($raw);

        $row = $CI->db->where('event_key', $event_key)->where('channel', $channel)
                      ->where('outlet_id', 0)->where('company_id', $company_id)
                      ->get('tbl_notification_rules')->row();
        if (!$row && !$enabled && !$critical && !$people) { return 0; }

        $now = irNotifyNow();
        if ($row) {
            $CI->db->where('id', $row->id)->update('tbl_notification_rules', array(
                'is_enabled' => $enabled,
                'is_critical' => $critical,
                'escalate_after_minutes' => $escalate_minutes,
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
                'escalate_after_minutes' => $escalate_minutes,
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

/* ------------------------------------------- N5(a): acknowledging an alert -- */

if (!function_exists('irNotifyAckAlphabet')) {
    /**
     * The token alphabet. No padding and no '+' or '/', and none of 0/O/1/l/I,
     * because these tokens get read off a phone screen and sometimes down a
     * telephone.
     */
    function irNotifyAckAlphabet() {
        return '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    }
}

if (!function_exists('irNotifyAckNewToken')) {
    /**
     * 10 characters from a 56-letter alphabet: about 58 bits, from
     * random_bytes() and not mt_rand(), because a predictable token would let
     * somebody silence an alert they never received.
     *
     * Short on purpose. The whole link has to fit in an SMS alongside the
     * message, and every character here costs one there.
     */
    function irNotifyAckNewToken() {
        $alphabet = irNotifyAckAlphabet();
        $n = strlen($alphabet);
        $out = '';
        $bytes = random_bytes(10);
        for ($i = 0; $i < 10; $i++) {
            /* ord() % n is very slightly biased towards the first few letters.
               With one alert per token that is of no consequence; it is noted so
               nobody copies this into something where it would be. */
            $out .= $alphabet[ord($bytes[$i]) % $n];
        }
        return $out;
    }
}

if (!function_exists('irNotifyAckCreate')) {
    /**
     * Make one acknowledgeable alert and return its token, or '' if the table is
     * not there yet (code deployed before its migration must never stop an
     * alert being SENT - only stop it being acknowledgeable).
     *
     * @param string $group  the alert identity. Every message for this alert
     *                       carries it, so any one of them can settle it.
     * @param string $label  shown on the page. Stored here so the page needs no
     *                       join and reveals nothing beyond this one alert.
     */
    function irNotifyAckCreate($group, $event_key, $label = '', $outlet_id = NULL, $company_id = 1) {
        $CI = &get_instance();
        $hours = (int) irNotifySetting('ack_expiry_hours', 72);
        if ($hours < 1) { $hours = 72; }

        /* Retried rather than assumed unique: the UNIQUE index is the real
           guarantee, and a collision must not lose the alert. */
        for ($try = 0; $try < 5; $try++) {
            $token = irNotifyAckNewToken();
            $ok = @$CI->db->insert('tbl_notification_acks', array(
                'token'      => $token,
                'ack_group'  => $group,
                'event_key'  => substr((string) $event_key, 0, 60),
                'label'      => substr((string) $label, 0, 190),
                'outlet_id'  => $outlet_id !== NULL ? (int) $outlet_id : NULL,
                'company_id' => (int) $company_id,
                'expires_at' => irNotifyNow($hours * 3600),
                'created_at' => irNotifyNow(),
            ));
            if ($ok) { return $token; }
        }
        @irNotifyLog('could not create an ack token for ' . $event_key);
        return '';
    }
}

if (!function_exists('irNotifyAckUrl')) {
    /**
     * The shortest link this application can serve: /ack/<token>.
     *
     * One path segment and no query string. .htaccess already rewrites
     * everything to index.php, so "ack" resolves to the Ack controller with no
     * entry in routes.php - which is a vendor file an update would overwrite.
     */
    function irNotifyAckUrl($token) {
        if ((string) $token === '') { return ''; }
        return rtrim(base_url(), '/') . '/ack/' . $token;
    }
}

if (!function_exists('irNotifyAckRow')) {
    /** The alert behind a token, or NULL. */
    function irNotifyAckRow($token) {
        $token = trim((string) $token);
        if ($token === '' || !preg_match('/^[0-9A-Za-z]{6,16}$/', $token)) { return NULL; }
        $CI = &get_instance();
        $q = @$CI->db->query(
            "SELECT * FROM tbl_notification_acks WHERE token = " . $CI->db->escape($token) . " LIMIT 1");
        if (!$q || !$q->num_rows()) { return NULL; }
        return $q->row();
    }
}

if (!function_exists('irNotifyAckState')) {
    /**
     * What a token is good for right now, WITHOUT changing anything. This is
     * what the GET uses, and the reason the GET is safe to prefetch.
     *
     * One of: unknown, expired, already, open.
     */
    function irNotifyAckState($token) {
        $row = irNotifyAckRow($token);
        if (!$row) { return array('state' => 'unknown', 'row' => NULL); }
        if ($row->acknowledged_at !== NULL && $row->acknowledged_at !== '') {
            /* Checked BEFORE expiry on purpose: an alert that was acknowledged
               and has since expired should say "already done", not "too late". */
            return array('state' => 'already', 'row' => $row);
        }
        if (strtotime($row->expires_at) < time()) { return array('state' => 'expired', 'row' => $row); }
        return array('state' => 'open', 'row' => $row);
    }
}

if (!function_exists('irNotifyAckConfirm')) {
    /**
     * Acknowledge. Idempotent: a second POST reports 'already' and changes
     * nothing, so a double-tap cannot overwrite who answered first.
     *
     * Settles every outbox row in the group, which is what stops the N5(b)
     * ladder - one person answering silences the alert for all of its tiers.
     */
    function irNotifyAckConfirm($token, $user_id = NULL, $ip = '') {
        $st = irNotifyAckState($token);
        if ($st['state'] !== 'open') { return $st; }

        $CI = &get_instance();
        $row = $st['row'];
        $now = irNotifyNow();
        $by  = ($user_id !== NULL && $user_id !== '') ? (int) $user_id : NULL;

        /* The WHERE carries acknowledged_at IS NULL, so two taps in the same
           second cannot both win: the second updates nothing. */
        @$CI->db->query(
            "UPDATE tbl_notification_acks
                SET acknowledged_at = " . $CI->db->escape($now) . ",
                    acknowledged_by = " . ($by !== NULL ? $by : 'NULL') . ",
                    acknowledged_ip = " . $CI->db->escape(substr((string) $ip, 0, 45)) . "
              WHERE id = " . (int) $row->id . " AND acknowledged_at IS NULL");

        @$CI->db->query(
            "UPDATE tbl_notification_outbox
                SET acknowledged_at = " . $CI->db->escape($now) . ",
                    acknowledged_by = " . ($by !== NULL ? $by : 'NULL') . "
              WHERE ack_group = " . $CI->db->escape($row->ack_group) . "
                AND acknowledged_at IS NULL");

        @irNotifyLog('acknowledged ' . $row->event_key . ' (' . $row->ack_group . ')'
            . ($by !== NULL ? ' by user ' . $by : ' by token')
            . ($ip !== '' ? ' from ' . $ip : ''));

        return irNotifyAckState($token);
    }
}

if (!function_exists('irNotifyAckGroupSettled')) {
    /** Has this alert been acknowledged? The N5(b) ladder asks this. */
    function irNotifyAckGroupSettled($group) {
        if ((string) $group === '') { return FALSE; }
        $CI = &get_instance();
        $q = @$CI->db->query(
            "SELECT acknowledged_at FROM tbl_notification_acks
              WHERE ack_group = " . $CI->db->escape($group) . " LIMIT 1");
        if (!$q || !$q->num_rows()) { return FALSE; }
        $v = $q->row()->acknowledged_at;
        return ($v !== NULL && $v !== '');
    }
}

if (!function_exists('irNotifyAckAppend')) {
    /**
     * Put the link in the message.
     *
     * Short channels get " Ack: <url>" and nothing more, because every word here
     * costs an SMS character and a second SMS costs real money. Email gets its
     * own line and a sentence, because there is room and the link needs
     * explaining.
     */
    function irNotifyAckAppend($body, $channel, $url) {
        if ((string) $url === '') { return $body; }
        $short = in_array($channel, array('sms', 'whatsapp', 'voice'), TRUE);
        if (!$short) {
            return rtrim($body) . "\n\n" . lang('notify_ack_email_line') . "\n" . $url . "\n";
        }

        /* The link must survive; the prose may not. Measured against the real
           templates with this venue's longest real values, order_cancelled came
           to 169 characters - a second SMS, charged separately, for the sake of
           the tail of a free-text reason.
           So the BODY is trimmed to fit and the link is never touched. The
           templates put the order number, outlet and amount before the
           free-text, so what gets cut is the least load-bearing end of the
           sentence. An ellipsis marks it, rather than stopping mid-word and
           looking like a truncated send. */
        $tail = ' Ack: ' . $url;
        $body = rtrim($body);
        $room = 160 - strlen($tail);
        if (strlen($body) > $room) {
            /* "..." and NOT the single-character ellipsis. U+2026 is not in the
               GSM 03.38 alphabet, so one of them forces the whole message into
               UCS-2 - where a single SMS holds 70 characters, not 160. The
               pretty ellipsis would have caused the exact split this trimming
               exists to prevent. Three dots are three GSM characters. */
            $body = rtrim(substr($body, 0, $room - 3)) . '...';
            while (strlen($body) > $room) { $body = substr($body, 0, -4) . '...'; }
        }
        return $body . $tail;
    }
}

/* --------------------------------------------------------------- one clock -- */

if (!function_exists('irNotifyNow')) {
    /**
     * The module's clock. Every stored datetime and every comparison goes
     * through here, and nothing in this module uses MySQL's NOW().
     *
     * WHY THIS EXISTS
     * The module used to mix the two. PHP wrote created_at, sent_at and
     * next_attempt_at with date(), and SQL compared them with NOW() - which is
     * the database server's clock, not PHP's. On this machine PHP runs in UTC
     * and MySQL reports W. Central Africa (UTC+1), so they were an hour apart,
     * and the effect was not cosmetic:
     *
     *   a 5-minute backoff written as 16:02:15 was already <= NOW() (16:57:15)
     *
     * so every retry was due the instant it was scheduled. A failing message
     * burned all three attempts in three minutes instead of over twenty-one,
     * and held messages expired an hour early. Proven with
     *   SELECT '<php now + 300s>' <= NOW()  ->  1
     *
     * It is not fixed by setting the session timezone, because this is a vendor
     * product whose reports all over the application compare dates their own
     * way; changing the connection's clock would move those too. So the module
     * stops asking the database what time it is instead.
     *
     * date() and not gmdate(): the application sets PHP's timezone per request
     * from the venue's own setting (my_helper.php, date_default_timezone_set),
     * and a venue reading its own log wants its own wall clock.
     */
    function irNotifyNow($offset_seconds = 0) {
        return date('Y-m-d H:i:s', time() + (int) $offset_seconds);
    }
}

/* ------------------------------------------- N5(b): the escalation ladder -- */

if (!function_exists('irNotifyEscalate')) {
    /**
     * Move unanswered critical alerts up to the next rung. Returns how many new
     * messages were queued. Called by the worker on every run.
     *
     * NEW ROWS, NEVER MUTATED ONES. The outbox is the send log as well as the
     * queue - "who was told, when, and did it arrive" has to stay answerable
     * afterwards. Rewriting tier 0's row to point at tier 1 would destroy the
     * record of the first attempt, which is exactly the record you want when
     * somebody asks why nobody reacted for forty minutes.
     *
     * A ladder stops for any of four reasons, and three of them are endings
     * rather than failures:
     *   - somebody acknowledged      -> the alert is handled
     *   - the last rung is reached   -> there is nobody else to tell
     *   - the ack token expired      -> nobody can answer it any more, so
     *                                   climbing would only make noise
     *   - the rule was switched off or deleted since it fired
     *
     * The interval is per RULE, not per alert: "SMS the manager every 10
     * minutes" and "email the owner after an hour" are different undertakings
     * about the same incident. Acknowledging settles the incident and therefore
     * stops both.
     */
    function irNotifyEscalate($limit = 50) {
        if (!irNotifyOn()) { return 0; }
        $CI = &get_instance();
        $queued = 0;

        /* Candidates: the newest rung of every unanswered critical alert whose
           rule has a ladder. Grouped per (alert, rule) because each rule climbs
           its own. */
        $rows = @$CI->db->query(
            "SELECT o.ack_group,
                    o.rule_id,
                    MAX(o.escalation_tier)                      AS tier,
                    MAX(COALESCE(o.sent_at, o.created_at))      AS last_at,
                    SUM(o.status = 'pending')                   AS still_pending,
                    MAX(o.event_key)                            AS event_key,
                    MAX(o.subject)                              AS subject,
                    MAX(o.body)                                 AS body,
                    MAX(o.payload)                              AS payload,
                    MAX(o.outlet_id)                            AS outlet_id,
                    MAX(o.company_id)                           AS company_id,
                    MAX(r.channel)                              AS channel,
                    MAX(r.escalate_after_minutes)               AS mins
               FROM tbl_notification_outbox o
               JOIN tbl_notification_rules r
                 ON r.id = o.rule_id AND r.is_enabled = 1 AND r.del_status = 'Live'
               JOIN tbl_notification_acks a
                 ON a.ack_group = o.ack_group
              WHERE o.is_critical = 1
                AND o.ack_group IS NOT NULL AND o.ack_group <> ''
                AND a.acknowledged_at IS NULL
                AND a.expires_at > " . $CI->db->escape(irNotifyNow()) . "
                AND COALESCE(r.escalate_after_minutes, 0) > 0
              GROUP BY o.ack_group, o.rule_id
              LIMIT " . (int) $limit);
        if (!$rows || !$rows->num_rows()) { return 0; }

        foreach ($rows->result() as $g) {
            /* A rung that has not finished sending is not a rung nobody
               answered - it is a rung nobody has been TOLD about yet, usually
               because cron is behind or a gateway is slow. Escalating past it
               would punish the recipient for the system's own delay. */
            if ((int) $g->still_pending > 0) { continue; }

            /* The clock starts when the previous rung actually went out, not
               when the alert was raised. A failed rung still counts as gone
               out: nobody is coming, so the next person should hear sooner,
               not never. */
            $due_at = strtotime($g->last_at) + ((int) $g->mins * 60);
            if ($due_at > time()) { continue; }

            $next = (int) $g->tier + 1;
            $to_list = irNotifyRecipientsFor($g->rule_id, $g->channel, $next);
            if (!$to_list) { continue; }          /* top of the ladder: done */

            if (!irNotifyChannelOn($g->channel)) { continue; }

            $payload = json_decode((string) $g->payload, TRUE);
            if (!is_array($payload)) { $payload = array(); }

            foreach ($to_list as $to) {
                $queued += irNotifyEnqueue(array(
                    'event_key'          => $g->event_key,
                    'channel'            => $g->channel,
                    'rule_id'            => $g->rule_id,
                    'intended_recipient' => $to,
                    'subject'            => $g->subject,
                    /* The same words, with the rung named. Somebody on tier 2
                       needs to know they are the backstop and not the first to
                       hear, or they will assume it is already being handled. */
                    'body'               => irNotifyEscalateBody($g->body, $next),
                    'payload'            => $payload,
                    'is_critical'        => 1,
                    'outlet_id'          => $g->outlet_id,
                    'company_id'         => $g->company_id,
                    'ack_group'          => $g->ack_group,
                    'escalation_tier'    => $next,
                    /* One rung per alert per rule, however many times the worker
                       runs. Two overlapping cron runs would otherwise both see
                       the same due rung and both send it. */
                    'dedupe_key'         => 'esc:' . $g->ack_group . ':' . $g->rule_id . ':' . $next . ':' . $to,
                )) ? 1 : 0;
            }

            if ($queued) {
                @irNotifyLog(sprintf('escalated %s (%s) rule %d to tier %d, %d recipient(s)',
                    $g->event_key, substr($g->ack_group, 0, 8), $g->rule_id, $next, count($to_list)));
            }
        }
        return $queued;
    }
}

if (!function_exists('irNotifyEscalateBody')) {
    /**
     * Mark a message as an escalation without pushing an SMS over 160.
     *
     * The prefix goes at the FRONT, because that is the part a phone shows in
     * the notification shade, and "NOT ACKNOWLEDGED" is the whole reason this
     * person is being woken up. The Acknowledge link is at the end and must
     * survive, so if the result is too long the middle of the sentence gives
     * way - never the prefix and never the link.
     */
    function irNotifyEscalateBody($body, $tier) {
        $body   = (string) $body;
        $prefix = 'ESCALATED (no reply): ';
        $out    = $prefix . $body;
        if (strlen($out) <= 160 || strpos($body, ' Ack: ') === FALSE) { return $out; }

        $at   = strrpos($body, ' Ack: ');
        $text = substr($body, 0, $at);
        $link = substr($body, $at);
        $room = 160 - strlen($prefix) - strlen($link);
        if ($room < 20) { return $prefix . $link; }   /* nothing sensible left to say */
        if (strlen($text) > $room) {
            /* "..." not the ellipsis character - see irNotifyAckAppend: U+2026
               is outside GSM 03.38 and would force UCS-2, capping the message
               at 70 characters and splitting it. */
            $text = rtrim(substr($text, 0, $room - 3)) . '...';
            while (strlen($text) > $room) { $text = substr($text, 0, -4) . '...'; }
        }
        return $prefix . $text . $link;
    }
}

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

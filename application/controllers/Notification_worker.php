<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The notification worker (N2, 2026-10-29). CLI ONLY.
 *
 * This is the only thing in Rexlio that sends a notification. Event points
 * write a row and return; this drains the outbox from cron. Keeping the sending
 * here is what guarantees a till never waits on a provider.
 *
 * Run from cron, once a minute:
 *   cd /home/<user>/<docroot> && /usr/local/bin/php index.php Notification_worker run
 *
 * Commands:
 *   run     send what is due
 *   status  print a summary; writes nothing
 *   test    queue one message to the test recipient and send it immediately
 *
 * WHY IT REFUSES THE WEB: a URL that sends mail is a URL somebody else can
 * trigger. There is no authentication on a cron job, so the protection has to
 * be that it cannot be reached over HTTP at all.
 */
class Notification_worker extends CI_Controller {

    public function __construct() {
        parent::__construct();
        /* The guard. is_cli() is CodeIgniter's own check: TRUE only when PHP is
           running from a command line, never for a web request. */
        if (!is_cli()) {
            show_error('The notification worker runs from the command line only. '
                     . 'It is not reachable over the web, on purpose: a URL that sends messages '
                     . 'is a URL somebody else can trigger. Run it from cron: '
                     . 'php index.php Notification_worker run', 403, 'Command line only');
            exit(1);
        }
        $this->load->helper('notify');
        $this->load->database();
    }

    /** Drain what is due. The whole point of the module. */
    public function run() {
        $started = microtime(TRUE);

        if (!irNotifyOn()) {
            /* Not an error. The master switch being off is a valid state, and a
               cron job that shouts about it every minute trains people to ignore
               the log. Said once, quietly. */
            $this->out('master switch off - nothing to do');
            $this->markRun('master switch off - nothing to do');
            return;
        }

        $s = irNotifySettings();
        $max = (int) $s['max_attempts'];
        $batch = (int) $s['batch_size'];

        $this->expireHeld($s);

        /* N5(b): climb any ladder that is due BEFORE picking up what to send,
           so a new rung goes out on this run rather than waiting for the next
           one. At a minute apart that would be a minute of needless silence on
           an alert nobody has answered. */
        $escalated = irNotifyEscalate($batch);

        /* due = pending, and either never tried or past its backoff */
        $rows = $this->db->query(
            "SELECT * FROM tbl_notification_outbox
              WHERE status = 'pending'
                AND (next_attempt_at IS NULL OR next_attempt_at <= '" . irNotifyNow() . "')
              ORDER BY is_critical DESC, id ASC
              LIMIT " . $batch)->result();

        if (!$rows) {
            /* Escalating and then finding nothing to send would mean the new
               rung sat unsent until the next run, so say what happened. */
            $line = $escalated ? ('nothing due (escalated ' . $escalated . ')') : 'nothing due';
            $this->out($line);
            $this->markRun($line);
            return;
        }

        $sent = 0; $failed = 0; $held = 0; $skipped = 0;
        foreach ($rows as $row) {
            /* A channel switched off AFTER something was queued holds its
               messages rather than sending them. Switching a channel off should
               stop messages going out, including the ones already waiting. */
            if (!irNotifyChannelOn($row->channel)) {
                $this->mark($row->id, array(
                    'status' => 'held',
                    'last_error' => 'channel ' . $row->channel . ' is switched off',
                ));
                $held++;
                continue;
            }

            $driver = irNotifyDriver($row->channel);
            if ($driver === NULL) {
                /* left pending on purpose - see irNotifyDriver() */
                $skipped++;
                continue;
            }

            $attempt = (int) $row->attempts + 1;
            $result = call_user_func($driver, $row);

            if (!empty($result['ok'])) {
                $this->mark($row->id, array(
                    'status' => 'sent', 'attempts' => $attempt,
                    'sent_at' => irNotifyNow(), 'last_error' => NULL,
                    'next_attempt_at' => NULL,
                ));
                $sent++;
                irNotifyLog(sprintf('sent   #%d %s/%s to %s', $row->id, $row->event_key, $row->channel, $row->actual_recipient));
                continue;
            }

            $error = isset($result['error']) ? $result['error'] : 'unknown error';
            if ($attempt >= $max) {
                $this->mark($row->id, array(
                    'status' => 'failed', 'attempts' => $attempt,
                    'last_error' => substr($error, 0, 480), 'next_attempt_at' => NULL,
                ));
                $failed++;
                irNotifyLog(sprintf('FAILED #%d %s/%s after %d attempts: %s',
                    $row->id, $row->event_key, $row->channel, $attempt, $error));
                continue;
            }

            /* backoff: one entry per retry, the last one repeats */
            $waits = $s['backoff_minutes'];
            $wait = isset($waits[$attempt - 1]) ? (int) $waits[$attempt - 1] : (int) end($waits);
            $this->mark($row->id, array(
                'status' => 'pending', 'attempts' => $attempt,
                'last_error' => substr($error, 0, 480),
                /* irNotifyNow, not date(): this value is compared against the
                   module's clock in the due query, and the two were an hour
                   apart while one was PHP and the other MySQL NOW(). */
                'next_attempt_at' => irNotifyNow($wait * 60),
            ));
            irNotifyLog(sprintf('retry  #%d %s/%s attempt %d, next in %dm: %s',
                $row->id, $row->event_key, $row->channel, $attempt, $wait, $error));
        }

        $took = round(microtime(TRUE) - $started, 2);
        $line = sprintf('run: %d sent, %d failed, %d held, %d escalated, %d waiting for a driver (%ss)',
            $sent, $failed, $held, $escalated, $skipped, $took);
        $this->out($line);
        /* Only write a run line to the log when something happened. A minute-by
           -minute "nothing due" would bury the lines that matter. */
        if ($sent || $failed || $held || $escalated) { irNotifyLog($line); }
        $this->markRun($line);
    }

    /**
     * Overwrite a one-line "last run" marker.
     *
     * The dated log deliberately stays quiet on an idle run, which leaves no way
     * to answer the first question anybody asks after adding the cron entry:
     * did it run at all? Appending a line a minute would answer it and bury
     * everything else, so this file is OVERWRITTEN each time - it never grows
     * past one line, and `cat` on it is the whole check.
     *
     * Written on EVERY path out of run(), including "master switch off" and
     * "nothing due". Those two are the common cases on a healthy site and were
     * the first version's blind spot: it only marked the path where work had
     * been done, so the file stayed missing exactly when somebody was trying to
     * find out whether cron was firing at all.
     *
     * Not the N5 heartbeat. That one has to notice when the worker STOPS, which
     * needs something outside this process; this is just a timestamp the worker
     * leaves behind.
     */
    private function markRun($line) {
        $dir = APPPATH . 'logs/notifications';
        if (!is_dir($dir)) { @mkdir($dir, 0755, TRUE); }
        @file_put_contents($dir . '/last-run.txt',
            date('Y-m-d H:i:s') . '  ' . $line . PHP_EOL);
    }

    /** A summary. Writes nothing - safe to run at any time. */
    public function status() {
        $on = irNotifyOn();
        $s = irNotifySettings();
        $this->out('master switch   : ' . ($on ? 'ON' : 'off'));
        foreach (array('inapp','email','sms','whatsapp','telegram','push') as $c) {
            /* The "no driver" note has to show for an ON channel too - that is
               the case worth warning about, because its messages will sit
               pending for ever and look like a stuck queue. */
            $state = irNotifyChannelOn($c) ? 'on' : 'off';
            if (irNotifyDriver($c) === NULL) {
                $state .= ($state === 'on') ? '  <- NO DRIVER YET, messages will stay pending' : ' (no driver yet)';
            }
            $this->out(sprintf('  %-10s    : %s', $c, $state));
        }
        $this->out('test mode       : ' . (!empty($s['test_mode']) ? 'ON -> ' . ($s['test_recipient'] ?: '(nobody set!)') : 'off'));
        $q = $this->db->query("SELECT status, COUNT(*) n FROM tbl_notification_outbox GROUP BY status");
        $this->out('outbox          :');
        if ($q && $q->num_rows()) {
            foreach ($q->result() as $r) { $this->out(sprintf('  %-10s    : %d', $r->status, $r->n)); }
        } else {
            $this->out('  (empty)');
        }
        $stuck = $this->db->query("SELECT COUNT(*) n FROM tbl_notification_outbox
                                    WHERE status='pending' AND created_at < '" . irNotifyNow(-3600) . "'")->row();
        if ($stuck && (int) $stuck->n > 0) {
            $this->out('WARNING: ' . $stuck->n . ' message(s) pending for over an hour - is cron running?');
        }
    }

    /**
     * Queue one message to the test recipient and send it now.
     * Proves the whole path - switches, queue, driver, provider - in one go.
     */
    public function test($channel = 'email') {
        if (!irNotifyOn()) { $this->out('master switch is off - turn it on first'); return; }
        if (!irNotifyChannelOn($channel)) { $this->out('channel ' . $channel . ' is off - turn it on first'); return; }
        $s = irNotifySettings();
        $to = trim((string) $s['test_recipient']);
        if ($to === '') { $this->out('no test recipient set - set one in Settings > Notifications'); return; }

        $id = irNotifyEnqueue(array(
            'event_key' => 'manual_test',
            'channel' => $channel,
            'intended_recipient' => $to,
            'subject' => 'Rexlio test notification',
            'body' => "This is a test from the Rexlio notification worker.\n\n"
                    . "If you are reading this, the queue, the worker and the "
                    . strtoupper($channel) . " settings are all working.\n\n"
                    . 'Sent ' . date('Y-m-d H:i:s') . ' from ' . php_uname('n') . ".\n",
            'payload' => array('manual' => TRUE),
        ));
        if (!$id) { $this->out('could not queue - check the switches and the test recipient'); return; }
        $this->out('queued #' . $id . ', sending...');
        $this->run();
        $row = $this->db->query("SELECT status, attempts, last_error, actual_recipient
                                   FROM tbl_notification_outbox WHERE id = " . (int) $id)->row();
        if ($row) {
            $this->out('result: ' . $row->status . ' to ' . $row->actual_recipient
                     . ($row->last_error ? ' - ' . $row->last_error : ''));
        }
    }

    /* ------------------------------------------------------------- internals */

    /** Held messages are dropped once they are stale. Never sent late. */
    private function expireHeld($s) {
        $hours = (int) $s['hold_expiry_hours'];
        if ($hours <= 0) { return; }
        $this->db->query(
            "UPDATE tbl_notification_outbox
                SET status = 'expired',
                    last_error = CONCAT(COALESCE(last_error,''), ' | expired after " . $hours . "h held')
              WHERE status = 'held'
                AND created_at < '" . irNotifyNow(-$hours * 3600) . "'");
        $n = $this->db->affected_rows();
        if ($n > 0) { irNotifyLog($n . ' held message(s) expired after ' . $hours . 'h'); }
    }

    private function mark($id, $fields) {
        $this->db->where('id', (int) $id)->update('tbl_notification_outbox', $fields);
    }

    private function out($line) {
        fwrite(STDOUT, $line . "\n");
    }
}

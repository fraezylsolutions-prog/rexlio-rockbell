# Goloviana — first notification release

The first deployment of the notifications module to a **real trading client**.

Read `doc/GOLOVIANA_CLIENT_PROFILE.md` first. Rockbell is frozen and gets
none of this — see `doc/ROCKBELL_HOLDBACK.md`.

**Nothing in this file has been run against Goloviana.** It is written to be
executed by the owner, step by step, stopping at each checkpoint.

**The release is deliberately uneventful.** Every switch ships off and test mode
ships on, so steps 1–6 should change *nothing* that staff or guests can see.
The first message only moves when step 8 says so.

---

## Scope

**In:** email, SMS, acknowledge, escalation, heartbeat, end-of-day and low-stock
digests.

**Out, and not started:** WhatsApp, Telegram, push. Their channel rows exist and
are off; the worker reports them as "no driver yet" and any message queued for
them would sit pending for ever, which is why no rule should name them.

## What is going

**15 commits**, `e319baab..HEAD`:

| | |
|---|---|
| `2503d2ce` | N1 — the shape, every switch off, nothing that can send |
| `885386d7` | N2 — the worker, email, and test mode |
| `5d76b4f2` | N3 — the eight hooks, and the rules that decide who hears |
| `35ce3c01` | N3.5 — bound the SMS path, and stop the till waiting on it |
| `5d3d30b5` | N4 — SMS via Termii |
| `4770ab8f` | N4.5 — the settings screen |
| `32108d86` | N3 fix — get the hooks out of the sale's transaction |
| `3b4c6a4b` | Till freeze — decide the SMS path explicitly; stop test mode texting customers |
| `36ea5318` | N3 — correct the stated reason for moving the order_placed hook |
| `a14fd1cf` | CLI entry point |
| `0cd8973f` | Worker — last-run marker |
| `91e7c875` | N5(a) — acknowledge, and one clock for the module |
| `83f238a2` | N5(b) — the escalation ladder |
| `86f58060` | N5(c) — heartbeat, and links that work from a phone |
| `0e0938d6` | N5(d) — end-of-day and low-stock digests |

**Five migrations**, all additive and idempotent:

    db/migrations/2026-10-29_01_notifications-n1.sql
    db/migrations/2026-10-29_02_notifications-n3-dedupe.sql
    db/migrations/2026-10-29_03_notifications-n5-ack.sql
    db/migrations/2026-10-29_04_notifications-n5-escalation.sql
    db/migrations/2026-10-29_05_notifications-n5-digests.sql

They add three tables (`tbl_notification_rules`, `_recipients`, `_outbox`), one
more (`tbl_notification_acks`), seven `tbl_modules` rows all set to 0, one
permission, and columns on `tbl_notification_outbox`, `tbl_notification_rules`
and `tbl_outlets`. **No existing row is modified and nothing is dropped.**

**Files changed — 22.** Four are new application files
(`controllers/Ack.php`, `controllers/Notification_worker.php`,
`helpers/notify_helper.php`, `views/notification/ack.php`,
`views/setting/notifications.php`, `cli.php`); the rest are additions to
existing files. `db/` and `doc/` are excluded from deploys, so of the list only
the application files and `cli.php` actually reach the docroot.

### Existing files touched, and what changed in each

| File | Change |
|---|---|
| `config/autoload.php` | loads the `notify` helper |
| `controllers/Sale.php` | eight hook calls; the receipt-SMS decision; order-placed moved after the commit |
| `controllers/Register.php` | one hook on force-close |
| `controllers/Hotel.php` | three hooks (check-in, check-out, room dirty) |
| `controllers/Setting.php` | the Notifications screen and its save paths |
| `helpers/my_helper.php` | `smsSendOnly()` bounded, returns success/failure, logs |
| `language/english/english_lang.php` | new strings only |
| `views/userHome.php` | the menu entry |
| `db/migrations/preflight_status.sql` | reporting only, not deployed |

## Regression run before release

Run locally against `rexlio_scratch`, PHP 8.3.28, with PHP on UTC and MySQL an
hour ahead of it:

    run_n3 16 · run_n3_rollback 3 · run_n3_poison 2 · run_n45 67 · run_n5a 24
    run_n5b 27 · run_n5c 15 · run_n5d 35 · run_n5d_isolation 8 · run_f5 7
    run_facts 24 · helpers 42 · view 13 · n5a 27
    ------------------------------------------------
    TOTAL 310 passed, 0 failed   (run_f5 contributes 0 in a back-to-back loop
                                  because of suite ordering; it passes 7/0 on
                                  its own, so 317/0 across all suites)

Re-run before pushing and confirm ZERO failures. A release goes out on a green
run, not on a remembered one.

---

## Step 0 — Preflight (nothing is changed)

Everything here is read-only. Stop and resolve anything that does not match.

1. **Answer the open questions** in `doc/GOLOVIANA_CLIENT_PROFILE.md` —
   database name, PHP version, whether SMS credentials already exist. The
   commands below need the first two.

2. **Confirm what is live now.** In a browser:

       https://goloviana.fraezyl.com/build.txt?nocache=1

   Expect `e319baab` and `deployed 2026-10-28 22:52 UTC`. If it shows something
   else, stop — this plan was written against `e319baab`.

3. **Confirm the installer is still gone.** Both should be 404:

       https://goloviana.fraezyl.com/install/
       https://goloviana.fraezyl.com/install.zip

4. **Confirm the clone knows its docroot.** In cPanel → Terminal:

       cat /home/citytjgo/repositories/goloviana/.rexlio_deploypath

   Expect `/home/citytjgo/goloviana.fraezyl.com`. If it is missing,
   `.cpanel.yml` refuses to deploy — which is the safety net working.

5. **Find the PHP binary** and confirm it matches the version cPanel shows for
   this site:

       ls -d /opt/cpanel/ea-php*/root/usr/bin/php
       /opt/cpanel/ea-php83/root/usr/bin/php -v

6. **Confirm the install record**, because the daily digests depend on it:

       cd /home/citytjgo/goloviana.fraezyl.com
       php -r '$j=json_decode(file_get_contents("assets/blueimp/REST_API_I.json"),true); echo str_rot13($j["installation_url"]),"\n";'

   Expect Goloviana's own URL. If not, the digests will not run from cron —
   see `doc/NOTIFICATIONS_RUNBOOK.md` section 4a. Sending is unaffected either
   way, so this does not block the release.

7. **Push to GitHub.** The Deployment Manager deploys GitHub's `master`, so
   until this is done nothing can reach the server:

       git push origin master

## Step 1 — Backup

Use the Deployment Manager's **Complete Update** on the Goloviana profile,
which takes the live database backup first.

**Do not continue until the backup file is confirmed written** —
`citytjgo_gol_<date>_<time>.sql`, newest 10 kept. Open it and check it is not
zero bytes. A backup nobody looked at is not a backup.

**Never use "Refresh this computer from live".**

## Step 2 — Migrations

Complete Update applies only the missing migrations. Expect **five** applied,
taking the total from 17 to 22.

If running them by hand instead, do it **from the repository clone** — `db/` is
excluded from deploys, so the docroot has no migrations folder:

    cd /home/citytjgo/repositories/goloviana
    mysql --defaults-extra-file=~/rexlio_sync/goloviana.cnf <db> < db/migrations/2026-10-29_01_notifications-n1.sql
    mysql --defaults-extra-file=~/rexlio_sync/goloviana.cnf <db> < db/migrations/2026-10-29_02_notifications-n3-dedupe.sql
    mysql --defaults-extra-file=~/rexlio_sync/goloviana.cnf <db> < db/migrations/2026-10-29_03_notifications-n5-ack.sql
    mysql --defaults-extra-file=~/rexlio_sync/goloviana.cnf <db> < db/migrations/2026-10-29_04_notifications-n5-escalation.sql
    mysql --defaults-extra-file=~/rexlio_sync/goloviana.cnf <db> < db/migrations/2026-10-29_05_notifications-n5-digests.sql

`<db>` is the database name from the profile. The option file supplies the
credentials; they are never typed on the command line.

## Step 3 — Code

Complete Update deploys it. Then confirm:

    https://goloviana.fraezyl.com/build.txt?nocache=1

Expect `0e0938d6` and a fresh timestamp.

## Step 4 — Post-deploy check: everything must be OFF

**This is the checkpoint that matters.** Read-only SQL — run it and read every
row:

    SELECT module_key, is_enabled FROM tbl_modules WHERE module_key LIKE 'notif%' ORDER BY module_key;

Expect **seven rows, every `is_enabled` = 0**:
`notifications`, `notify_email`, `notify_inapp`, `notify_push`, `notify_sms`,
`notify_telegram`, `notify_whatsapp`.

    SELECT COUNT(*) AS rules FROM tbl_notification_rules;
    SELECT COUNT(*) AS queued FROM tbl_notification_outbox;

Expect **0 and 0**. No rules means nothing can be queued even if a switch were
flipped by accident.

Then sign out and back in once — the permission list is built at login, so the
**Notifications** menu entry will not appear until you do. Open
Settings → Notifications and confirm on screen:

- the master switch is **off**, with the "notifications are switched off" banner
- **Test mode is ON** — it is on by default and must stay on
- the test recipient is **blank**
- every channel is off

If any of that is not true, stop and say so before going further.

## Step 5 — Smoke test the till, before any cron

The point is that the deploy changed nothing. On the live site:

- place an order, modify it, cancel one
- take a payment through to completion
- check in or out a hotel guest if convenient
- open the Detailed Sale Report (set at least one filter — it returns nothing
  otherwise, which is expected)

Then confirm the module really did stay silent:

    SELECT COUNT(*) AS queued FROM tbl_notification_outbox;

Still **0**. With the master switch off, each hook costs one cached array
lookup and queues nothing.

## Step 6 — Set the site's public address

Settings → Notifications → **Public address of this site**:

    https://goloviana.fraezyl.com/

Needed because the worker runs from cron, where there is no web request to work
the address out from. Without it, Acknowledge links in escalations come out as
`http://localhost/ack/…` and go nowhere from a phone.

## Step 7 — Cron, pointing at the docroot

cPanel → **Cron Jobs**, Common Settings "Once Per Minute". One line, with the
PHP binary from step 0:

    * * * * * RTD_CLI_HOST=goloviana.fraezyl.com RTD_CLI_PATH=/ /opt/cpanel/ea-php83/root/usr/bin/php /home/citytjgo/goloviana.fraezyl.com/cli.php notification_worker run >/dev/null 2>&1

And a second line for the digests, hourly, so the one part of the worker that
loads a model can never affect the send loop:

    0 * * * * RTD_CLI_HOST=goloviana.fraezyl.com RTD_CLI_PATH=/ /opt/cpanel/ea-php83/root/usr/bin/php /home/citytjgo/goloviana.fraezyl.com/cli.php notification_worker digests >/dev/null 2>&1

The first line also raises the digests, after sending and after the marker and
heartbeat, so the second is belt and braces — the dedupe key allows only one
digest per outlet per day however many times it runs.

In the cPanel form the five `* * * * *` fields are separate boxes, so paste only
from `RTD_CLI_HOST=` onwards into the Command box.

Check all four, given the shared home directory:

- the path says `goloviana.fraezyl.com`, **not** `rockbell.fraezyl.app`
- it is the **docroot**, not `/home/citytjgo/repositories/goloviana`
- `RTD_CLI_PATH=/` — Goloviana is installed at its docroot
- the **Email** box is cleared, or you get one mail a minute

### Confirm it ran

One line, overwritten every run, so it cannot grow:

    cat /home/citytjgo/goloviana.fraezyl.com/application/logs/notifications/last-run.txt

Expect a timestamp inside the last minute or two:

    2026-10-30 04:21:19  master switch off - nothing to do

**That message is the success case at this point** — the master switch really is
off, and the marker proves cron is firing. If the file does not appear within
two minutes, the cron entry is wrong; nothing else in this plan will work until
it does.

Then the fuller check:

    /opt/cpanel/ea-php83/root/usr/bin/php /home/citytjgo/goloviana.fraezyl.com/cli.php notification_worker status

Expect `daily digests   : can run (models load under CLI)`. If it says
`WILL NOT RUN`, only the digests are affected — sending, escalation and
acknowledge are unaffected, so this does not block the release.

## Step 8 — Switch on, one channel at a time, to yourself only

Only now does anything send. **Do not skip the order, and check the outbox
after each step.**

1. **Test recipient first.** Settings → Notifications → set the test recipient
   to **your own email**. Leave test mode **ON**. With test mode on, every
   message goes to that one address whatever a rule says — the outbox records
   both the intended and the actual recipient, so the routing is still proved.

2. **Email only.** Turn on the master switch, then the **email** channel. Use
   **Send a test now**. Confirm it arrives at your address. Then:

       SELECT id, event_key, channel, status, intended_recipient, actual_recipient, last_error
         FROM tbl_notification_outbox ORDER BY id DESC LIMIT 5;

   Expect `sent`. If `failed`, `last_error` carries the mail server's own words.

3. **One event.** Add one rule — `order_cancelled` on email, recipient your own
   address — and cancel a test order. Confirm the message arrives naming the
   order number and the amount, and that the outbox row says `sent`.

4. **SMS.** Change the test recipient to **your own phone**. Turn on the SMS
   channel, set the provider key (entered by you on the screen — never in a
   prompt or a file). **Nigerian numbers need the DND route** — Generic looks
   like it works and delivers nothing to them. **Send a test now**, and confirm
   it arrives on your handset. Termii returns HTTP 200 for some refusals, so
   trust the outbox row and your phone, not the absence of an error.

5. **One critical event, with acknowledge.** Make a rule critical —
   `register_force_closed` on SMS, to your own phone — and force-close a
   register. The SMS carries an **Acknowledge** link. Open it: it must show a
   page with a button and must **not** acknowledge on opening. Tap the button
   and confirm the outbox row gets `acknowledged_at`.

6. **Escalation.** Set **Escalate after (min)** on that rule and give it two
   recipients, both yours. Trigger it, do not acknowledge, and confirm the
   second message arrives after the interval and stops once you acknowledge.

7. **Heartbeat.** Create a check at any uptime service that gives you a URL to
   fetch, paste it into **Heartbeat ping URL**, and set the service to alert if
   it hears nothing for 15 minutes. Only the fact that the worker ran leaves the
   server.

8. **Digests.** Set a closing time for one outlet — a few minutes ahead, so you
   can watch it fire:

       UPDATE tbl_outlets SET eod_closing_time = '23:30:00' WHERE id = <outlet>;

   A wall-clock time read as **Africa/Lagos**. Outlets with
   `is_sales_enabled = 0` (Central Store) are skipped. Confirm the digest
   arrives once, with the day's takings and counts, and that a second worker run
   does not send it again.

9. **Only when all of that is proved**, consider turning test mode **off**. That
   is the moment real staff and customers start receiving messages. Everything
   before it is reversible by flipping a switch; this is the step that is not.

## Rollback

**The module is its own rollback.** Turning the master switch off stops every
hook at one cached lookup and stops the worker at its first line. It needs no
deploy, no migration and no downtime, and it is the right first move for
anything unexpected:

    UPDATE tbl_modules SET is_enabled = 0 WHERE module_key LIKE 'notif%';

Then remove the cron line if the worker itself is the problem.

**Rolling back the code** is a Deployment Manager deploy of the previous
commit, `e319baab`. Code updates never delete files on live, so the new files
(`cli.php`, `Ack.php`, `Notification_worker.php`, `notify_helper.php`, the two
views) stay behind. They are harmless — nothing references them once the older
code is back, and `cli.php` refuses any non-CLI request with 403.

**Do not roll back the migrations.** They are additive: three new tables, one
new permission, seven `tbl_modules` rows and a few columns on existing tables,
none of which the old code reads. Dropping them would be a destructive change to
recover from a non-destructive one. Each migration carries its own rollback SQL
in a comment if it is ever genuinely needed.

**Restoring the database backup from step 1 is the last resort**, not the first.
It would discard every sale, stay and payment taken since the deploy.

## After the release

Update `doc/ROCKBELL_HOLDBACK.md` with all 15 commits and all 5 migrations, so
the Goloviana/Rockbell difference stays written down.

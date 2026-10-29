# Notifications — runbook

How to switch the notifications module on for a site, and how to tell whether it
is working. Written for **Goloviana only**: Rockbell is frozen and has none of
this code, so it gets no cron entry. When Rockbell does take the module, add a
second entry by copying the one below and changing the host and the path.

Everything here ships **off**. Nothing sends until somebody turns it on, and
test mode is on by default so the first thing that sends goes to the tester, not
to the venue's customers.

---

## 1. Apply the migrations

From the **repository clone**, not the docroot:

    cd /home/citytjgo/repositories/goloviana
    mysql -u <dbuser> -p <livedb> < db/migrations/2026-10-29_01_notifications-n1.sql
    mysql -u <dbuser> -p <livedb> < db/migrations/2026-10-29_02_notifications-n3-dedupe.sql
    mysql -u <dbuser> -p <livedb> < db/migrations/2026-10-29_03_notifications-n5-ack.sql
    mysql -u <dbuser> -p <livedb> < db/migrations/2026-10-29_04_notifications-n5-escalation.sql

All four are additive and idempotent — re-running one changes nothing. Take a
verified backup first anyway.

**After the first migration, sign out and back in once.** The permission list is
built at login, so the Notifications menu entry will not appear for a session
that started before the migration ran.

## 2. Find the PHP binary

Never rely on a bare `php` in cron — cPanel's cron PATH often points at an older
build than the site runs.

    ls -d /opt/cpanel/ea-php*/root/usr/bin/php

Pick the one matching cPanel → **Select PHP Version** (8.3 → `ea-php83`) and
confirm it:

    /opt/cpanel/ea-php83/root/usr/bin/php -v

## 3. Add the cron entry — Goloviana

cPanel → **Cron Jobs**, Common Settings "Once Per Minute". One line:

    * * * * * RTD_CLI_HOST=goloviana.fraezyl.com /opt/cpanel/ea-php83/root/usr/bin/php /home/citytjgo/goloviana.fraezyl.com/cli.php notification_worker run >/dev/null 2>&1

In the cPanel form the five `* * * * *` fields are separate boxes, so paste only
the part from `RTD_CLI_HOST=` onwards into the Command box.

Four things to get right:

- **The path is the docroot**, not `/home/citytjgo/repositories/goloviana`. The
  repo clone has no live `database.php`.
- **`RTD_CLI_HOST` matters.** Messages raised by the worker — every escalation,
  and the end-of-day and low-stock digests — contain Acknowledge links. Inside a
  web request the application knows its own address; under cron there is no
  request, so without this the links come out as `http://localhost/ack/…` and go
  nowhere from a manager's phone. A named host also makes `cli.php` build the
  links as `https`.
- Confirm the docroot in cPanel → **Domains**, Document Root column, before
  saving.
- Clear the **Email** box, or keep the `>/dev/null 2>&1`, or you get one mail a
  minute.

`cli.php` does its own `chdir`, so no `cd` is needed.

### Belt and braces: set the address on the screen too

Settings → Notifications → **Public address of this site**:

    https://goloviana.fraezyl.com/

The screen setting wins over `RTD_CLI_HOST`. Set both: an environment variable
in a cron line is easy to lose the next time somebody edits the entry, and a
field on a screen is visible. With neither, the worker writes a warning to its
log rather than shipping a broken link quietly.

## 4. Confirm it is running

One line, overwritten every run — it cannot grow:

    cat /home/citytjgo/goloviana.fraezyl.com/application/logs/notifications/last-run.txt

Expected, with a timestamp inside the last minute or two:

    2026-10-29 15:40:27  nothing due

While the master switch is still off it reads `master switch off - nothing to
do`, which is **still proof that cron is firing**.

Detail, once messages start flowing:

    tail -20 /home/citytjgo/goloviana.fraezyl.com/application/logs/notifications/worker-$(date +%F).log

A state summary, which warns by itself when anything has been pending over an
hour:

    /opt/cpanel/ea-php83/root/usr/bin/php /home/citytjgo/goloviana.fraezyl.com/cli.php notification_worker status

## 5. Heartbeat (optional, recommended)

`last-run.txt` answers "is it running?" only when somebody looks. The heartbeat
answers it when nobody is looking, by putting the alarm somewhere this server
does not control — a dead worker cannot report that it is dead, and neither can
a dead cron.

Create a heartbeat check at any uptime service that gives you a URL to fetch
(no vendor is assumed or required — anything that accepts a plain `GET` works),
then paste that URL into Settings → Notifications → **Heartbeat ping URL** and
set the service to alert you if it hears nothing for, say, 15 minutes.

- Blank means off, which is how it ships.
- The URL is stored masked and is only readable with the `view_credentials`
  permission, because anybody holding it can silence the alarm by pinging it
  themselves.
- **Only the fact that the worker ran leaves the server.** No order, customer or
  money data.
- The ping is bounded at 3s to connect and 5s in total, so a monitoring outage
  cannot stall the queue.

## 6. Switching it on, in order

1. Settings → Notifications. Leave **test mode ON** and set a **test recipient**
   (your own email or phone).
2. Set the **Public address** (section 3).
3. Turn on the **master switch**, then the channels you want.
4. For SMS, set the provider key. Nigerian numbers need the **DND route** —
   Generic looks like it works and delivers nothing to them.
5. Use **Send a test now** on each channel and check it arrives.
6. Set up rules: who hears what, on which channel. For a Critical rule you can
   set **Escalate after (min)**, which turns the recipients into a ladder in the
   order you type them — the first is told at once, the next only if nobody has
   acknowledged in time.
7. Only when all of that is proven, turn **test mode off**. That is the moment
   real customers and staff start receiving messages.

## 7. When something has not arrived

In this order:

1. `last-run.txt` — is cron firing at all?
2. `notification_worker status` — is the master switch on, the channel on, and
   is there a driver for it? A channel with no driver yet leaves messages
   `pending` for ever, and the summary says so.
3. The Notifications screen shows the last 25 messages with their status, so
   "was it even queued?" does not need a database client.
4. The worker log for the day gives the provider's own refusal text. A
   provider returning HTTP 200 with a refusal in the body is recorded as
   **failed**, not sent.

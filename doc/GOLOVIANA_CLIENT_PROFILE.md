# Goloviana — client profile

**Read this file at the start of any task that mentions Goloviana, live testing
or deployment.**

Goloviana is a **real, trading client** with real guests, stays and sales.
Nothing here is a sandbox.

> **This file must never contain passwords, API keys, tokens or database
> credentials.** It records *where* things live and *which* credential is
> needed, never the credential itself. If a fact changes, update this file in
> the same commit as the change.

Last updated: 2026-10-30.

---

## 1. Who and what

| | |
|---|---|
| Client | Goloviana — a hotel with a bar |
| Live site | https://goloviana.fraezyl.com |
| Hotel Operations module | **ON** — 29 rooms, 16 checked in on 28 Oct |
| Outlets seen | Goloviana - Front Desk, Goloviana - VIP Bar, Central Store |

Hotel stays are kept **outside `tbl_sales`**, so the Detailed Sale Report can
look empty for Front Desk. That is expected, not a fault.

Rexlio is the **same codebase** as Rockbell. Every per-client difference —
company name, logo, address, outlets, counters, price-tier labels, menu — lives
in the database. Never in code.

## 2. Server

Goloviana and Rockbell share **one cPanel account and one home directory**.
Every path, cron entry and option file must be checked against the site it is
meant for.

| | |
|---|---|
| cPanel user | `citytjgo` |
| Host | `business126.web-hosting.com` |
| IP | `162.0.209.204` |
| SSH port | `21098` |
| Goloviana docroot | `/home/citytjgo/goloviana.fraezyl.com` |
| Repository clone | `/home/citytjgo/repositories/goloviana` — **outside** the docroot |
| Database option file | `~/rexlio_sync/goloviana.cnf` (Rockbell's is `online.cnf` — not interchangeable) |
| Database name | **[CONFIRM — see questions]** |
| PHP version | **[CONFIRM — expected 8.3]** |

The clone holds `.rexlio_deploypath`, containing the docroot path.
`.cpanel.yml` reads that file and **refuses to deploy if it is missing**, so a
pull can never carry one client's docroot onto another client's server.
cPanel's Git Version Control list does not show the clone, but it exists.

A GitHub fine-grained token, read-only on one repository, is stored inside the
clone's remote URL. **Its value is not recorded here.** Its expiry is tracked by
the owner.

The vendor installer (`/install/` and `install.zip`) was removed from the
Goloviana docroot on 28 Oct and verified as 404. `.cpanel.yml` now excludes
both, so a deploy cannot put them back.

### What `.cpanel.yml` does not deploy

Verified in the repository: `.git`, `.gitignore`, `.rexlio_deploypath`,
`install/`, `install.zip`, `pw.php`, `application/config/database.php`,
`assets/blueimp/REST_API*.json`, `application/config/testing/`, `images/`,
`uploads/`, `asset/excel/`, `application/logs/`, `application/cache/`, **`db/`**
and **`doc/`**, `_temp/`.

Two consequences worth remembering:

- **`doc/` is excluded**, so this file and the runbooks never reach the server.
- **`db/` is excluded**, so **migrations are run from the repository clone**,
  not from the docroot. The docroot has no `db/migrations` folder.

## 3. Current state

| | |
|---|---|
| Live version | `e319baab` — "Deploy: never copy the vendor installer to a docroot" |
| Deployed | 28 Oct 22:52 UTC (`build.txt` shows version and time) |
| Migrations applied | 17, per the Deployment Manager |
| Local `master` ahead of GitHub | **15 commits**, all the notification work |
| On Goloviana | **None of the notification module.** N1–N5 is local only. |
| SMS Setting screen credentials | **[CONFIRM — see questions]** |

The Deployment Manager deploys **GitHub's `master`**, so nothing reaches
Goloviana until it is pushed.

## 4. Rockbell is frozen

Rockbell is changing domain and wants no upgrades until that is done.

**Never deploy to, test on, or touch Rockbell's files or database.** See
`doc/ROCKBELL_HOLDBACK.md`, which lists every commit and migration that goes to
Goloviana but not Rockbell, and must be updated on every Goloviana deploy.

## 5. How deployment works

The Deployment Manager is a **local tool on the owner's machine** with a
Goloviana profile.

- **Complete Update** — live database backup first
  (`citytjgo_gol_<date>_<time>.sql`, newest 10 kept), then only the missing
  migrations, then the code.
- **Code only** / **Database only** — the halves on their own.
- **Never use "Refresh this computer from live".**

Migrations are additive and idempotent. Code updates never delete files on live.

Order for any deploy:

1. Confirm the backup saved.
2. Apply missing migrations.
3. Deploy code.
4. Check `build.txt` shows the new commit — add `?nocache=1` to defeat browser
   caching.
5. Smoke test.

Commits must be **pushed to GitHub** before the Manager can deploy them.

## 6. What Claude can and cannot do here

- **Cannot SSH to the live server** — the permission layer blocks it — and must
  never try to fabricate a session, enter a password, or type a credential.
- **Prepares commands** for the owner to run in their own terminal or in cPanel.
  Writes runbooks; does not run them.
- The owner may log in and let Claude in Chrome **read** pages. Reading is fine.
  **Do not click anything that writes live data without asking first.**
- **Never ask for keys or passwords in a prompt.** Provider keys (Termii, Brevo,
  Meta, Telegram) are entered by the owner on Settings → Notifications, where
  they are stored masked and readable only with `view_credentials`.
- **Do not run anything against Goloviana.**

## 7. Notification module rules for Goloviana

- After a deploy: the module is **OFF**, every channel is **OFF**, test mode is
  **ON**. A deploy must change nothing visible to staff.
- Before any channel is switched on, set the test recipient to the **owner's own
  phone and email**.
- Switch on **one channel at a time** and one or two events, checking the outbox
  after each.
- **Cron is added last**, in cPanel Cron Jobs, pointing at the **docroot** — not
  the repository clone, which has no live `database.php` — using the matching
  `ea-phpNN` binary. Confirm with the last-run marker.
- Because the two sites share one cPanel account and home folder, double-check
  every path, cron entry and option file names the right site.

See `doc/NOTIFICATIONS_RUNBOOK.md` for the cron line and the switch-on order,
and `doc/GOLOVIANA_FIRST_RELEASE.md` for the first deployment itself.

## 8. Known small facts

- The outlet address shows a double-encoded apostrophe
  (`People&amp;#039;s Bus Stop`). Cosmetic, pre-existing — for the tracker.
- 24 tracked `assets/cart_data_*.json` files are runtime junk, to untrack on a
  quiet day.
- The Detailed Sale Report returns rows only when at least one filter is set.
  Expected.
- Times are **Africa/Lagos (UTC+1)**. The notification module reads this clock
  explicitly rather than inheriting PHP's or MySQL's, which differ.

---

## Open questions — please confirm

These are recorded as unknown rather than guessed. Nothing that depends on them
should be run until they are answered.

1. **Database name** for Goloviana, as shown in the Deployment Manager profile.
   (Needed for the migration commands in the first-release runbook. Not the
   credentials — just the name.)
2. **PHP version** selected for `goloviana.fraezyl.com` in cPanel → Select PHP
   Version. Expected 8.3, which would make the cron binary
   `/opt/cpanel/ea-php83/root/usr/bin/php`. If it is 8.2 or 8.1 the cron line
   changes.
3. **SMS Setting screen** — are provider credentials already filled in on
   Goloviana's existing (pre-notification) SMS settings? Yes/no is enough; do
   not paste values. This decides whether the legacy inline receipt SMS path is
   live today, which changes what switching the module on will alter.
4. **Rockbell's deployed commit**, so `ROCKBELL_HOLDBACK.md` can state the
   difference exactly rather than approximately.
5. **Is the install record correct on Goloviana?** Run from the docroot:
   `php -r '$j=json_decode(file_get_contents("assets/blueimp/REST_API_I.json"),true); echo str_rot13($j["installation_url"]),"\n";'`
   It should print Goloviana's own URL. If it does not, the daily digests will
   not run from cron — see `doc/NOTIFICATIONS_RUNBOOK.md` section 4a.

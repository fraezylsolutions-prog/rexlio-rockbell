# Go-live runbook — Hotel Operations add-on (and everything committed since the last deploy)

Prepared 2026-09-16 for the owner to run. Claude runs nothing against live (see `DEPLOYMENT.md`).
Local HEAD at preparation: `5fdbfbd6` (H4). Replace `<user>`, `<livedb>`, `<cpaneluser>` with the live values.

Order, as always: **backup → preflight → migrations (each prints PASS) → code → switch on → smoke test.**

## 0. Before you start (on live, over SSH)

    cd /home/<cpaneluser>/repositories/rexlio
    git pull                                   # the migration files must be present locally
    git log --oneline -1                       # expect 5fdbfbd6 feat(hotel): H4 ...

## 1. Backup — not optional

    mkdir -p ~/backups
    mysqldump -u <user> -p --single-transaction --routines --triggers <livedb> \
      > ~/backups/rexlio_$(date +%Y%m%d_%H%M%S).sql
    ls -lh ~/backups/rexlio_*.sql | tail -1    # must be a real size, not 0 bytes

## 2. Preflight — which migrations does live already have? (read-only)

    mysql -u <user> -p <livedb> < db/migrations/preflight_status.sql

One row per migration, `APPLIED` or `MISSING`. Apply **only the MISSING ones, top to bottom** — the
hotel add-on needs the last two, but the code you are about to deploy also contains the Table-First
Flow and Part B, which need the 2026-09-15 ones if live does not have them yet.

## 3. Migrations — one at a time, each must print PASS

Every script is additive and re-runnable. If any prints `FAIL`, **stop**: live is still consistent
with the old code; nothing else has changed yet.

    # only those the preflight listed as MISSING, in this order
    mysql -u <user> -p <livedb> < db/migrations/2026-09-15_01_device-tags.sql
    mysql -u <user> -p <livedb> < db/migrations/2026-09-15_02_running-order-versioning.sql
    mysql -u <user> -p <livedb> < db/migrations/2026-09-15_03_perf-indexes.sql
    mysql -u <user> -p <livedb> < db/migrations/2026-09-15_04_table-first-flow.sql
    mysql -u <user> -p <livedb> < db/migrations/2026-09-15_05_beverage-values.sql
    # the hotel add-on - 01 before 02
    mysql -u <user> -p <livedb> < db/migrations/2026-09-16_01_modules.sql
    mysql -u <user> -p <livedb> < db/migrations/2026-09-16_02_hotel-schema.sql

    # confirm: every row APPLIED
    mysql -u <user> -p <livedb> < db/migrations/preflight_status.sql

What 01 and 02 do to live: create `tbl_modules` (one row, `hotel` = OFF) and the five `tbl_hotel_*`
tables; add permission rows (`modules`, `hotel_rooms`, `hotel_front_desk`, `hotel_housekeeping`); add a
**Housekeeping** role per company; grant Admin and Manager everything hotel, Cashier the front desk basics.
No existing table or row is altered. The add-on stays **OFF** and invisible until step 5.

## 4. Deploy the code

cPanel → Git™ Version Control → the rexlio repository → **Update from Remote** → **Deploy HEAD Commit**
(the tasks in `.cpanel.yml` copy the app into the docroot and skip `.git`, `pw.php`, live's
`database.php`, uploads). Or over SSH:

    cd /home/<cpaneluser>/repositories/rexlio && git pull
    # then Deploy HEAD Commit in cPanel

Then, in a browser, hard-refresh the POS once (Ctrl+F5) — the changed scripts and styles carry new
`?v=` versions, but a stale tab may still hold the old ones.

## 5. Switch the add-on on

1. Sign in as **Admin** → Settings → **Modules**. Hotel Operations must show **OFF** with a *Switch on*
   button. If it shows *Not installed* or *Tables missing*, a migration from step 3 did not run — go back.
2. **Switch on.** The **Hotel Operations** group appears in the sidebar on the next page load; no sign-out.
3. Managers, cashiers and housekeeping staff pick up their new hotel permissions **at their next login**
   (permissions are snapshotted at login). Admin needs nothing.

## 6. Smoke test

- POS: sale screen loads, a category filters, an item adds, one real order end to end (unchanged
  behaviour from the earlier stages).
- Hotel: Room Types → add one; Rooms → add one room; Front Desk → the card shows; check a test guest
  in → check out → the Housekeeping board (Hotel Operations › Housekeeping Board) shows the cleaning
  task → Start → Finish → Verify → room shows *Inspected*. The test stay stays in the Stay Log as history.
- Create a user in the **Housekeeping** role (no POS permission), assign the outlet, sign in as them on the
  tablet: they must land on the Housekeeping board directly.

## 7. If something goes wrong

| Stage | Action |
|---|---|
| A migration printed FAIL | Stop. Nothing else has changed. Send the output; live keeps running on the old code. |
| Code deployed, something off in the POS | Redeploy the previous commit (`git revert <sha>` locally → push → Deploy HEAD). The migrations are additive and can stay. |
| Hotel screens misbehave | Settings › Modules › **Switch off** — everything hotel disappears in one click; no data is lost, the POS is untouched. |
| Anything worse | Restore the step-1 dump. |

Nothing in this deploy is destructive: no column or table is dropped or renamed, no existing row is
changed. The backup is the safety net you should not need.

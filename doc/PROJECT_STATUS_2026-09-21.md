# Rexlio POS — project status, 2026-09-21

Written for hand-over to another session. Every statement is tagged:

- **VERIFIED** — read from the repo, the local database, or a read-only probe of the live host *today*.
- **DOCUMENTED** — taken from a file in `doc/` or the task tracker; not re-tested today.
- **INFERRED** — my conclusion from evidence; the evidence is stated so it can be re-checked.

Local repo: `C:\wamp64\www\rexlio` (WampServer). Local DB: `rexlio` (MySQL 8.4.7). Scratch DB used for
every test run: `rexlio_scratch`. Live: `https://rockbell.fraezyl.app` (cPanel/LiteSpeed).

---

## 1. Git state

**VERIFIED** (`git branch -a -v`, `git log`, `git status`, `git remote -v`, 2026-09-21):

- Branch: `master`. It is the **only** local branch. **No remote is configured** (`git remote -v` prints
  nothing), so nothing has ever been pushed from this repo — see §3 for how live got its code.
- HEAD: `e5481bd7d01036c7c156aea8e4f9a0a51916db81` — *feat(pos): waiter auto-logout after order placement
  is a Settings > Modules switch (default ON)* — committed 2026-09-20 22:11 +0100.
- No stash entries. `git count-objects` warns about one stray `.git/objects/35/tmp_obj_*` file (harmless
  leftover of an interrupted write; `git gc` would clear it).

### Uncommitted / untracked right now
```
 M assets/cart_data_.json, cart_data_45..48.json      <- runtime files the POS writes on every cart change
?? assets/cart_data_50..56.json                       <- same, new counters
?? print_server.zip                                   <- untracked archive in the repo root; not examined
```
Nothing else is modified. The `cart_data_*.json` files are tracked from the vendor baseline but are
runtime state — they churn whenever a till is used; recommend adding `assets/cart_data_*.json` to
`.gitignore` and `git rm --cached` them. `print_server.zip` should be moved out or ignored; I did not
open it.

### Recent history (`git log --oneline`, newest first — the full history is 54 commits, baseline 2026-08-28)
```
e5481bd7 feat(pos): waiter auto-logout after order placement is a Settings > Modules switch (default ON)   2026-09-20
ed78dbb4 feat(pos): P2 - placed items visible but locked on Modify without pos_26                            2026-09-19
9f9644cc feat(tables): Order Details as the seventh table-card action - read-only, never the cart           2026-09-19
d730330f fix(pos): the tables panel always starts from an empty cart - irClearCart() on every entry         2026-09-19
3457253b docs(hotel): guide wording for the rate; runbook expected HEAD                                      2026-09-17
b38c1746 docs(hotel): owner's guide covers value tracking, dashboards and the seven reports; runbook ...    2026-09-17
342837a0 feat(hotel): H11 - Room Turnaround, Out of Order and Room Status History reports
96cd1d18 feat(hotel): H9 - Housekeeping productivity report
62741c19 feat(hotel): H8 - Stays report with value, variance flag and subtotals by period / category / staff
d3593321 feat(hotel): Front Desk period value gets a clock-time window
31a0306d fix(migrations): preflight covers the H5/H6 migrations; the hotel-schema check tolerates later-added functions
2c9cf961 feat(hotel): H10 - Rooms Checked-in dashboard card, Front Desk live analytics row with period value
2638c1e0 feat(hotel): H7 - Occupancy report with RevPAR, per period and per room category
f076ece7 feat(hotel): H6 - Value Generated report by room category, Day/Week/Month/Year
120a1d78 feat(hotel): H5 - stay value: rate x nights at check-in, variance at check-out, manager edits
95219084 fix(ui): flash banners readable everywhere, success banners auto-dismiss; Front Desk toasts
acdab43b feat(hotel,pos): Front Desk in the Panel menu and header quick links; Tables button replaces the old table picker   2026-09-16
6ca334da docs(hotel): go-live runbook and read-only migration preflight
5fdbfbd6 feat(hotel): H4 - module switch end to end, half-installed guard, owner's guide
dd5532ca feat(hotel): H3 - Housekeeping board, task workflow, permission-based landing
e1ffac61 feat(hotel): H2 - Front Desk board, check-in / check-out / out of order, stay log
c74a02b3 feat(hotel): H1 - schema, permissions, Housekeeping role, Room Types and Rooms
08257121 feat(modules): H0 - business-wide module switches; Hotel Operations add-on shell
7db5c634 feat(pos): Food / Drinks labels, Search button on the category rail, search clears after a pick   (Part B)
f78ea2b8 feat(tables): Stage 5c - the panel is the POS landing for every role; retire Monitor/tables
b06bf56e feat(tables): Stage 5b - one tables screen: filters, counts, free tables, view-only cards, switch outlet
fc0c3cb0 feat(tables): Stage 5a - myTablesAjax as the single data source; multi-outlet waiter login
10f3ad90 feat(tables): Stage 4 - Monitor Table Status for higher roles: filters, order value, permission-gated actions
b958d83e feat(tables): Stage 3 - six-action sheet on the panel, in place
ccb3fe10 feat(tables): Stage 2 - waiters land on the "My tables" panel with "+ New Table"
2eb2b21b feat(tables): Stage 1 - table-first foundation: migration, per-waiter auto tables, panel data, auto release
6a302703 fix(pos): Stage 0 - counter price-tier lock reaches waiters; offline-placed orders sync to the kitchen
ed225916 feat(pos): cross-till running orders - "Open here" adoption with server-side versioning (Step 2)
d52bc26c feat(pos): waiter auto-logout after a placed order
a85a5a70 feat(pos): notice when the till print service is unreachable; self-closing print popups
40eb3b5f feat(dashboard): time-of-day filter, Running/Completed Order Value cards, mobile layout and quick-action panel
48449e45 docs: tracker updates - launch batch, register force-close, reports, live reset, new-host procedure
b4d0450a perf(pos): load the DataTables/pdfmake bundle on demand; make the 7 s poll and load-time check asynchronous
2c2fd246 perf: login/POS/Running Order/Table Status - remove N+1 loops, memoize company info, add hot-path indexes, shrink the POS page
a778ee9a feat(pos): order-number uniqueness across tills via server-issued device tags
65a87486 chore: add PASS/FAIL verification to the two pending migrations
7d5ade0e feat: time-of-day filter on the Detailed Sale Report
0a536351 feat: waiter filter on the Sales by Category report
60f8813f fix: running orders were invisible to every role except Admin
3f3b5940 docs: log the register force-close and category rail work in the tracker
f76a2106 feat: rebuild the POS category rail with depth and larger labels
ef75e0ec feat: surface Manage Registers in the sidebar and header                                            2026-09-04
84a0f165 feat: Admin Register Management - force-close a register on someone else's behalf
9a34e4a7 fix: restore customer_name dropped by the v3.7 site-3 replacement
89e8b89e tracker: log both waiter order-placement crashes (rows 73, 78)
48936b4e fix: second waiter crash - .select2('data') on the hidden waiter field
a968d3e1 fix: waiters could not place orders on ANY order type - TypeError killed handler               2026-09-03
8237fcea gitignore: also exclude per-environment database.php overrides
7fa208f9 Initial commit: Rexlio POS deployment baseline                                                    2026-08-28
```

---

## 2. Database state (local `rexlio`)

### Preflight — **VERIFIED**, verbatim output of `mysql -uroot rexlio < db/migrations/preflight_status.sql` on 2026-09-21
```
migration	status
2026-09-03_01_register-force-close	APPLIED
2026-09-08_01_view-all-running-orders	APPLIED
2026-09-15_01_device-tags	APPLIED
2026-09-15_02_running-order-versioning	APPLIED
2026-09-15_03_perf-indexes	APPLIED
2026-09-15_04_table-first-flow	APPLIED
2026-09-15_05_beverage-values	APPLIED
2026-09-16_01_modules	APPLIED
2026-09-16_02_hotel-schema	APPLIED
2026-09-17_01_hotel-stay-value	APPLIED
2026-09-17_02_hotel-reports-permission	APPLIED
2026-09-19_01_pos-edit-placed-items	APPLIED
2026-09-20_01_waiter-auto-logout-switch	APPLIED
```
All 13 migration files in `db/migrations/` are applied locally; **none exists as a file without having
been run** (VERIFIED: the directory holds exactly these 13 plus `README.md`, `_TEMPLATE.sql`,
`preflight_status.sql`). `rexlio_scratch` also has all 13 (VERIFIED). Live: unknown from here — §3.

Other local facts (VERIFIED): `tbl_modules` = `hotel` ON (since 2026-09-16 06:55), `waiter_auto_logout` ON
(row inserted 2026-09-20, never toggled). Local data is a small dev set: 3 sales, 15 users, 197 food
menu rows, 1 hotel room, 0 stays.

### Backups
Two locations exist, both git-ignored (VERIFIED: `.gitignore` lines 53 and 57).

`db/backups/` (inside the repo):
```
pre-force-close-migration_20260904_050116.sql        2026-09-04
pre-view-all-running-orders_20260908_172701.sql      2026-09-08
```
`C:\Users\FRAEZYL\Desktop\APP DEV\rexlio_backups\` (where this session's dumps went):
```
rexlio_full_20260825_212644.sql                      2026-08-25
rexlio_before_hotel_20260916_065223.sql              2026-09-16 06:52   (before 2026-09-16_01 + _02)
rexlio_before_h5_20260916_185019.sql                 2026-09-16 18:50   (before 2026-09-17_01 + _02)
rexlio_before_p2_20260919_184940.sql                 2026-09-19 18:49   (before 2026-09-19_01)
rexlio_before_p4_20260920_225847.sql                 2026-09-20 22:58   (before 2026-09-20_01)
```
**Migrations with NO matching pre-migration backup of the local DB:** the five `2026-09-15_*` scripts
(device-tags, running-order-versioning, perf-indexes, table-first-flow, beverage-values). INFERRED from
the file listing; if a dump was taken elsewhere on 2026-09-15 it is not in either folder. All five are
additive (indexes, columns with defaults, permission rows) and every one has been running locally since.
Everything from 2026-09-16 onward has a backup taken minutes before it was applied.

---

## 3. Live vs local

**The project is NOT local-only.** Live has been in real business use since 2026-09-08 (DOCUMENTED:
`doc/Rexlio_POS_Project_Description.md` line 11; the "Live Launch Batch 2026-09-03 → 09-08" section).

### What is on live right now — VERIFIED by read-only GETs of public static files on 2026-09-21, matched byte-for-byte against git blobs
| File on live | Matches commit | Meaning |
|---|---|---|
| `frequent_changing/js/pos_script_v7.3.js` (1,013,919 B) | blob first committed in `acdab43b` (2026-09-16); unchanged until `d730330f` | live has the Tables-button / hotel-links POS script but **not** P1/P3/P2/P4 |
| `frequent_changing/js/hotel_front_desk.js` | `d3593321` (Front Desk clock-time window) | hotel front desk JS is deployed at its H10+ version |
| `assets/dist/css/custom/hotel.css` | `342837a0` (H11) | the last hotel css change is on live |
| `db/migrations/2026-09-17_02_hotel-reports-permission.sql` | present (HTTP 200) | |
| `db/migrations/2026-09-19_01_pos-edit-placed-items.sql` | absent (same response as a non-existent file) | |
| `frequent_changing/css/rexlio_theme.css` | size 72,511 B = the `3457253b` version's size; content **not** verified (my download was cut off) | |

**INFERRED:** live carries the code of commit **`3457253b`** (or `b38c1746` / `342837a0` — all three
ship identical application files; only docs differ), i.e. everything up to and including the Hotel
add-on H0–H11 and the 2026-09-17 docs, deployed at some point **on or after 2026-09-17**. The four
commits of 2026-09-19/20 (`d730330f`, `9f9644cc`, `ed78dbb4`, `e5481bd7`) are **not** on live.

**What I could NOT verify about live** (needs the owner, over SSH/cPanel):
- whether the migrations were applied on live's database (`preflight_status.sql` run against live), and
  whether the Hotel module has been switched ON there. The runbook `doc/GO_LIVE_HOTEL_2026-09-16.md`
  is the procedure; nothing in the repo records that it was executed.
- how the deploy was done. `.cpanel.yml` excludes `db/` and `doc/`, yet `db/migrations/*.sql` are
  publicly readable on live — so the last deploy did **not** go through the `.cpanel.yml` tasks (a
  manual upload, or an rsync without those excludes). Low sensitivity (migration scripts contain no
  credentials) but it means the documented deploy path is not the one in use.

**Docs are stale on this point.** Every stage entry in `doc/HOTEL_ADDON_PLAN.md`,
`doc/TABLE_FIRST_FLOW_PLAN.md` (Stages 0–5c) and the Roadmap says "Local only; not on live". For
everything up to `3457253b` that is no longer true. The four 2026-09-19/20 entries (P1–P4) are still
correctly "not on live".

### Two live-host findings that need action (VERIFIED today)
1. **`https://rockbell.fraezyl.app/install/` answers HTTP 200 with the vendor installer page**
   (`<title>iRestora PLUS`). `doc/DEPLOYMENT.md` says the installer must be blocked or removed on a live
   host. It is reachable.
2. Live returns **HTTP 500 for any non-existent `.php` path** (tested with a random filename). Not a
   defect in itself, but it means "does `pw.php` exist on live?" cannot be answered from outside — the
   testing front controller (an auth bypass when the testing config is present) returns the same 500.
   The owner should confirm `pw.php` and `application/config/testing/` are absent from the docroot.
   (`application/config/testing/database.php` itself answers 403, which is the normal `application/`
   block, not proof of absence.)

Everything committed to live so far was run by the owner; this session never touched live beyond the
read-only GETs above (per `DEPLOYMENT.md`).

---

## 4. Feature state

Tests named below were run on `rexlio_scratch` through the `pw.php` testing entry with seeded sessions,
plus real-browser checks; "regression" = the POS/tables and hotel suites (5a 55, 5b 22, 5c 28, deep
link 16, Part B 18+21, menu/POS 6, P2 12, P4 21+6, h0–h11) — **all green on 2026-09-20/21** (VERIFIED,
re-run this session).

| Area | Commits | Status | Tested how | Known gaps / TODOs inside it |
|---|---|---|---|---|
| **Live Launch Batch** (waiter crash fixes, register force-close, running-order visibility, category rail, report filters, live data reset) | `a968d3e1` … `ef75e0ec` (2026-09-03/04) | Done, **on live** since 2026-09-08 (DOCUMENTED) | Per tracker rows; tracker row 78 documents that round 1 of the waiter fix was verified against the wrong fixture shape | Voided sales still count as revenue in two reports (DOCUMENTED, unfixed); Google Map iframe on the frontend likely broken (DOCUMENTED, unverified) |
| **Performance overhaul** | `2c2fd246`, `b4d0450a` (+ migration 2026-09-15_03) | Done/committed; INFERRED on live (part of the `3457253b` snapshot) | Local timings in `doc/PERFORMANCE_2026-09-15.md` §7; live curl timings on public endpoints | §5 of that doc: latency is hosting distance (Chicago↔Nigeria), a lean layout for the two Monitor screens is a follow-up "if the owner's till measurement confirms it"; session GC depends on the host. Doc §6 header still says "not committed, not on live" — stale |
| **Cross-till running orders** (device tags, "Open here" adoption, versioning) | `a778ee9a`, `ed225916` | Done/committed; INFERRED on live | HTTP + IndexedDB harness per `doc/CROSS_DEVICE_ORDERS_ASSESSMENT.md` §4.4/§5.3 | Doc §5.3 says "not yet committed" — stale (commit `ed225916` exists). Deep-link limitation: five of the six running-order actions still open the POS |
| **Table-first flow** Stages 0–5c | `6a302703` … `f78ea2b8` | Done/committed; INFERRED on live | Suites 5a/5b/5c/deep-link; owner's real-login checklist in `TABLE_FIRST_FLOW_PLAN.md` §7 — **no record that the owner ran it** | Stage 0's kitchen sync for offline-placed orders is built (unit 12/12); nothing else open in the plan |
| **POS Sale Screen Part B** (Food/Drinks, Search button) | `7db5c634` (+ migration 2026-09-15_05) | Done/committed; INFERRED on live | `partb/test_partb.php` 18, `test_search.js` 21 | none recorded |
| **Hotel Operations add-on H0–H11** (modules framework, rooms, front desk, housekeeping, value tracking, 7 reports, dashboards) | `08257121` … `342837a0` + docs `b38c1746`, `3457253b` (+ migrations 2026-09-16_01/02, 2026-09-17_01/02) | Done/committed; **code on live (VERIFIED), DB/switch state on live unknown** | h0–h11 suites, browser checks per `HOTEL_ADDON_PLAN.md` log | Explicitly out of scope: reservations, guest folio, guest ID capture. Local DB: module ON, 1 room, 0 stays. Tracker row 54 still says "Hotel add-on: Not started" — stale |
| **U1 flash banners** | `95219084` | Done/committed; INFERRED on live | browser | — |
| **POS fix P1** — tables panel always clears the cart | `d730330f` | Done/committed; **not on live** | scratch reproduction before/after in a real browser; regression | Business rule accepted: opening the panel just to look also discards an unsaved cart |
| **POS fix P3** — Order Details as 7th sheet action | `9f9644cc` | Done/committed; **not on live** | browser + deep-link suite | — |
| **POS fix P2** — placed items locked on Modify without `pos_26` | `ed78dbb4` (+ migration 2026-09-19_01) | Done/committed; **not on live** | 12-check suite, Cashier + Manager browser runs | `pos_26` granted Admin+Manager only; needs sign-out/in after the migration. Edit of a placed row by a Manager was proven only to pass the guard (the synthetic fixture item had no `window.items` entry) |
| **Waiter auto-logout switch (P4)** | `e5481bd7` (+ migration 2026-09-20_01) | Done/committed; **not on live**; applied to local `rexlio` 2026-09-20 | 21-check HTTP suite incl. missing-row default, 6 JS checks running the real `irWaiterAutoLogout` (byte-identical to HEAD), Admin browser flip | Flip applies on the next sale-screen load, not to a POS tab already open |

### Still open in the planning docs (DOCUMENTED, verified present in the files today)
- `TABLE_FIRST_FLOW_PLAN.md` §7 — owner's end-to-end checklist with real logins: not recorded as done.
- `PERFORMANCE_2026-09-15.md` §5 — hosting move (biggest lever, non-code); lean Monitor layout pending a till measurement.
- `Rexlio_POS_Modification_Roadmap.md`: Phase D still-open items (FR/ES/AR translations for the renames,
  `setHeightInComponent()` dead code — the footer grid breaks at a 7th button, the VIP/Club "layered
  interaction" question); Phase F deferred: F6 transfer gaps (in-transit stock invisible), variation
  inheritance of the oversale policy, Stock Value columns, inventory stock-VALUE figure mathematically
  wrong; Phase G per-counter reporting not started; queued base-product defects: `payment_method_id`
  written as Cash for every sale, customer credit (negative due) not surfaced, `updatePrice()` assigns new
  menus to Central Store, voided sales counted in two reports; waiter's own order history deferred.
- Tracker "Next": DataTables Buttons 1.5.2→1.7.0 for 24 report views; bulk item upload / stock-qty
  update / List Food Menu search not started; Food Menu bulk upload + bulk price tools "done on scratch,
  **pending live permission**"; Rockbell menu file import **blocked** (needs a transform step); slick
  carousel init failure on report screens; category icon filenames with spaces; mobile category rail is
  `:hover`-only; register can still be opened at Central Store via direct URL (flagged, not built).
- `GO_LIVE_HOTEL_2026-09-16.md` — runbook exists and now lists all 13 migrations; **execution on live
  not recorded**. Its "expected HEAD" line still names `342837a0`.

---

## 5. Queued / not started — VERIFIED against the repo

- **Push Notifications + Mobile API** — no code. `git ls-files` finds no service worker, manifest, FCM/push
  code or new API controller; the only "API" is the vendor's own `IR_api.php` (untouched since the
  baseline) and the vendor `Twilio/` library. The only thing that exists is the **requirement** recorded
  in the Roadmap (2026-09-16): both must be independently Super-Admin-toggleable at runtime — and the
  toggle framework they will use (`tbl_modules` / `irModuleRegistry` / `irModuleEnabled`, with the P4
  `kind`/`default` extension) is built and in use by the hotel add-on and the auto-logout switch.
- **Owner's Mobile App** — no code, not scoped (tracker row 50, Roadmap "Separately Planned").
- **Accounting Module / Dashboard** — no code and, as far as the repo and tracker go, **no scoping entry
  at all** under that name. The nearest items are "Dashboard Enhancement" (tracker row 51, not scoped)
  and the delivered dashboard commit `40eb3b5f`. If "Accounting Module Dashboard" is a new request it
  has not been written down anywhere in `doc/`.
- Also not started: WhatsApp end-of-day notifications, Phase G, "Branding & License Cleanup" (tracker row 55).

---

## 6. Open questions / risks

**Live host (highest priority, VERIFIED today)**
1. `/install/` is reachable on live — remove or block it (DEPLOYMENT.md already says so).
2. The deploy path in use is not `.cpanel.yml` (`db/` is on live). Owner to confirm the method and that
   `pw.php` + `application/config/testing/` are not in the docroot.
3. Unknown whether live's DB has the 2026-09-15…09-17 migrations and whether Hotel is switched on. If the
   code is on live but the migrations are not, the guards hold (the Modules screen shows "Not installed"
   / "Tables missing", `irModuleEnabled` fails closed) — but P2's `pos_26` and P4's switch are not on live
   at all, so nothing there depends on the two newest migrations.
4. The four newest commits are not on live. Before the next deploy the owner runs the runbook order:
   backup → preflight → the MISSING migrations (each prints PASS) → code → hard-refresh the POS →
   sign-out/in for `pos_26`.

**Repo hygiene**
5. `assets/cart_data_*.json` are tracked runtime files (permanent noise in `git status`); `print_server.zip`
   untracked in the root; one stray object file in `.git`.
6. Docs carry stale "not on live / not committed" markers (see §3) and tracker row 54 says the hotel
   add-on is not started. Worth a single docs pass.
7. No git remote: the only copy of the history is this machine. `DEPLOYMENT.md` describes the GitHub /
   cPanel-remote options; neither has been set up (VERIFIED).

**Behaviour decisions that were made and could be revisited**
8. P1: the tables panel discards an unsaved cart on *every* open, including "just looking". Accepted by
   the owner on 2026-09-19; a toast reports it.
9. P2: `pos_26` is Admin + Manager only; Cashier and Waiter cannot change placed items. Reusing `pos_7`
   was rejected because live grants it to Waiter and not Cashier and the waiter app bypasses it.
10. P4: the switch lives under Settings › Modules ("POS behaviour" group) rather than a field on the
    vendor Settings form, and takes effect on the next sale-screen load rather than mid-session.
11. Hotel value tracking: value counted at check-in, option C variance at check-out, edit restricted by
    the `hotel_front_desk › value` permission; informational only (never `tbl_sales`, registers or invoices).

**Test-harness caveats (so the next session does not over-trust green runs)**
12. Every suite runs against `rexlio_scratch` with seeded sessions, not real logins. Two harness drifts
    were fixed this session (seeded sessions lacked `pos_1`; h2's hard-coded expected check-out date had
    passed). The hotel suites h2–h11 each need `hotel/seed_h1.php` run immediately before them. The
    scratch harness lives in this session's temp scratchpad
    (`C:\Users\FRAEZYL\AppData\Local\Temp\claude\C--Users-FRAEZYL-Desktop-APP-DEV\0fc53318-…\scratchpad\`),
    **not in the repo** — it will be lost when that folder is cleaned. If the suites are worth keeping,
    copy them into a git-ignored `tests/` folder.
13. `application/config/testing/database.php` is back on `rexlio_bulk` (VERIFIED); it must point at
    `rexlio_scratch` while suites run.

**Deferred by explicit decision (DOCUMENTED)**
14. Reservations / guest folio / guest-ID capture (hotel); F6 transfer gaps; Stock Value columns;
    waiter's own order history; inline versions of Modify/Invoice/Bill on the running-order screen
    (order state is device-bound IndexedDB); per-client tier labels (a Nuvora concern).

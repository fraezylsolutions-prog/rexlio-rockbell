# Hotel Operations add-on — plan and verification log

Started 2026-09-16. Scope as decided earlier in the project and reconfirmed today.

## 1. Scope
- **In:** Housekeeping (room cleaning / status tracking, on the Kitchen Panel pattern) and Front Desk
  basics (room status board, check-in / check-out log).
- **Out (explicitly):** Reservations (date-range availability) and Guest Folio (room charges joined
  with restaurant / bar billing). Guest ID document capture: out for this phase.
- **Principle:** an isolated add-on. New tables only (`tbl_hotel_*`, `tbl_modules`); no column on any
  existing table changes. Permissions are registered as rows in `tbl_access` / `tbl_role_access`,
  exactly as every screen of the product does (data, not schema).
- **iLodge:** no connection in code — nothing in the application references it, no shared tables, no
  API. The live host merely sits under the same domain family. Kept independent on purpose; the two
  things that would bridge the products (reservations, folio) are the two out of scope.

## 2. Decisions (2026-09-16)
| Question | Decision |
|---|---|
| Toggle scope | Business-wide switch, no per-outlet list. Rooms belong to an outlet; screens scope to the checked-in outlet. |
| Status vocabulary | occupancy: `vacant` / `occupied` / `out_of_order`; housekeeping: `clean` / `dirty` / `in_progress` / `inspected`; tasks: `pending` → `in_progress` → `done` → `verified`. |
| Check-in on a dirty room | **Warn**, not block. |
| Housekeeping role | Seeded (panel + own tasks). Manager: assign + verify. |
| Guest ID capture | Out. |

## 3. Design
### Module switch (H0 — reused by every later add-on)
- `tbl_modules` — one row per module (`module_key`, `is_enabled`, `settings` JSON, `updated_by`, `updated_at`).
- `irModuleEnabled($key)` reads it **per request** (one query, static cache) and is never stored in the
  session — permissions are snapshotted at login, this deliberately is not — so Settings > Modules takes
  effect on the next request: no re-login, no deploy.
- `irRequireModule($key)` in a module's controller constructor refuses every method while off (flash +
  profile). The sidebar group is not rendered at all while off. Nothing is deleted on OFF.
- Settings > Modules screen (`Setting::modules`, `views/setting/modules.php`): one switch per module in
  `irModuleRegistry()`, "last changed by / at", audit-logged. Gated by a new `tbl_access` row `modules`
  (function `update`), Admin by default; the row's id is looked up by name (`irAccessModuleId`) because
  migrations do not fix ids.

### Data model (H1)
- `tbl_hotel_room_types` — name, informational `base_rate` (display only, never billed).
- `tbl_hotel_rooms` — outlet, type, number, floor, `occupancy_status`, `housekeeping_status` (two
  independent columns: "occupied and dirty" is a real state), notes.
- `tbl_hotel_room_status_log` — every change of either status: from → to, user, note, time.
- `tbl_hotel_housekeeping_tasks` — room, type (cleaning / turndown / inspection / maintenance), status,
  assigned_to (null = pool), created_by, started_at / done_at, verified_by, note. Kitchen Panel mapping:
  task ≙ order line, room ≙ order card, Start / Done ≙ Started Cooking / Done, Verify ≙ served.
- `tbl_hotel_stays` — room, guest name / phone, adults, children, checkin_at, expected_checkout,
  checkout_at, checked_in_by / out_by, status (`in_house` / `checked_out` / `cancelled`), notes, free-text
  `reference` (a receipt or booking number typed by staff — not a billing bridge). One `in_house` stay per room.
- Automation: check-out → room `dirty` + a cleaning task; task done → `clean`; verify → `inspected`;
  check-in requires `vacant` and warns when not `clean` / `inspected`.

## 4. Stages
| Stage | Scope |
|---|---|
| H0 | Module framework: `tbl_modules`, helpers, Settings > Modules, sidebar / controller gating, Hotel controller shell |
| H1 | Schema + permissions + Housekeeping role (one migration); Room Types and Rooms CRUD |
| H2 | Front Desk board, check-in / check-out / out-of-order, status log, stay log + export |
| H3 | Housekeeping board (Kitchen Panel pattern): poll, My tasks / Pool, Start / Done / Verify, auto-task, staff landing |
| H4 | Toggle end to end, docs, owner's checklist, per-stage commits |

## 5. Log

### H0 — module framework (2026-09-16). Local only; not on live.
Migration `2026-09-16_01_modules.sql` (PASS on scratch, idempotent on re-run): `tbl_modules` with the
`hotel` row OFF; access row `modules` → `update`, granted to Admin. Helpers `irModuleRegistry`,
`irModuleRows`, `irModuleEnabled`, `irModuleSet` (audit-logged), `irRequireModule`, `irAccessModuleId`.
`Setting::modules` + view; sidebar item under Settings (token looked up by name); a "Hotel Operations"
sidebar group rendered only while ON; `Hotel` controller shell whose constructor calls
`irRequireModule('hotel')` before anything else. 16 language keys in all four files.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch: while OFF `Hotel/index` refused and no Hotel group in the sidebar; Modules screen 200 for Admin (state, buttons, token), refused for Manager and for a Cashier POST (row unchanged); unknown key rejected; Admin switch ON → row updated with who/when, screen says ON, `Hotel/index` 200 **on the very next request for Admin and for a Waiter whose session predates the switch** (per-request read), Hotel group rendered, audit row written; switch OFF → refused again on the next request, group gone; other screens untouched | **16/16** |
| Real browser: Modules screen rendered with the live CSS | PASS |
| Regression: Part B 18/18, tables panel 5a 55/55, 5b 22/22, 5c 28/28, deep-link 16/16 | green |

Note: CodeIgniter answers a POST-then-redirect with 303 (GET redirects with 307) — the suite accepts both.

### H1 — schema, permissions, Housekeeping role, Rooms CRUD (2026-09-16). Local only; not on live.
Migration `2026-09-16_02_hotel-schema.sql` (PASS on scratch, idempotent on re-run): the five
`tbl_hotel_*` tables; access rows `hotel_rooms` (add/update/view/delete), `hotel_front_desk`
(view/checkin/checkout/status), `hotel_housekeeping` (view/update_task/assign/verify) under the
Role screen's "Panel" group; the **Housekeeping** role per company; grants — Admin + Manager all,
Cashier front desk view/checkin/checkout + board view, Housekeeping board view + own tasks + outlet
enter/view. `Hotel_model`; `Hotel` controller with the per-method access map (ids by name); Room
Types and Rooms CRUD on the Table.php pattern (encrypted ids, soft delete, select2, DataTable);
landing tiles per permission; sidebar items with name-resolved tokens; 31 language keys × 4.
Rules: room number unique among the LIVE rooms of an outlet (a deleted number can be reused);
outlet must be one the caller may access; a room with a guest in it cannot be deleted; a type
still assigned to rooms cannot be deleted; statuses are shown on the list but only the Front Desk
(H2) and the board (H3) change them.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch (Manager / Cashier / Housekeeping sessions carrying the migration's real tokens): permission matrix incl. a Waiter refused even while ON; room types add / validation / edit / prefill / counts; rooms add with defaults, duplicate per outlet, foreign outlet, validation, edit keeps own number, list badges + guest, tampered id; delete guards (in-house stay, type in use) and soft-delete reuse; Cashier cannot delete; everything refused while OFF incl. an Admin POST | **35/35** |
| Real browser: Rooms list and Add Room form with the live CSS | PASS |
| Regression: H0 16/16 (its no-re-login proof now uses a permitted pre-switch session — since H1 a Waiter without a hotel permission is refused by design), Part B 18/18, tables panel 55 / 22 / 28 / 16 | green |

Found while verifying: `Custom::encrypt_decrypt('')` answers FALSE, not "" — the CRUD normalises the
id (Table.php survives on a loose `==`); the Save button used an undefined `save` key → `submit`.

### H2 — Front Desk (2026-09-16). Local only; not on live.
`Hotel/frontDesk`: the room board for one outlet (accessible outlets selectable), server-rendered
once and then polled every 15 s and after every action through `Hotel/boardAjax` (`hotel_front_desk.js`,
`hotel.css`; card builders are pure functions). Cards show room, type, the two states as badges,
the guest and "since", open-task count, and per-permission actions. `Hotel/index` now goes to the
board for anyone holding `hotel_front_desk` view; others keep the landing tiles.
- **Check-in** (`checkIn`, permission `checkin`): guest name required; room must be `vacant` — occupied
  and out-of-order are refused; a room not `clean` / `inspected` answers a **warning** (`reason dirty,
  warn 1`) and the same modal button becomes "Check in anyway" (re-post with `confirm_dirty=1`) — warn,
  not block, by decision. Writes the stay (`in_house`), sets `occupied`, logs, audit row.
- **Check-out** (`checkOut`, `checkout`): closes the stay (who / when), room → `vacant` **and** `dirty`
  (two log rows), and a `cleaning` task goes to the housekeeping pool — deduplicated while one is open.
- **Out of order** (`setOutOfOrder`, `status`): only while vacant; back in service → `vacant`; both logged
  with the reason.
- **History** (`roomHistoryAjax`): the room's last status changes with user names.
- **Stay log** (`Hotel/stays`): outlet / status / room / check-in date range / guest-phone-reference
  filters, DataTable, and `?export=csv` streaming the same filtered rows.
- 38 language keys (5 already existed) × 4; sidebar: Front Desk → board, Stay Log.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch: board page + JSON (counts, outlet switch and fallback, permission flags), Housekeeping role refused; check-in validation (name, room, date), success row + room state + log + audit, occupied refused, **dirty → warn → confirm**, inspected without warning, clamps; out of order (Cashier refused by the constructor, Manager ok + log, check-in on OOO refused, OOO on occupied refused, back in service); check-out (stay closed, vacant + dirty, two logs, pool task, repeat refused, **no duplicate task**), history, counts; stay log filters (status, guest/reference, outlet, date range) and the CSV export (headers, rows, attachment); everything refused while OFF | **47/47** |
| Board builders (Node, real script, real `boardAjax` answer): occupied / vacant-dirty / out-of-order cards, permission-dependent buttons, escaping, floor grouping, empty state, `since()` | 9/9 |
| Real browser (real page + CSS + JS, `boardAjax` answered from the captured JSON): 3 cards on 2 floors, chips, buttons per state; **check-in modal → warning → "Check In Anyway" → second post carries `confirm_dirty=1`, modal closes**; out-of-order modal; history modal | PASS |
| Regression: H1 35/35 and H0 16/16 (both updated for the index → board redirect), Part B 18/18, tables panel 55 / 22 / 28 / 16 | green |

### H3 — Housekeeping board (2026-09-16). Local only; not on live.
`Hotel/housekeeping`: a standalone full-screen page on the Kitchen Panel pattern (own `<html>`, no
sidebar — for a tablet on the floor; `housekeeping_panel.php`, `hotel_housekeeping.js`, `hotel.css`
v1.1), polled every 10 s and after every action through `Hotel/tasksAjax`. Sections are decided by
the server's `is_mine` / `assigned_to` / `status`, never by role name:
- **My tasks** and **Unassigned (pool)** for every `hotel_housekeeping` viewer; **Assigned to others**
  only for `assign` holders; **Done — awaiting verification** only for `verify` holders.
- **Start** (`taskStart`, `update_task`): `pending → in_progress`; a pool task is claimed by the actor;
  the room goes `in_progress` unless the task is `maintenance`. **Finish** (`taskDone`): → `done`;
  `cleaning` / `turndown` mark the room `clean`; inspection / maintenance leave it alone.
- **Verify** (`taskVerify`, `verify`): `done → verified`, room → `inspected`; the task leaves the board.
- **Assign** / **New task** / **Cancel** (`taskAssign`, `taskCreate`, `taskCancel`, `assign`): assignee
  must be a user whose role holds `update_task` (or Admin) — validated before anything is written;
  New task dedupes an open task of the same type (`task_exists`); a `cleaning` task on a
  `clean` / `inspected` room marks it `dirty`; cancel leaves the room as it is. Every action writes
  an audit row (`Hotel Task …`).
- An `update_task` holder may only touch tasks that are theirs or in the pool (`task` for anyone
  else's) unless they also hold `assign`; outlet-scoped like everything else.
- **Landing** (`irHousekeepingLanding()`): a session whose role holds `hotel_housekeeping` view, holds
  **no** POS access and is not Admin lands on the board — from both single-outlet login branches and
  `Outlet::setOutletSession`. Permission-based, so any custom role qualifies; with the module OFF the
  staff fall back to their usual landing. `Hotel/index` sends such staff to the board too.
- JSON now decodes the stored HTML entities once (`jsonOk`), since text is stored encoded and the boards
  escape on render — `O'Brien` no longer shows as `O&#039;Brien` (H2's board benefits as well).
- 30 language keys × 4; sidebar item and landing tile → `Hotel/housekeeping`.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch: page per permission set (Stella: mine/pool only, no overlays; Manager: four chips, New task, room + staff selects, Front Desk link; Waiter refused; Cashier view-only), index → board, sidebar; pool → start (claim, room in_progress + log) → finish (clean + log) → verify (inspected, "Verified") with every wrong-state repeat refused (`task_state`) and every action Stella lacks refused by the constructor; create (pool, dedupe, bad type, bad room, **bad assignee refused before writing**), assign / unassign, others' task refused for Stella, maintenance leaves the room alone, cancel, outlet 6 scoping + fallback, audit rows; **landing**: Stella → `Hotel/housekeeping`, Cashier / Manager → middleman, OFF → middleman; everything refused while OFF; text round-trip (note `O'Brien <x> & co`, guest `D'Souza`) | **60/60** |
| Board builders (Node, real script, real `tasksAjax` answers for Stella and the Manager): `sectionOf`, per-permission buttons on pool / mine / others / done cards, escaping, section order and counts, empty states, `since()` per status | 18/18 |
| Real browser (real page + CSS + JS, `tasksAjax` answered from the captured JSON): Stella's board (My tasks 1 / Unassigned 1, Finish vs Start+Finish); Manager's board (four sections, Assign / Cancel / Verify), **New task overlay → Submit posts room / type / user / note and closes**, Assign overlay carries the task and room; phone width | PASS |
| Regression: H2 47/47, H1 35/35, H0 16/16 (three assertions updated for index → board and the sidebar links), Part B 18/18 + 21/21, tables panel 55 / 22 / 28 / 16 | green |

Not in H3 (by scope): no notification when a task lands in the pool — the board polls; H4 adds the
toggle end-to-end check, docs and the owner's checklist.

### H4 — toggle end to end, schema guard, docs (2026-09-16). Local only; not on live.
- **Half-installed guard.** `irModuleRegistry()` now lists each module's `tables` and `migration`;
  `irModuleSchemaMissing($key)` names the tables absent from this database; `irModuleSet()` refuses ON
  while any is missing (OFF is always allowed). Settings › Modules shows a *Tables missing* badge with the
  migration file and the table names and withholds *Switch on*; a forced POST answers with the same notice
  and writes nothing. Closes the gap "migration 01 applied, 02 not, switch flipped, fatal on first use".
- **Docs.** `doc/HOTEL_OPERATIONS_GUIDE.md` — owner's guide (what it is / is not, switching, permissions
  table with the migration's defaults, landing rule, setup, the front-desk and housekeeping daily flows,
  status vocabulary, troubleshooting, **go-live checklist**, file map). Roadmap: hotel item and the
  "Module toggles" section marked built, with the one decision change (business-wide, no per-outlet list)
  recorded and the plug-in recipe for Push / Mobile API.
- Per-stage commits: H0 `feat(hotel): H0`, H1, H2, H3 `dd5532ca`, H4 (this one).

| Test | Result |
|---|---|
| HTTP on rexlio_scratch, module switch **with H1–H3 data in place**: fixture (3 rooms, 2 stays, 2 open tasks incl. one in progress for Stella); ON — landing + sidebar; **who may flip**: Manager without the grant refused (screen and POST), a **non-admin holding `update-<modules>`** gets the screen and the sidebar item; **OFF by that user**: row / updated_by / audit; **every Hotel entry refused for Admin, Manager and Stella — 9 GET + 16 POST each, including check-in / check-out / task actions / room CRUD with real payloads — and no row changed** (rooms, stays, tasks, log, types; task still pending, 101 still in house); the notice on the landing page; no sidebar group for three roles; Modules screen still reachable; Stella falls back to the middleman; **base product answers byte-for-byte as while ON** (POS, tables route, running orders, waiter and cashier outlet entry); **guard**: one table renamed away → badge + migration + table named, no *Switch on*, forced POST refused with the notice and no audit row, module still unreachable without a fatal; table back → normal; **ON again**: audit, `boardAjax` / `tasksAjax` JSON identical to the pre-switch snapshot for the Manager and for Stella, Stay Log intact, landing and sidebar back, Stella starts the task that waited through the OFF period; unknown key → "not installed", nothing written | **33/33** |
| Base-product suites run **with the module OFF**: tables panel 5a 55/55, 5c 28/28, Part B 18/18 | green |
| Real browser: Settings › Modules as the granted non-admin (OFF badge, Switch on, last changed by / at) | PASS |
| Regression (module ON): H3 60/60, H2 47/47, H1 35/35, H0 16/16, Node 18 + 9, tables panel 55 / 22 / 28 / 16, Part B 18 + 21 | green |

**Not done here, by design:** nothing was applied to the live database. Going live is the owner's
checklist in the guide (backup → migration 01 → 02 → deploy → Switch on).

### U1 — flash banners (2026-09-16). Commit 95219084.
The shared banner (139 screens) had green-on-green text (theme rule specificity) and never left; now white
text on both banners, success fades after 6 s, danger stays; Front Desk actions show a 4 s toast.

### H5 — stay value (2026-09-17). Local only; not on live.
Migration `2026-09-17_01_hotel-stay-value.sql`: `rate`, `nights`, `amount`, `actual_nights`, `value_note` on
`tbl_hotel_stays` (guarded ADD COLUMNs, re-runnable), index on (company, check-in), permission
`hotel_front_desk › value` for Admin and Manager.
- **Counted at check-in** (decision 2a): rate from the room type's base rate — only a `value` holder may type
  another; nights = expected check-out − check-in date (min 1); amount = nights × rate. A rate above 0
  requires the expected check-out (decision 4); a type without a rate is complimentary. Logged in the room
  history as kind `value` (null → amount).
- **Check-out, option C** (decision 1): actual nights recorded on every check-out. A `value` holder whose
  actual ≠ expected gets a prompt — keep the recorded value or adjust it (reason optional) — logged old → new.
  Staff without the permission check out unprompted; the variance (actual ≠ nights) shows as a badge in the
  Stay Log for a manager to settle with **Edit value** (front desk card for in-house stays, Stay Log for any
  stay). Every change logged with old / new and the reason; audit rows too.
- Stay Log: Room type / Rate / Nights (+ actual badge) / Amount columns, amount total, CSV columns.
- Still nothing in `tbl_sales`, registers, Today's Sale, no invoice — recorded value only.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch: permission rows; board carries base_rate / stay value / can.value; page fields read-only vs editable; check-in: expected required when rate > 0, past date refused, Cashier's posted rate ignored (type rate wins), Manager's negotiated rate honoured, negative rate refused, day-use = 1 night, complimentary type; edit value: Cashier refused by the constructor, unknown stay, extend → recomputed + logged, typed amount + reason, no log on a no-op, bad date / amount; check-out: variance prompt with expected / actual / suggested, bad amount refused, adjust with reason (actual_nights, amount, log "4 / 2"), Cashier checks out unprompted with the variance recorded and the amount untouched, later settlement on a checked-out stay, same-day no prompt, early departure "keep"; Stay Log columns / badges / total / edit buttons per permission, CSV; sales tables untouched | **35/35** |
| Node builders on a real `boardAjax` answer: money(), nightsBetween(), occupied card value line + Edit value button (permission-dependent), vacant card data-rate | 5/5 |
| Real browser: check-in modal live nights/amount from the expected date (15,000 → 3 nights → 45,000, required star), variance prompt on check-out with the suggested figure → adjust posts `confirm_value=1`, amount, reason; Edit value modal recalculates on a new expected date and posts | PASS |

### H6 — Value Generated report (2026-09-17). Local only; not on live.
Migration `2026-09-17_02_hotel-reports-permission.sql`: `hotel_reports › view` (Admin, Manager) — one
permission for every hotel report; the reports live in the Hotel controller (module-gated), the Reports
menu links there (and the Hotel group has "Hotel Reports").
`Hotel/reportValue`: shared filter bar (`_report_filter.php`: outlet or all, **View = Day / Week / Month /
Year**, date range, presets today / this week / this month / this year, GET so it bookmarks; the span is
capped per view so a Day view cannot ask for a thousand columns). Rows = **every live room category**
(zeros included, so a new category appears by itself), columns = the view's buckets across the range
(weeks start Monday), each cell amount + stays · nights, row totals, column totals, **grand total**,
summary cards (value, stays, nights, average per stay). Stays are counted on their **check-in date**
(the value is booked then), in-house included, cancelled excluded. DataTables export (print / copy /
Excel / CSV / PDF) like every other report.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch with stays across Jan–Mar in two categories, a third category with none, an in-house stay, a cancelled stay and one at another outlet: Cashier refused, `Hotel/reports` → the report, both menu links with the looked-up token; **year view** (every category as a row incl. the empty one, per-category and grand totals 290 000 / 6 stays / 13 nights, cancelled excluded, summary card); **month view** outlet 1 (three ordered columns, cells, in-house counted, other outlet excluded, column and grand totals); **day view** (one column per day, cells on the right days); **week view** (Monday buckets); defaults (this month, month view), span cap with notice, reversed dates swapped, bad view / date / outlet fall back; module OFF hides and refuses | **20/20** |
| Node: the preset date maths (today / week from a Wednesday and a Sunday / Feb / year) | 5/5 |
| Real browser: filter bar, summary cards, pivot with sub-counts, totals row, DataTables export buttons, alphabetical categories | PASS |

### H7 — Occupancy report (2026-09-17). Local only; not on live.
`Hotel/reportOccupancy`, same filter bar. **Per period bucket**: days, rooms, available room-nights
(live rooms × days), occupied room-nights, occupancy %, arrivals, departures, value booked, **RevPAR**
(value ÷ available room-nights); totals. **Per room category** for the whole range: rooms, available,
occupied, occupancy %, arrivals, departures, value, RevPAR. Definitions (in the footnote): a stay occupies
its room each night from check-in to the night before check-out — a same-day stay counts one; an in-house
stay counts up to **today**, never into the future; arrivals and value on the check-in day, departures on
the check-out day; cancelled stays ignored. Known simplification: "available" is today's live room count
for every day of the range (rooms out of order or added mid-range are not netted per day).

| Test | Result |
|---|---|
| HTTP on rexlio_scratch with hand-computable January fixtures (3-night stay, a same-day stay, a stay straddling into February, one straddling in from December, a cancelled one, one at another outlet, one in-house since yesterday): month view outlet 1 (31 days, 4 rooms, 124 available, 8 occupied, 6.5 %, 3 arrivals, 3 departures, 180 000, RevPAR 1 451.61; per-category rows); day view 5–8 Jan (per-day occupied / arrivals / departures incl. the same-day stay, 25 % → 50 %, totals); in-house counts yesterday and today but not tomorrow; all outlets adds the fifth room and its stay; week view days add to 14; an empty month shows zeros without a division error; Cashier refused; menu link | **18/18** |
| Real browser: filter bar, five summary cards, weekly rows (ISO weeks from Monday), category table | PASS |

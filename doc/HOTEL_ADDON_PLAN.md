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

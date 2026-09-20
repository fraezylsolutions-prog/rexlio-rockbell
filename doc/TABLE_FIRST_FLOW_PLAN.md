# Table-first waiter flow — investigation and staged plan (2026-09-15)

Status: **plan only, nothing implemented.** Local dev only. Everything below was read from the
code or measured; nothing is assumed.

## 1. Findings that shape the design

### 1.1 The six actions already exist — inside the POS page
`main_screen.php` (table modal) carries a per-table quick-action list: Invoice (1), Split Bill (2),
Modify (3), Merge (55), Bill (4), Cancel (5). `pos_script_v7.3.js` ~7373 implements each as
"select the running-order card, then click the existing button":

| Action | What the handler does | Where the logic lives |
|---|---|---|
| Invoice | `#order_<sale>.click()` → `.invoice_btn_class[1].click()` (finalize modal) | POS, IndexedDB copy |
| Split Bill | same → `.invoice_btn_class[0]` (split modal) | POS |
| Modify | `get_details_of_a_particular_order(sale_no)` | POS |
| Merge | select second table → `setMergeTableFinal(...)` | POS |
| Bill | `#create_bill_and_close.click()` (print) | POS |
| Cancel | `#cancel_order_button.click()` (reason prompt → confirm) | POS |

All six work on **this till's local copy** and refuse with "order engaged on another device" when
the order is not local — the exact gap Step 2's adoption (`irAdoptOrder`) closes. None of this
logic exists server-side. Re-implementing Invoice (payment methods, due, change, split payments,
loyalty), Split and Merge as server endpoints for a different page is the bulk of any
"in-place outside the POS" design.

### 1.2 Measured page costs (warm cache, real browser, same machine)
| Page | DOMContentLoaded | Server |
|---|---|---|
| Monitor Table Status (`Monitor/tables`) | **0.20 s** (two runs 191 / 207 ms) | 42 ms |
| POS Sale screen (after today's fixes) | **0.53 s** (504–571 ms) | 0.33 s |

On live add ≈0.27 s per navigation (Chicago round trip).

### 1.3 Defect found: the Phase D counter price-tier lock does not reach waiters
`getLockedPriceTier()` reads the session's `counter_id`. Only users who open a register get one;
waiter sessions never do. **Tested:** outlet 1's only counter set to Club (tier 5), POS rendered
for the waiter → `locked_price_tier` empty, 0 of 2 tier buttons disabled. The same helper is used
for server-side enforcement at placement, so it is not enforced there either. Today a waiter at a
Club-locked counter orders at Regular prices. Fix is small and independent of this feature: when a
session has no `counter_id`, resolve the outlet's single live counter (one counter per outlet is
confirmed). Stage 0.

### 1.4 Defect found: an order placed offline never reaches the server as a running order
The only caller of `Sale/add_kitchen_sale_by_ajax` is the placement handler; if that request fails
(offline) nothing retries it. The order exists only in that till's IndexedDB until it is invoiced
(`push_online`, completed sales) or modified while online. Consequences: Running Order screen,
KDS, Step 2 sync and **any server-rendered Table Status cannot show it**. A queued "kitchen sync"
(retry the KOT push on reconnect, print suppressed) closes this for every consumer. Stage 0.

### 1.5 Data facts
- `tbl_tables(id, area, name, sit_capacity, position, description, user_id, outlet_id, company_id,
  del_status, is_setting)`; **no unique index on name**; only PRIMARY. All previous tables are
  `Deleted` (0 live). 
- `tbl_areas`: outlet 1 currently has **five** live areas (Test Main Floor, Ground Open Bar, Up
  Open Bar, Cloud 9, Pool Side Bar). "One area per outlet going forward" is a rule to apply, not
  the current data — the auto-create needs a deterministic pick (see 3.3).
- Order ↔ table: `tbl_orders_table(sale_id, sale_no, table_id, persons)` (server) and the
  `orders_table` array inside the order JSON / `order_tables` IndexedDB store (till).
  `tbl_kitchen_sales.table_id` also carries the first table. Merges = several
  `tbl_orders_table` rows for one sale.
- Table selection in the POS is a DOM state: `.single_table_div[data-table-checked=checked]` plus
  `#table_id`; the order JSON builder reads the checked divs. Pre-selecting a table
  programmatically means marking that div and setting `#table_id` after the table list renders.

## 2. Answers to the seven investigation questions

**Q1 — Is a "table" now a running order?** No: keep them distinct, bind their lifecycles. Kitchen
display, bills, reports and `tbl_orders_table` all key on `table_id`, and a merge legitimately puts
two tables on one order. So: a real `tbl_tables` row is auto-created per "+ New Table", flagged
`auto_created=1`, and **soft-deleted** (`del_status='Deleted'`) when no live running order
references it any more (completion, cancel, or the merge that absorbed it). Soft delete keeps
history readable (a completed sale's table name still resolves). In the new flow the mapping is
1 table : 1 order until a merge; after a merge 2 tables : 1 order, both released together.

**Q2 — Cross-device.** A table created on Till A is a server row; the order is in A's IndexedDB
and — when A was online — in `tbl_kitchen_sales`. On Till B the same waiter sees it via the
server list and, on Modify, Step 2 adopts it (verified path). Two caveats, both fixed by Stage 0:
(a) if A placed the order **offline** the order is invisible to B until A syncs (1.4);
(b) "+ New Table" needs the server (naming), so it cannot be used offline unless numbers are
reserved ahead (3.3). Step 1's clash guard protects the order numbers exactly as before; the
table name is a separate sequence and never collides because it is issued server-side.

**Q3 — Naming.** Server-side, per waiter, atomic: `tbl_users.ir_table_seq` incremented with
`UPDATE … SET ir_table_seq = LAST_INSERT_ID(ir_table_seq + 1)` and read back — one round trip,
no race, survives cleared browsers and multiple tills. Name = lowercase first word of the full
name, letters/digits only, then `-` and four digits; wraps 9999 → 0001 (a repeated name only ever
coexists with a *deleted* table). Offline extension (Stage 2b): the till reserves the next N
numbers at load, the same idea as the device tag, so a table can be started offline and its row
created on reconnect.

**Q4 — The five in-place actions.** Two honest architectures:

| | **POS-hosted panel** (recommended) | **Monitor-hosted page** |
|---|---|---|
| Waiter landing | POS page opens with a card panel of *my* tables over the item gallery | `Monitor/tables` in a waiter mode |
| Landing cost (warm) | 0.53 s | 0.20 s |
| "+ New Table" → ready to order | one server call, **no navigation** | server call + POS navigation (+0.53 s, +1 RTT live) |
| Modify | in page | navigation to POS (+ adoption) |
| Invoice / Split / Merge / Bill / Cancel | **existing handlers, unchanged**, adoption first if the order is not local | Cancel, Bill: new server endpoints (small). **Invoice, Split, Merge: server-side re-implementation of payment/split/merge UIs — the largest item in the whole plan** (or: navigate to the POS pre-armed, which is not "in place") |
| Offline | panel and all six actions work on local orders; "+ New Table" needs 3.3's reservation | page cannot load offline; no actions offline |
| Rendering | new card partial (~150 lines) inside the POS; **not** the floor canvas | existing card partial, restyled for value + own-tables |
| Risk | touches the 20k-line POS page (additive panel, no existing handler changed) | new server-side money logic duplicating POS behaviour |
| Estimate (Stages 2–3) | 4–6 days | 8–12 days, and Split/Merge still navigate |

The spec asks for the Monitor card screen as the foundation for speed. The measurement shows the
POS page is 0.33 s slower to open — but the flow's hot path (open → New Table → order) is
**one** POS load either way, and every other action avoids a navigation on the POS-hosted side.
The decisive point is 1.1: the five actions are POS code. Recommendation: **POS-hosted panel for
waiters; Monitor page (extended) for higher roles.**

**Q5 — Performance.** The POS-hosted panel adds one small AJAX at load (`Monitor/myTablesAjax`:
one query, own live orders with `total_payable`, own tables) and reuses the IndexedDB the page
already opens. No new eager data. The waiter-scoped query is a subset of today's
`getTableStatus()` (indexed by migration 03). Rendering ≤ a few dozen cards. Landing therefore
stays at the measured POS cost (~0.53 s warm here). The Monitor page for higher roles gains three
filters on an already server-rendered, indexed query. Profiling will be repeated per stage with
the same harness (`perf.sh` + browser snapshot).

**Q6 — Permissions.** Reuse `view_all_running_orders` (tbl_access 382, parent 372) for
*seeing* other waiters' tables — it is exactly that concept and the Running Order screen already
uses it. Add one row, same pattern: `act_on_any_running_order` (parent 372, label "Act on any
running order"), default grants **Admin, Manager, Cashier** (migration, like
`2026-09-08_01`). Cross-outlet: visible through `getAccessibleOutletIds()`, actions only within
the session outlet (different register and stock — Step 2 rule).

**Q7 — Conflicts.** `Monitor/tables` today shows every table of the session outlet (free and
occupied) to every role, with a "New Order" link per free table into the POS. Under the plan:
waiters never land there (POS-hosted panel); other roles keep it unchanged until Stage 4 extends
it. `_table_cards.php` shows seat capacity — the waiter cards show order value instead (separate
partial). The old Area / Table / Floor-design admin screens stay untouched and dormant.
`Authentication::userProfile` (line 617) still deep-links `Sale/POS/2/<outlet>/<table>` for the
legacy waiter-app path — left alone.

## 3. Staged plan

### Stage 0 — prerequisites (independent fixes, both architectures) · ~1 day
1. **Counter lock inheritance for waiters**: `getLockedPriceTier()` falls back to the outlet's
   single live counter when the session has none. Test: counter locked to Club → waiter POS renders
   tier locked and placement enforces it (HTTP + rendered-page checks, as done today for the
   failing case).
2. **Kitchen sync for offline-placed orders**: keep a `kitchen_pending` flag on the local order;
   on reconnect push it with print suppressed; clash and version guards apply. Test with the
   Step 1/2 harnesses (offline placement → reconnect → row appears, no duplicate KOT).

### Stage 1 — schema and backend · ~1 day
- Migration `2026-09-15_04_table-first-flow.sql`: `tbl_tables.auto_created TINYINT DEFAULT 0`,
  `tbl_users.ir_table_seq INT DEFAULT 0`, permission row `act_on_any_running_order` + grants.
- `Sale/createWaiterTable`: atomic sequence → name → insert `tbl_tables` (area = the outlet's
  live area with the lowest id, or create "Main" if none; `user_id` = waiter; `auto_created=1`)
  → returns `{id, name}`. Refuses without a session outlet.
- `Monitor/myTablesAjax`: waiter-scoped live orders + their tables with `total_payable`
  (own `user_id` or `waiter_id`; higher roles see all with the permission).
- `irReleaseAutoTables(sale_no)`: soft-delete auto tables no longer referenced by any live kitchen
  sale; called from `push_online` (completion), `add_cancel_audit_report`, and the merge endpoint.
- Tests on scratch DB: sequence atomicity (parallel curl), naming, area pick, release on each path.

### Stage 2 — waiter landing + "+ New Table" · ~2 days
- Landing: `Authentication::loginCheck` and the POS menu entry route `designation=='Waiter'`
  (and `is_waiter=='Yes'`) to `Sale/POS?tables=1`; other roles unchanged.
- POS-hosted panel: card grid (name, order value, elapsed) built from IndexedDB first, then merged
  with `myTablesAjax` (adopting server-only orders via `irAdoptOrder`); "+ New Table" button;
  panel closes when a table is chosen or created; reopenable from the header.
- "+ New Table": `createWaiterTable` → insert into the POS table list → mark
  `data-table-checked` + `#table_id` → panel closes, gallery ready.
- Verify: counter lock on an auto-created table (the test you asked for), cross-till (create on A,
  see and modify on B), real-browser DCL unchanged within noise.
- 2b (optional, after 2): reserved number block for offline "+ New Table".

### Stage 3 — the six actions from the panel · ~1.5 days
- Tap → 6-button sheet. Each button: adopt if not local → existing quick-action path (1.1).
  Cancel keeps its confirmation; Print Bill goes straight to `#create_bill_and_close` (uses the
  print-server / self-closing popup work from earlier); Merge shows an in-panel picker of the
  waiter's other tables then calls `setMergeTableFinal`.
- Verify each action end-to-end on local, then the two-till script.

### Stage 4 — higher roles · ~1.5 days
- `Monitor/tables` gains User / Outlet / Date filters (outlet via `getAccessibleOutletIds()`),
  order value on cards, and the six actions as deep links into the POS (adoption) within the
  session outlet; view-only across outlets. Gated by `view_all_running_orders` (see) and
  `act_on_any_running_order` (act).

Total ≈ 7–9 working days with the POS-hosted panel. Each stage: scratch-DB migration test,
HTTP/Node suites, real-browser measurement, report before the next.

## 4. Decisions needed before Stage 2
1. POS-hosted panel (recommended) vs Monitor-hosted page for the waiter landing.
2. Area rule when an outlet has several live areas: lowest id (proposed) or a chosen one.
3. Whether "+ New Table" must work offline in the first release (adds 2b).
4. Confirm Stage 0's counter-lock fix is wanted for *all* waiter orders, not only auto-created
   tables (it changes today's behaviour: waiters at a locked counter currently get Regular).

---

## 5. Stage 0 / 1 / 2 — built and verified (2026-09-15). Local only; not committed; not on live.

### Stage 0
- **Counter lock reaches waiters.** `getLockedPriceTier()` resolves the outlet's single live counter
  (`irOutletSingleCounterId`, lowest id if several) when the session has none. Test 7/7: Club-locked
  counter → waiter's page locked to 5, Club pre-selected; an order posted as Regular is stored at
  tier 5; Regular counter unchanged. Applies to all waiter orders (agreed).
- **Kitchen sync for offline-placed orders.** Local record flag `kitchen_pending` (1 waiting, 3 in
  flight, 2 refused, 0 synced); the 7 s loop sends waiting orders when the server answers,
  printing the KOT then; success marks the order online-placed so the cross-till sync includes
  it. Unit 12/12.

### Stage 1
Migration `2026-09-15_04` (PASS scratch + local): `tbl_tables.auto_created`, `created_at`,
`tbl_users.ir_table_seq`, permission `act_on_any_running_order` → Admin/Manager/Cashier.
`Sale/createWaiterTable` (atomic sequence, `okoye-0001`, lowest-id live area or "Main"),
`Sale/myTablesAjax` (own orders + empty auto tables, `view_all_running_orders` widens to the
outlet, 4 h sweep of abandoned empty tables), `irReleaseAutoTables()` on completion / cancel /
merge-absorb. HTTP 18/18 incl. 10 parallel creations → 12 distinct names; naming 8/8.

### Stage 2
- Waiter landing: `Sale::POS` renders the panel and opens it on load when `isWaiterUser()`;
  waiters already land on `Sale/POS` after login, so no route change. Header button reopens it.
  Panel reopens after each new order is placed (not after modifications, not from background sync).
- Panel (`irTablesPanel*` in `pos_script_v7.3.js`, CSS section 25): cards from this till's
  IndexedDB merged with `myTablesAjax` by sale_no (local wins for value/tables; server adds
  kitchen progress and other tills' orders); status pills New / In kitchen / Served / Offline;
  elapsed from the server clock; 10 s refresh while open.
- "+ New Table": `createWaiterTable` → selects the table the way the table modal does
  (`#table_id`, `#hidden_table_name`, `#hidden_table_capacity=1`), closes the panel, focuses the
  item search. Disabled when the last server fetch failed (offline).
- Tap: empty table → select; own order on this till → select its running-order card; order on
  another till → adopt (Step 2) then select. Stage 3 replaces the tap with the six-action sheet.

**Verification**
| Test | Result |
|---|---|
| Panel unit (Node, real functions, fake DOM/IndexedDB/XHR): merge, badges, elapsed, offline, New Table ok/error, taps incl. adoption, reopen-after-placement gating | 20/20 |
| Server flow (HTTP, two cloned sessions of the same waiter): page carries + opens the panel; **Club lock: order on an auto-created table posted as Regular stored at tier 5**; booking row; **Till B lists it, adopts it (v2), modifies (v3), Till A polled stale with the new value; Till B cancels → table released, Till A polled "gone", panel empty** | 13/13 |
| Real browser (Chrome pane), real POS page + real scripts, server calls canned: panel auto-opens with three cards (New / In kitchen / Served, values, elapsed); **"+ New Table" → one server call, table selected, panel closed, ready toast, search focused, gallery ready**; header reopen; tap empty table selects; tap other-till order attempts adoption and stays open when refused | PASS |
| Warm DOMContentLoaded with the panel | 646 ms (harness page; 0.50–0.57 s before the panel — within run-to-run noise, no new eager work) |
| Regression: Step 1/2 suites, Stage 0/1 suites, loader, naming | all green |

**Found and fixed during this stage:** (1) the panel markup was first injected *inside* the
`.main_right` opening tag (regex stopped at a `?>`) — caught by the real-browser check, repaired;
(2) first open was empty because the online flag is unset until the first 2 s ping — the fetch
now always tries; (3) a literal middle-dot in the JS mis-rendered (`Â·`) — replaced with
`String.fromCharCode(183)`. Cache-busters: `pos_script_v7.3.js?v=4.9`, `rexlio_theme.css?v=7.8.7`
(both files).

**Not verified by tooling:** a logged-in browser placing a real order from an auto-created table
end to end (the pane cannot carry the session) — the owner's two-till test in step 3 of the
original plan.

### Stage 3 — six-action sheet (2026-09-15). Local only; not committed; not on live.
Tapping an order card opens the sheet (`#ir_action_sheet`, approved visual): table name, order
number, value, elapsed; Modify / Invoice / Split Bill / Merge Table / Print Bill / Cancel Order.
Every action is the POS's own handler reached the way the table modal's quick actions reach it:
adopt the order onto this till if it lives elsewhere (`irAdoptOrder`), wait for its running-order
card, select it, click the existing button (`.invoice_btn_class[1]`, `[0]`,
`#create_bill_and_close`, `#cancel_order_button`; Modify = `get_details_of_a_particular_order`
+ `update_kitchen_status`, as quick action 3). Bill and Cancel leave the panel open (refreshed
after a cancel); Modify/Invoice/Split close it. **Cancel's confirmation is the POS's existing
reason prompt**, and the existing role permission applies (a role without Cancel gets the POS's
"no permission" notice — verified). **Merge** shows an in-sheet picker of the other open tables
(self and already-merged bills excluded), asks the vendor's confirmation, adopts the other order
if needed, then calls the vendor's `setMergeTableFinal` with its four inputs (both order JSONs,
numbers, table names, local ids) — which, as in the product today, produces one `A || B` order
and opens the invoice modal for it.

| Test | Result |
|---|---|
| Sheet unit (Node, real functions): header, each of the six actions on a local order (card selected → correct button), other-till order adopted first, refused adoption → nothing, merge picker contents/back/already-merged guard, merge confirm → adopt → `setMergeTableFinal(objs, nos, texts, ids)`, decline → nothing, close/backdrop | 19/19 |
| Panel unit, updated for Stage 3 (tap opens the sheet) | 20/20 |
| Real browser, real POS page + real scripts (server calls canned): sheet renders as approved; **Cancel** → adoption (1 call) → order appears in Running Orders and is selected → vendor handler ran (answered "no permission" for the local Waiter role, which is the policy); **Modify** → adopted → cart loaded with the item, "Update Order" mode, panel closed; **Bill** → card selected → `#create_bill_and_close` → print popup opened, panel stayed; **Merge** → picker lists only the other open table, Back returns | PASS |
| Regression: Step 1/2, Stage 0/1/2, kitchen sync, loader, naming, lint | all green |

Harness notes (not code): a stale adopted record persisted in the pane's IndexedDB between runs
(origin storage) and had to be cleared; the harness item JSON needed the modifier fields the cart
builder reads (`modifiers_id` etc. — the real order JSON always carries them); the `NaN` total in
the harness comes from missing rounding fields, same cause. Cache-busters:
`pos_script_v7.3.js?v=5.0`, `rexlio_theme.css?v=7.8.8` (both files).

### Stage 4 — higher roles on Monitor Table Status (2026-09-15). Local only; not committed; not on live.
`Monitor/tables` is the manager's view of the same flow. Nothing here tests a role name: *seeing* is
`view_all_running_orders` (382), *acting* is `act_on_any_running_order` (383), both ordinary
`tbl_access` rows switchable on the role screen; Admin short-circuits both as everywhere else.

- **Scope.** A view-all caller sees every table in every outlet they may access
  (`getAccessibleOutletIds()`), grouped as `Outlet / Area`; anyone else sees the session outlet
  only, exactly as before. Cards show **order value** (sum of the table's live orders) instead of
  seat capacity while occupied; a merged bill lists its value on every table it holds.
- **Filters** (view-all callers only; rendered nowhere else and posted values are ignored for
  everyone else): Outlet (validated against the accessible list), User (matches `user_id` *or*
  `waiter_id`), Date (`sale_date`, `YYYY-MM-DD` or ignored). A user or date filter is about
  orders, so it returns only the tables holding a match — free tables have nothing to match. The
  15 s poll (`table_status.js`) re-sends the three current values so a refresh never widens the
  view.
- **Actions.** On an occupied table in the **session outlet**: own order (user or waiter) → the six
  actions; someone else's → the six actions only with `act_on_any_running_order`, otherwise
  "Order Details" + *View only*. Each action is a deep link into the POS
  (`Sale/POS/<me>/<outlet>?open_sale_no=X&ir_action=modify|invoice|split|merge|bill|cancel`): the
  POS selects the card (adopting the order from the other till first when needed, Step 2), then
  runs the **same handler the waiter sheet uses** (`irRunOrderAction`); `merge` opens the tables
  panel with the picker. A free table's "New Order" arrives as `?ir_table_id=&ir_table_name=` and
  preselects it as "+ New Table" does. Another outlet's table is **view only, no link at all** —
  it is a different register and stock and the POS would refuse the adoption anyway.
- **Found and fixed while verifying:** (1) an occupied table in *another* outlet still carried an
  "Order Details" link into this outlet's POS, which could only fail at adoption — removed;
  (2) `escape_output(0)` returns `''`, so the header showed "Occupied: " / "Free: " blank and an
  area "/3 Occupied" whenever a count was zero — common once a filter narrows the list; counts
  now print as plain ints; (3) a dangling "· Walk-in Customer" separator when the order had no
  waiter name.

| Test | Result |
|---|---|
| HTTP (seeded sessions on rexlio_scratch through `pw.php`; fixtures: a waiter's auto table, a cashier-placed merged bill on two tables with another waiter, an order in a second outlet, a free table in a third): Admin scope/values/actions/links; every filter alone and combined, invalid outlet and malformed date ignored, zero-result state; `tablesAjax` totals with filters; Cashier (view-all + act-any) acting on another user's order, links carry the caller's user/outlet, other outlet view-only; same cashier at the other outlet (actions flip); Manager (view-all, no act-any): no actions on others', own order in another outlet view-only with no link, own order in session outlet actionable; Waiter: no filters, posted filters ignored, own order actionable, others' view-only; second waiter own-via-`waiter_id` on the merged bill | **55/55** |
| POS deep-link parser (Node, real source of `irDeepLinkAction` / `openTableFromDeepLink` with stubbed handlers): five actions dispatch after selection with the URL cleaned, merge opens panel → sheet → picker, unknown action / no action / non-numeric table id do nothing, plus-encoded and missing table names | **16/16** |
| Real browser (Chrome pane, server-rendered cashier page + real CSS/JS): filters row, `Outlet / Area` groups with `0/1`, `3/5` counts, value/guest rows, six buttons on actionable cards, *View only · Outlet* without a link on the other outlet, *View only* on the other outlet's free table; the poll posts the three selected filters to `Monitor/tablesAjax` and shows the offline banner when it cannot reach the server | PASS |
| Regression: Stage 4 suite after each fix, `Monitor/runningOrders`, `Monitor/orderLookup`, `Sale/myTablesAjax` for Admin / Cashier / Waiter, lint | all green |

**Not verified by tooling:** a logged-in browser following an action button from Table Status into
the POS and through the vendor handler (the pane cannot carry the session; Stage 3 covered the
handler side with canned server calls). Warm `Monitor/tables` for a 3-outlet scope: 0.10–0.21 s.

## 6. Stage 5 — one tables screen (decided 2026-09-15)
The Monitor Table Status page is merged INTO the POS-hosted panel: one screen, behaviour by
permission. Waiters and Cashiers land on it after login; Admin/Manager keep the dashboard and reach
it through the POS icon. Within the checked-in outlet a Cashier sees and acts on any table; a
table in another outlet is shown but acting means switching outlet first (register/stock
boundary, unchanged). A user assigned to several outlets gets a Location filter defaulting to all
of them. Register guard accepted (the POS needs an open register). Multi-outlet Cashier keeps the
outlet chooser, then lands on the panel.

### Stage 5a — data (2026-09-15). Local only; not committed; not on live.
`Sale/myTablesAjax` is now the single data source (Monitor::tablesAjax to be retired in 5c).
Additive: every field the panel already reads is unchanged.
- Scope: session outlet; every accessible outlet (`getAccessibleOutletIds`) for a view-all caller
  **or** anyone assigned to >1 outlet; posted `outlet_id` narrows, only to one of those.
- Orders: own; everyone's with `view_all_running_orders`. `view_user_id` (user **or** waiter) and
  `sale_date` honoured for view-all callers only, same validation as the old Monitor filters.
  Each order adds `outlet_id/outlet_name/sale_date/waiter_name/customer_name/persons`, `is_own`,
  `same_outlet`, **`can_act`** = same outlet ∧ (own ∨ `act_on_any_running_order`). Orders with no
  table are listed like any other (table_ids null) — the case the Monitor page could not show.
- `free_tables`: every live table in scope with no order, via `Sale_model::getTableStatus()`
  (house tables for everyone; another waiter's empty auto table only for view-all callers).
  `counts` {tables = occupied + free as shown, occupied, free, orders, orders_without_table};
  `filters` {…_allowed, applied values}; `scope`; `outlets`/`users` lists only with `with_lists=1`.
- Login gap: a waiter assigned to >1 outlet now goes through the outlet chooser (both login
  paths, `loginCheck` and `redirectUrl`); `Outlet::setOutletSession` → middleman → `Sale/POS` as
  for every other role. Single-outlet waiters unchanged.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch (9 seeded sessions incl. open registers for non-waiters; fixtures add a **table-less order** and an order in an **outlet with zero live tables** — today's real shape — an empty auto table of a second waiter, and a waiter assigned to two outlets): Admin scope/orders/counts/free tables/`can_act` matrix/lists; every filter alone and combined, invalid values ignored; Cashier at three outlets (`can_act` flips with the checked-in outlet); Manager with view-all only; single-outlet waiter (no filters, posted ones ignored, other waiter's auto table hidden); two-outlet waiter (Location filter only, both outlets by default, unassigned outlet ignored, own order in the other outlet visible but `can_act 0`) | **55/55** |
| Real DB (`rexlio`, temporary Admin session): the two live orders of 15 Sep come back, both `can_act 1`; the Wetspot one `can_act 0`; tables 0/0/0, orders_without_table 3 | PASS |
| Regression: the panel's legacy fields asserted intact; `Monitor/tables` still 200 for all roles until 5c; warm `myTablesAjax` for a 6-outlet Admin scope 41–50 ms | green |
| Login branch: assigned-outlet counting over the real column shapes (`"1"`, `"1,6"`, `""`, NULL, `"1,"`, `"6, 1"`) | 8/8 |

**Not verified by tooling:** an actual multi-outlet waiter login through the browser (needs credentials).

### Stage 5b — the panel UI (2026-09-15). Local only; not committed; not on live.
The POS panel now renders everything `myTablesAjax` says (Stage 5a); no new data path.
- Head: title "Tables" for a view-all caller ("My tables" otherwise), **Occupied / Free / Orders**
  chips from `counts`. Filter row shown only when the server allows any filter: Location
  (view-all caller, or a user assigned to >1 outlet), User and Date (view-all). A change refetches
  at once; the 10 s poll carries the same values; lists fetched once (`with_lists`); Clear appears
  while a filter is applied. With a filter active only server-listed orders show (a local record
  outside the filter is hidden).
- Cards: free tables (`free_tables`: house tables + own auto tables; "Free" badge, seats, outlet
  name when the scope spans outlets) — tap selects the table as "+ New Table" does; order cards
  gain the waiter name (view-all) and outlet name (multi-outlet), "No table" marker for table-less
  orders; **view-only** cards (dashed, eye mark) when `can_act` is 0.
- Sheet: for a view-only order the six buttons are replaced by the reason — another outlet →
  **"Switch to <outlet>"** (SweetAlert confirm → `Outlet/setOutletSession/<id>/pos`, new: lands on
  the POS whatever the role; unassigned outlet still refused) — or "your role cannot act on it".
  A free table in another outlet offers the same switch. Merge picker lists only actionable orders.
- Cache-busters `pos_script_v7.3.js?v=5.2`, `rexlio_theme.css?v=7.8.9` (both files). Lang keys in
  all four files. Card builders (`irTpMerge`, `irTpOrderCardHtml`, `irTpFreeCardHtml`,
  `irTpGridHtml`) are pure so they run in Node.

| Test | Result |
|---|---|
| Panel unit (Node, real source, REAL `myTablesAjax` answers captured from rexlio_scratch for Cashier / Cashier+outlet / Cashier+user / no-match / Manager / one-outlet Waiter / two-outlet Waiter / Admin on the zero-table outlet): merge rules incl. local-wins and filter hiding, card content and escaping, view-only marking, grid order and both empty states, the zero-table outlet showing its table-less order | **22/22** |
| Real browser (Chrome pane, the real POS page rendered for the Cashier session + real CSS/JS, only `myTablesAjax` answered from the captured fixtures): chips 4/5/5, filter row, 10 cards; Location=6 → one call with `outlet_id=6`, one view-only card, chips 1/0/1, Clear shown; view-only order → sheet with reason + "Switch to Rockbell lounge Wetspot" → SweetAlert; Clear → unfiltered refetch; actionable order → six buttons (waiter in subtitle); other-outlet free table → switch prompt, nothing selected; same-outlet free table → selected, panel closed, toast; Manager fixture → 5 view-only cards, sheet says "role cannot act", no switch; one-outlet Waiter → no filter row, "My tables"; two-outlet Waiter → Location only, both outlets listed, outlet shown on cards | PASS |
| `setOutletSession/<id>/pos` (HTTP): Cashier 1→6 lands on Sale/POS and the session outlet is 6; unassigned outlet → chooser; without `/pos` unchanged (middleman / dashboard); Admin `/pos` → Sale/POS | PASS |
| Regression: Stage 5a suite 55/55, deep-link parser 16/16, JS syntax, PHP lint | green |

**Not verified by tooling:** a switch followed through in a logged-in browser (the harness cannot
carry the session across the redirect); the sheet's six actions themselves are unchanged from Stage 3.

### Stage 5c — entry points and retirement (2026-09-15). Local only; not committed; not on live.
- **Landing.** The panel opens on load for every staff role (`ir_tables_panel_on_load` = 1 unless
  a customer session: self order / online order). Login routes are unchanged: Waiter and
  single-outlet Cashier → `Sale/POS` (panel open); multi-outlet Cashier/Waiter → outlet chooser →
  middleman → `Sale/POS`; Admin → chooser → Dashboard. As before, the panel also reopens after
  each new order is placed — now for every role.
- **Entry points.** The POS header's chair icon ("Table Status") now opens the panel in place for
  everyone (the waiter-only grid icon is gone; it was a second opener). Sidebar "Table Status" →
  `Sale/POS`. `Monitor/tables` and the `table-status` route redirect to `Sale/POS` (bookmarks
  keep working); `Monitor::tablesAjax` and its private helpers, `views/monitor/tables.php`,
  `_table_cards.php`, `table_status.js`, `table_status.css` removed. `Sale_model::getTableStatus()`
  stays (it feeds `myTablesAjax`); the POS's `?ir_action=` / `?ir_table_id=` parser stays (tested,
  harmless, any link may use it). Running Orders and Order Lookup untouched.

| Test | Result |
|---|---|
| HTTP on rexlio_scratch: `Monitor/tables` → 307 `Sale/POS` for Admin/Cashier/Manager/Waiter; `table-status` route → `Sale/POS`; `tablesAjax` gone; POS page for five role sessions carries `on_load=1`, exactly one `#ir_tables_open` with the chair icon, no old icon/link, the panel markup; sidebar item → `Sale/POS`, no `Monitor/tables` link left; Running Orders / Order Lookup 200; chooser → middleman → `Sale/POS/53/1` for the Cashier, → Dashboard for Admin | **28/28** |
| Real browser (real POS page rendered for the **Admin** session, real scripts): panel opens by itself on load with no trigger (server unreachable in the harness → local-only, "+ New Table" disabled, as designed); close; chair icon reopens it; with the server answered: "Tables", chips 4/5/5, filters, 10 cards, "+ New Table" enabled | PASS |
| Regression: 5a 55/55, 5b 22/22, deep-link 16/16, PHP lint, JS syntax | green |

Note: an unknown method on any controller answers with this install's 404 page under HTTP 500
(pre-existing, e.g. `Monitor/nothingHere`); the removed `tablesAjax` behaves the same.

## 7. Owner's end-to-end checklist (real browser, real logins) — the part tooling cannot do
Permissions are snapshotted at login: **sign out and in** on every account first.
1. **Waiter (one outlet)** — login lands on the POS with the panel open: own tables only, no
   filter row, "+ New Table" → a `<name>-000N` card, add items, place the order → the panel
   reopens showing the table with its value. Tap it → six actions; Print Bill and Cancel (with
   the reason prompt) work in place.
2. **Waiter assigned to two outlets** (set a second outlet on the user first) — login goes to
   the outlet chooser, then the POS with the panel; a **Location** filter shows both outlets by
   default; a table of the other outlet is view-only and offers "Switch to <outlet>"; the switch
   lands on that outlet's POS (its register must be open).
3. **Cashier** — login lands on the POS with the panel open (chooser first if several outlets):
   Outlet / User / Date filters, Occupied / Free / Orders chips, everybody's tables **and the
   table-less orders** (today's `sAAB…` / `aAAB…` shape). Tap another waiter's order → six
   actions; Invoice it. Other outlet → view-only + switch.
4. **Admin** — login lands on the Dashboard (unchanged). Click the POS → panel open with every
   outlet; sidebar "Table Status" and the header chair icon both land here; the old
   `…/table-status` bookmark too.
5. **Manager without `act_on_any_running_order`** — sees everything, every other user's order
   says "your role cannot act on it"; grant the permission on the role screen, sign out/in →
   six actions appear.
6. **Customer self-order / online-order session** — no panel, no chair icon.

### P1 — the panel always starts from an empty cart (2026-09-19). Local only; not on live.
**Bug (data integrity):** the cart survived a table switch. The only cart reset in the POS was the body
of the footer *Cancel* button; the tables panel never touched the cart, so a cart built for table A - or
an order loaded with *Modify* (which leaves a hidden `.modification` marker, `update_sale_id` and the
*Update Order* label behind) - was still there after tapping table B. *Update Order* would then move
sale A onto table B; a fresh cart would be placed on the wrong table.
**Fix:** one `irClearCart()` (the Cancel body, plus: forget the chosen table, the order being modified,
the header tooltip; returns the rows discarded). Cancel keeps its confirm and calls it. **Every entry into
the panel** (`irOpenTablesPanel`: header chair icon, all Tables buttons, auto-open after placing, deep
links) calls it first, no exceptions; a toast says *Cart cleared - choose a table to start again* when
rows were discarded. Business rule: opening the panel just to look also discards an unfinished cart.

| Test | Result |
|---|---|
| Real browser on rexlio_scratch (cashier, real menu items, real panel data): **reproduction** - 2 items on Table 2 + the Modify state of sale S5D260915-004; with the hook disabled, opening the panel and tapping Table 8 left cart 2 / 50 000, marker, *Update Order*, table 8 (the corruption); with the hook, opening the panel emptied everything (cart 0, no marker, *Place Order*, no table) and the toast showed; tapping a table afterwards set only the table. Rail *Tables* button clears too; footer Cancel still confirms then clears; opening with an empty cart shows no toast | PASS |
| Regression: 5a 55, 5b 22, 5c 28, deep link 16, Part B 18 + 21, menu/POS 6 | green |

### P3 — Order Details on the table card (2026-09-19). Local only; not on live.
A seventh sheet action, **Order Details**, first and full-width above Modify: the running-order panel's
existing read-only modal (`get_details_of_a_particular_order_for_modal`) - items, quantities, prices,
totals. Adoption-first like the other actions (the order is pulled onto this till if it is not), and it
touches neither the cart nor the panel (the sheet closes, the panel stays open behind the modal). The
`?ir_action=details` deep link works too. Theme css 7.9.2, pos_script 5.6.

| Test | Result |
|---|---|
| Real browser on rexlio_scratch: seven sheet actions in order (details first, spanning both columns); tapping Order Details on an order not yet on this till adopts it and opens the modal with its two items and 42 000 total; cart 0 / *Place Order* / no modification marker before and after; panel still open | PASS |


### P2 — placed items visible but locked on Modify (2026-09-19). Local only; not on live.
**Question answered:** can existing items be edited / removed on *Modify* by any role? Today, yes for
everyone: the rows loaded by Modify were ordinary cart rows, and the only guard was the vendor's `pos_7`
(*delete item when modifying*, a reason prompt) - which on live data Waiter holds and Cashier does not,
and which the waiter app bypasses in code. Reusing it would have locked the wrong people.
**Decision (owner, 2026-09-19):** permission-based, a **new** POS function `pos_26` *Edit placed items
when modifying an order*, granted by migration `2026-09-19_01_pos-edit-placed-items.sql` to **Admin and
Manager only** (not Cashier, not Waiter; grant under Settings › Roles if wanted; snapshotted at login).
**Mechanics:** the Modify row builder marks every placed row `ir_placed` + `data-placed_qty`, and adds
`ir_locked` when the role lacks `pos_26`. Locked rows keep their figures, lose the pencil and the ×, and
show a lock icon. Enforcement is in the handlers, not only the icons: `.edit_item`, `.removeCartItem`
and *decrease* (below the placed quantity) each refuse with a toast (*Already placed - this item cannot
be changed here. You can add more of it; ask a manager to change or remove it.*). *Increase* still works
(it is a new addition). Items **added during** the modify session are not `ir_placed`: fully editable,
removable, and they skip the vendor's `pos_7` reason prompt - that prompt now applies to placed rows
only. Holders (Admin/Manager) get today's behaviour unchanged, kitchen-status checks and `pos_7`
included. pos_script 5.8, theme css 7.9.3, hidden inputs `pos_26` + `ir_msg_item_locked`.

| Test | Result |
|---|---|
| Migration on rexlio_scratch: PASS, re-run PASS; preflight row APPLIED; Admin + Manager granted, Cashier and Waiter not | PASS |
| Real browser, **Cashier** (no `pos_26`): Modify S5D260915-004 → both rows `ir_placed ir_locked`, lock icon shown, pencil and × hidden; clicking edit / remove refused with the toast; decrease at the placed quantity refused; increase allowed (2 → 3, total 62 000); a newly added item is unlocked, editable and removable with no reason prompt; placed rows untouched throughout | PASS |
| Real browser, **Manager** (`pos_26`): same order → rows `ir_placed` only, pencil and × visible, no lock; decrease on a placed row goes through the vendor reason prompt (2 → 1, total 22 000); edit handler passes the guard | PASS |
| HTTP/static suite (12): migration re-run PASS, grant list exactly Admin + Manager, `pos_26` hidden input empty for the cashier and `1` for the manager, guards present in all three handlers, css | PASS |
| Regression: 5a 55, 5b 22, 5c 28, deep link 16, Part B 18 + 21, menu/POS 6 | green |

### P4 — waiter auto-logout after order placement becomes a switch (2026-09-20). Local only; not on live.
**Gap confirmed:** the feature's offline safety covers the *moment of placing* (never fires for an offline
save; re-checks the server before leaving). It cannot cover the connection dropping *after* the logout and
*before* the waiter signs back in — login is server-only, so the waiter is stuck out until the network
returns. No timing check closes that; the feature has to be switchable.
**Where:** Settings › **Modules**, a new **POS behaviour** group under the add-ons — chosen over a field on
the vendor's Settings form because it already gives exactly what is needed: one business-wide row,
Admin-gated (`modules › update`), audit-logged, read per request (no re-login), no schema change (one
`tbl_modules` row), trivial rollback. A `tbl_companies` column would have needed an ALTER, the vendor's
big form, and a login snapshot — the wrong shape for "turn it off now, the network is bad".
**Mechanics:** registry entry `waiter_auto_logout` (`kind: switch`, `default: 1`, no tables);
`irModuleEnabled()` answers the registry default while the row is missing (add-ons have none, so they still
fail closed); the POS hidden input becomes `role AND switch`. **`irWaiterAutoLogout()` in pos_script is
byte-identical** — with the switch OFF the flag is 0 and the function returns on its first line, before any
connectivity check; with it ON every existing guard runs as before. Migration
`2026-09-20_01_waiter-auto-logout-switch.sql` inserts the row ON. Applies the next time the sale screen opens.

| Test | Result |
|---|---|
| HTTP suite (21) on rexlio_scratch: migration PASS / re-run PASS; Modules screen groups Add-ons then POS behaviour, switch ON with *Switch off*; cashier refused; waiter POS flag 1 / cashier 0 while ON; toggle OFF → row 0, updated_by, audit row, OFF badge, waiter flag 0, cashier 0, hotel untouched; back ON → flag 1; **row deleted** → waiter flag 1 (default ON), screen shows ON + "on by default until installed" naming the migration, no button, toggle refused | PASS |
| JS (6) running the real `irWaiterAutoLogout` text with stubs: flag 0 online/offline → no connectivity check, no timer, no request, no navigation; flag 1 offline → stopped by the polled flag; flag 1 online + server answers → toast, 3 s, live re-check, navigate; flag 1 online + server gone → stays with the warning; function identical to HEAD | PASS |
| Real browser (Admin): screen as above; *Switch off* click → row OFF, "Module updated", *Switch On*; waiter's POS page carries `ir_waiter_auto_logout` 0 while OFF and 1 after switching back | PASS |
| Regression: 5a 55, 5b 22, 5c 28, deep link 16, Part B 18 + 21, menu/POS 6, P2 12; hotel h0 16, h1 35, h2 47, h3 60, h4 33 (two assertions scoped to the hotel row now that the screen lists two rows), h5 35, h6 20, h7 18, h8 13, h9 15, h10 20, h11 16 | green |

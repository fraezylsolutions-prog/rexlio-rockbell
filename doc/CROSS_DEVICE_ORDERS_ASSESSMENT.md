# Cross-device / cross-user running-order access — architectural assessment

Date: 2026-09-14. Status: **assessment only, nothing implemented**. Author: Claude (with the owner).

Client request: any user holding the relevant POS permission should be able to see and act on
any running order, regardless of which till placed it, which user placed it, or which outlet.
Running Order screen to gain outlet / user / date / order-number filters.

This conflicts with the Phase C decision that running orders are IndexedDB-authoritative and
device-bound. Everything below was verified by reading the code paths named, not assumed.

---

## 1. What the system actually does today (findings)

### 1.1 The server already holds a copy of every running order
`tbl_kitchen_sales.self_order_content` is the **full order JSON**, and its lifecycle is complete:

| Event | Server effect | Where |
|---|---|---|
| Order placed (online) | row inserted, `self_order_content` = full JSON | `Sale::add_kitchen_sale_by_ajax` |
| Order placed (offline) | **not written** until the order is modified while online, or completed (`push_online` inserts straight into `tbl_sales`) | `pos_script_v7.3.js:1751` reads `recent_sales` only |
| Order modified | same row **updated**, JSON rewritten, `is_update_*` flags set | `add_kitchen_sale_by_ajax` (update branch) |
| Order invoiced | `tbl_sales` row written; kitchen row deleted (pre-payment mode) | `Sale::push_online` |
| Order cancelled | kitchen row deleted | `Sale::add_cancel_audit_report` |

The standalone **Running Order screen (Monitor) already reads this table**, cross-user, with
user and table filters (`Sale_model::getRunningKitchenOrders`). So "visibility" is not the
missing piece; "acting from another device" is.

### 1.2 A device-to-device replication engine already exists (vendor "waiter order module")
Every 7 s while online, `new_notification_interval()` → `Sale/getWaiterOrders` and the client:
- **adopts** new orders addressed to this user into IndexedDB (`add_sale_by_ajax`, stamping
  the *local* user id so `displayOrderList()` shows them — `pos_script_v7.3.js:12360`);
- applies remote **updates** (`updateOrderForWaiter`), **invoices** (`closeOrderForWaiter`),
  **deletes** (`deleteOrderForWaiter`), and removes already-invoiced orders.

Plus two manual paths: the "Pull other device orders" header button (own orders, own outlet,
via `get_all_running_order_for_new_pc`) and "Logout → Submit" handover (`tbl_running_orders`).

**Conclusion: the vendor's model is not "device-only". It is device-authoritative for acting,
server-mirrored for kitchen/monitor, with point-to-point replication.** The request is an
extension of this hybrid, not a reversal of it.

### 1.3 Why it does not give cross-device access today — four deliberate limits
1. **Routing is point-to-point.** `tbl_users.order_receiving_id` names ONE receiver per user;
   `order_receiving_id_admin` is the legacy `role='Admin'` user (id 1) only. A cashier only
   receives from waiters that name them.
2. **One-shot delivery flags.** `pull_update` 1→2, `is_update_receiver` etc. Each change is
   delivered to exactly one receiver, once. Cannot fan out to N devices.
3. **Local panel filter.** `displayOrderList()` shows only IndexedDB rows whose `user_id`
   equals the logged-in user (`pos_script_v7.3.js:873`).
4. **Session outlet** is in every query (`getWaiterOrders*`, `get_all_running_order_for_new_pc`).

### 1.4 A hidden landmine: order numbers are not unique across devices
`generateSaleNo()` (`pos_script_v7.3.js:2040`) builds
`username_short + YYMMDD + "-" + localStorage counter`. `username_short` is the first letter of
the name plus **two random capitals drawn on every POS page load** (`getShortName()` →
`getRandomCodeTwoCapital(2)`); the counter lives in the **browser** and resets daily. So two
tills collide when they draw the same two letters: a **1-in-676 lottery per till-pair per day**,
and when it hits, *every* order that day for that pair collides (`-001` vs `-001`, `-002` vs
`-002`…). (Earlier drafts of this document said "identical on both"; that was overstated.)

Server side, `add_kitchen_sale_by_ajax` looks the number up and **UPDATES if found**, so
Till B's first order silently **overwrites** Till A's first order (kitchen, KOT, Monitor).
`push_online` likewise updates an existing `tbl_sales` row rather than inserting — a second
invoice of the same number overwrites the first's amounts. There is **no unique index** on
`sale_no` in either table (verified: `SHOW INDEX`). Local `rexlio` currently has 0 duplicates,
which only shows it has not been hit yet, not that it cannot be.

This is pre-existing, and it is **exactly the client's example #1** (waiter on Till A, then
Till B). Today it is masked because each device only displays its own IndexedDB. Any
cross-device design must fix order identity **first**, and that fix touches everything that
keys on `sale_no` (KOT, bill, reports, split suffixes `-1/-2`, `get_plan_string`, audit).

Every order also carries a 15-character `random_code` generated at creation and preserved
through modifies (`pos_script_v7.3.js:8834`, stored in `tbl_kitchen_sales.random_code` and
`tbl_sales.random_code`). It is a true per-order identity token the server can use to *detect*
a clash instead of silently updating.

### 1.5 Other constraints found
- Cancel is local-only plus an audit call; there is no server-side "cancelled elsewhere"
  signal for other devices except the row vanishing.
- The 7 s poll uses `async:false` (synchronous XHR). Pulling *every* permitted order on every
  device every 7 s would freeze the UI and load the server.
- Sales credit and money: `tbl_sales.user_id` / `counter_id` come from the **invoicing**
  session. Acting on Till B moves the sale and its cash to Till B's user and register.

---

## 2. Answers to the four questions

### Q1 — Achievable without abandoning offline-first?
**Yes.** Offline-first here means "a device can place, modify and invoice the orders it holds
without the server". That property belongs to the *device that holds the copy* and is not
touched by letting *other, online* devices obtain a copy. What must fundamentally change:

1. **Order identity** — server-issued or device-suffixed sale numbers (see 1.4).
2. **Routing** — permission-based ("orders this user may see") instead of `order_receiving_id`.
3. **Delivery** — a `version`/`updated_at` on `tbl_kitchen_sales` plus a per-device
   "last seen" cursor, replacing one-shot flags, so N devices can each learn of a change.
4. **Concurrency** — optimistic checks: Modify/Invoice/Cancel send the version they acted
   on; the server rejects a stale one instead of silently overwriting (today: last-writer-wins).
5. **Fan-out removal** — when any device invoices/cancels, every device holding a copy drops it
   (the vendor's `already_invoiced_orders` / `deleteOrderForWaiter` paths, broadened).

The server does **not** have to become the sole authority; it becomes the *source of adoption*
plus the *arbiter of conflicts*. Devices stay authoritative for the copy they hold.

### Q2 — Scope: three tiers

| Tier | What the client gets | Real work | Offline impact |
|---|---|---|---|
| **1. View-only, all devices/users/outlets** | Running Order screen shows every permitted order with outlet, user, date and order-number filters. Client items #2 and #3 for *viewing*. | Filters + permission gate on an already server-backed screen (`getRunningKitchenOrders`, `getAccessibleOutletIds`). **1–2 days** incl. tests. | None. |
| **2. "Open on this till" (deliberate adoption)** | From that screen, a permitted user clicks *Open here*; the server copy is adopted into this till's IndexedDB (existing `add_sale_by_ajax`), the Phase C deep-link selects it, and the **unchanged** Modify / KOT / Bill / Invoice / Cancel actions work. Client items #1 and #2 for *acting*. | Sale-number uniqueness fix (own step, migration + client change); `version` column and stale-version rejection on the three write endpoints; poll extension so adopted/originating copies refresh or drop when changed elsewhere; originating till told its copy moved. **5–8 working days**, staged on one outlet first. | Adoption needs the network (inherent). After adoption the order behaves like any local order, including offline; on reconnect the server rejects the push if the order was invoiced/cancelled elsewhere and the till is told. |
| **3. Continuous mirror (every permitted order appears on every POS panel automatically)** | Live, automatic cross-device panel. | Replace routing and flags wholesale; async incremental sync with cursors; conflict UI ("changed on Till A"); load design for N tills × 7 s × full set. **2–4 weeks**, a full new Phase with a staging period on real tills. | Each device stays *available* offline, but *consistency* becomes eventual and connectivity-dependent by definition; conflict policy must be explicit. |

### Q3 — What is lost?
- **Tier 1:** nothing.
- **Tier 2:** nothing for the originating till's offline ability. New risk is two tills holding
  the same order; that risk exists *already* via the vendor's pull paths and is currently handled
  by silent overwrite. Tier 2 replaces silent overwrite with explicit rejection — an improvement.
  Business semantics change: the adopting till's user gets the sale and its register gets the
  cash. The client must decide whether that is wanted.
- **Tier 3:** offline *availability* is kept; offline *consistency* is not. An order placed on
  Till A while offline is invisible elsewhere until A reconnects; if B (online) and A (offline)
  both act on it, a winner must be chosen and someone told. The more tills hold copies, the more
  the system depends on connectivity to *agree*, even though each till still *works*. This is the
  classic availability-vs-consistency tradeoff and cannot be engineered away, only managed.

### Q4 — Honest recommendation
1. **Do Tier 1 now.** Cheap, safe, no architecture change, gives most of #2 and #3 immediately.
2. **Fix order-number uniqueness as its own step regardless** — it is a latent data-integrity
   bug in the live system today (same user, two tills, same day), independent of this request.
3. **Plan Tier 2 as a proper Phase** (design → migration → staged rollout on one outlet →
   client sign-off), *after* the numbering fix.
4. **Defer Tier 3.** Decide only after Tier 2 has run live for a few weeks and the client
   confirms deliberate "open here" is not enough. Do not start with Tier 3.

### Correction (2026-09-14): registers are per counter, not per till or per user
Verified: `tbl_register.counter_id` is the key; `tbl_sales.counter_id` is stamped from the
session; close-out totals are computed by **counter + outlet + time window**
(`Register.php:361-387` → `getAllSaleByPaymentForRegister(opening_time, payment, counter_id,
outlet_id)`) — never by `user_id`; there is no device identifier anywhere. Every live outlet
has exactly one counter. **Within an outlet, money is a non-issue for adoption.**

Consequences for Tier 2:
- The "credit" decision shrinks to one confirmation: `tbl_sales.user_id` becomes the invoicing
  cashier. `waiter_id` travels inside the order JSON and survives adoption, so waiter reports
  are unaffected.
- Effort **5–8 → 4–7 days**; the heavy items (numbering, versioning, fan-out removal,
  originating-till notice) are unchanged.
- The numbering fix (1.4) becomes **more** urgent: "any waiter on any till" makes
  same-user-two-tills-same-day routine, which is exactly the collision case.
- Cross-outlet does **not** collapse: different outlet = different counter/register and
  per-outlet stock. Keep cross-outlet view-only in Tier 2.
- Live data shows two cashiers can hold *overlapping* open registers on one counter
  (`tbl_register` 37 & 39, outlet 5, counter 6); close-out by time window counts the same
  sales in both. Pre-existing, unrelated to cross-device; noted for the client separately.
- POS entry gate: waiters need any open register at the outlet (`canPersistOrderNow`);
  cashiers need their own open register or a session `counter_id` (`isOpenRegister`).

### Decisions the client must make before Tier 2 design starts
- Confirm `tbl_sales.user_id` = the invoicing cashier is acceptable reporting semantics.
- Which roles may adopt: cashiers only, or waiters too? May an adopter **cancel**?
- Should the originating till be *locked out* of an adopted order, or *refreshed* and allowed
  to continue (last-version-wins with explicit rejection)?
- Cross-outlet: confirm view-only (recommended — different counter, register and stock).

---

## 3. Code references
- Server copy write: `application/controllers/Sale.php` `add_kitchen_sale_by_ajax` (insert/update branch ~line 1500)
- Completion: `Sale::push_online` (~2282); cancel: `Sale::add_cancel_audit_report` (~1937)
- Replication loop: `frequent_changing/js/pos_script_v7.3.js` `new_notification_interval` (~14090), interval at ~14210
- Routing model: `application/models/Common_model.php` `getWaiterOrders*` (1533–1660)
- Adoption: `add_sale_by_ajax` (~12344); panel filter: `displayOrderList` (~851, user filter ~873)
- Manual pulls: `#pull_others_device_orders` (~1890), `pull_running_order*` (~15018)
- Order numbers: `generateSaleNo` (~2040), daily reset `removeInvoiceDate` (~1059)
- Running Order screen: `application/controllers/Monitor.php`, `Sale_model::getRunningKitchenOrders`
- Phase C deep-link: `pos_script_v7.3.js` ~17342 (`open_sale_no`)

---

## 4. Step 1 — order-number uniqueness (design, 2026-09-15)

### 4.1 Audit: everything that reads `sale_no` other than by equality
| Consumer | What it does | Impact of a longer number |
|---|---|---|
| `get_plan_string()` (`pos_script_v7.3.js:3531`) | strips `-`, `\|\|`, `,`, whitespace; lowercases; used as DOM id | none |
| Monitor order lookup (`Sale_model.php:2245`) and Report detailed sale (`Report_model.php:1808`) | `LIKE '%x%'` search | none |
| Split sale (`Sale.php:~2072`) | `parent + "-" + n` | none; length +1 |
| KOT / bill / invoice print, audit log, merge, reports, Monitor deep-link | opaque string, equality lookups | none (one extra character on receipts) |
| `tbl_orders_table`, `tbl_running_order_tables`, `tbl_running_orders` | `sale_no varchar(20)` | **widen to varchar(50)** — today's max is 13 + `-NN` |

Nothing derives the user, date or device from the number. Reports use `user_id` / `sale_date`.

### 4.2 Scheme: server-issued, browser-persisted device tag
Format stays `prefix + YYMMDD + "-" + counter`; only the prefix changes:

    today:   t  WN  260915-001     (WN = random per page load)
    new:     t  AB7 260915-001     (AB7 = this browser's device tag, issued once)

- **Tag issue.** On POS load, if `localStorage.ir_device_tag` is empty the page asks
  `Sale/issueDeviceTag`, which inserts a row in `tbl_device_tags` and returns the
  auto-increment id encoded as three base-32 characters (alphabet without 0/O/1/I/L).
  Sequence-based ⇒ **unique by construction**, no lottery. The POS page cannot load offline,
  so a first-time browser always has the server available. Fallback if the call fails:
  random 3-char tag (old odds) and retry on next load.
- **Number.** `generateSaleNo()` = first letter of name + device tag + YYMMDD + `-` + counter.
  Counter unchanged (per browser, reset daily). 14 characters; split adds `-n`.
- **Server guard, placement** (`add_kitchen_sale_by_ajax`): if a live kitchen row already has
  this `sale_no` **and** a different `random_code`, it is a different order wearing the same
  number → reject with `invoice_status=1`, `sale_no_conflict=1`, message
  `lang('sale_no_conflict')`. Never update another order's row.
- **Client recovery on conflict**: fetch a fresh tag, renumber the local IndexedDB record
  (`sales` store + its `orders_table` rows), re-render, re-push to kitchen. Operator sees one
  toast; nothing is lost.
- **Server guard, completion** (`push_online`): if a live `tbl_sales` row already has this
  `sale_no` and a different `random_code` → reject with `sale_no_conflict=1`; the client shows
  one error toast for that order and leaves it queued (money already taken; never overwrite).
  With server-issued tags this can only happen via a cloned browser profile.
- **Migration** `2026-09-15_01_device-tags.sql`: create `tbl_device_tags`; widen the three
  `varchar(20)` columns to `varchar(50)`. No data rewrite; existing numbers stay valid.

### 4.3 Test plan (scratch DB first)
1. Fresh browser gets a tag; second fresh browser gets a different tag; tags persist.
2. Two tills, same user, same day: numbers differ by construction.
3. Forced clash (two tills given the same tag by hand): placement on the second till is
   rejected, renumbered, re-pushed; the first till's order is untouched.
4. Forced clash on completion: second push rejected, first sale untouched, toast shown once.
5. Split, KOT reprint, bill, Monitor deep-link and order lookup with a 14-character number.
6. Existing (13-character) running orders still modify / invoice / cancel.

### 4.4 Step 1 — implemented and tested (2026-09-15). NOT yet on live.
Files: `db/migrations/2026-09-15_01_device-tags.sql` (new), `Sale.php` (`issueDeviceTag`, two
guards), `my_helper.php` (`irDeviceTagFromId`, `irIsSaleNoConflict`), `pos_script_v7.3.js`
(`generateSaleNo`, tag helpers, `irRecoverSaleNoConflict`, `irNoteCompletionConflict`;
cache-buster `?v=4.4`), `hidden_input_html.php` (2 inputs), 4 language files (3 keys).

| Test | Where | Result |
|---|---|---|
| Migration applies, re-applies without error, columns as specified | `rexlio_scratch` (throwaway copy of local dev DB) | PASS |
| Tag encoder: 40,000 ids → 40,000 distinct tags; no 0/O/1/I; 3 chars up to 32,767 | PHP unit (13 checks) | 13/13 |
| Conflict guard semantics incl. legacy rows without a code | PHP unit | PASS |
| Number format, counter, two tills differ, offline fallback, placement-clash renumber (record + JSON + tables, unrelated rows untouched, one toast, one re-push), self-order path, completion-clash notice once | Node, real functions extracted verbatim, fake IndexedDB (18 checks) | 18/18 |
| `issueDeviceTag` twice → AAB, AAC, rows stamped; placement clash → `sale_no_conflict`, first till's row byte-identical, still one row; same code → not flagged; completion clash → `SALE_NO_CONFLICT`, completed sale untouched | HTTP against local WAMP + local dev DB, cloned local session | 13/13 |

Not verified: a real browser driving the real POS page (the browser pane cannot carry the
session cookie). That is what the two-till staged rollout in step 3 is for.
Number length is 14 (was 13): `t` + 3-char tag + `YYMMDD` + `-` + 3-digit counter.

# Phase D — Pricing Tiers (Rexlio)

Reference for the pricing-tier work: what was built, **why the odd-looking decisions
are deliberate**, and what was consciously deferred.

---

## 1. The core design decision: `price_tier` is separate from `order_type`

Before this work, one control decided two unrelated things:

- **how the order is fulfilled** (dine-in / take-away / delivery), which correctly
  gates whether a table can be selected, and
- **which price applies**, read from `tbl_food_menus.sale_price*`.

VIP and Club are *price tiers*, not fulfilment types — a VIP-priced order is still a
customer sitting at a table. Adding them as `order_type` 4 and 5 would have meant
auditing every site that branches on `order_type` (17 table-gating sites alone, plus
invoices, KOT, reports and `getOrderType()`), and any missed site would silently
mistreat a VIP order as an unknown fulfilment type.

**So `price_tier` is a new, separate field.** `order_type` still means fulfilment only
and still takes values 1/2/3.

| tier_key | Name      | Price column           |
|----------|-----------|------------------------|
| 1        | Regular   | `sale_price`           |
| 2        | Outlet    | `sale_price_take_away` |
| 3        | Delivery  | `sale_price_delivery`  |
| 4        | VIP       | `sale_price_vip`       |
| 5        | Club      | `sale_price_club`      |

Consequence worth remembering: **the table-gating logic needed no changes for VIP/Club**,
because those orders never set `order_type = 3`.

---

## 2. VIP and Club are now two separate tiers (superseded the "VIP|Club" button)

**Current state:** the sale screen has two independent buttons, **VIP** (tier 4,
`sale_price_vip`) and **Club** (tier 5, `sale_price_club`), each carrying its own
`data-tier_key`. The Food Menu add/edit form has a separate price field for each.

### History — why it was one combined button first

The original build shipped a single button labelled with the literal text `VIP|Club`,
both words together with a pipe, deliberately: different clients on this Rexlio base
use different words for the same tier, and per-client configurable labels belong to the
larger modular product (**Nuvora**) that this build is a temporary substitute for.
Showing both terms signalled that ambiguity without needing per-client configuration.

That was superseded when the client committed to VIP and Club as genuinely distinct
tiers with different prices. Per-client label configuration remains a Nuvora concern —
the labels are now plain `$lang['vip']` and `$lang['club']`.

### The bug that prompted the split, and the corrected diagnosis

Reported as: *"a counter set to Club does not lock — Club behaves like Regular, freely
switchable, while VIP locks correctly."*

**The counter lock was never broken for Club.** `getLockedPriceTier()` decides with
`return ($tier > 1) ? $tier : 0;` — a range test, so tier 5 was always included. This
was verified by replaying the function against live data for every counter: tier 5
returned `lock=5` exactly as tier 4 returned `lock=4`.

The reported behaviour was the combined effect of two *real* defects, which together
are indistinguishable from a broken lock:

1. **The single button was hardcoded `data-tier_key="4"`**, mirrored in two more places
   in `pos_script_v7.3.js` (`|| 4`, `new_tier = 4`). Whenever the row was *unlocked*,
   clicking it always selected tier 4 — **tier 5 was unreachable by manual selection**,
   only ever via a counter lock. A direct consequence of Club being left "unused" in
   the original design.
2. **`sale_price_club` was NULL on every item**, so the resolver's fallback returned the
   Regular price for Club on every line. Club was *functionally* identical to Regular
   in the cart even when the lock was working correctly.

Lesson worth keeping: a tier that is locked correctly but priced identically to Regular,
and whose button silently selects a different tier, presents exactly like a lock failure.
Check the price columns and the button's `data-tier_key` before suspecting the gate.

---

## 3. Terminology renames

| Was        | Now       | Notes |
|------------|-----------|-------|
| Dine In    | Regular   | `$lang['dine']` |
| Take Away  | Outlet    | `$lang['take_away']` |

Both were verified **display-only** before renaming: an exhaustive search across
`application/` and `pos_script_v7.3.js` found **zero** comparisons against the literal
strings `"Dine In"` or `"Take Away"`. All logic compares the numeric `order_type`.

Renamed in: the English language file, 7 hardcoded literals each in
`pos_script_v7.3.js`, `Sale.php`, `Order.php`, and `getOrderType()` in `my_helper.php`.

**Known gap:** the French, Spanish and Arabic files still carry the *old* wording
(`Dîner dans`, `À emporter`, etc.). They were deliberately not machine-translated —
"Regular" and "Outlet" are client-specific terminology choices, so a human should pick
the wording per language.

---

## 4. What the VIP and Club buttons do

- Each sets `#selected_price_tier` from its own `data-tier_key` — VIP **4**
  (`sale_price_vip`), Club **5** (`sale_price_club`).
- **Neither sets `order_type`**, which stays dine-in (1), so **table selection remains
  available** exactly as for Regular.
- Both show the same "clears the cart" warning as the order-type buttons, because every
  line has to be repriced.

`#selected_price_tier` is the single source of truth read by `resolveItemPrice()`.
**All five tier-setting buttons set it** (Regular→1, Outlet→2, Delivery→3, VIP→4,
Club→5) so returning to Regular after VIP or Club restores normal pricing rather than
leaving the tier stuck.

`getSelectedPriceTier()` reads the tier off whichever button is selected rather than
hardcoding a value — that hardcoding was defect 1 in section 2 and must not return.

### The non-obvious bit: three `order_type` derivation sites

Three places derive `order_type` from whichever button is selected, each an if/else
chain over the three known `data-id` values, starting from `let order_type = 0`.
A new button in that group matches nothing, so `order_type` would stay **0** and be
written to `tbl_sales.order_type` — blank order type on invoices, KOT and reports.

Those three sites therefore treat `vip_button` and `club_button` **exactly like**
`dine_in_button`,
which also gives it the same waiter/customer validation. **Any future tier button added
to that row must do the same.**

---

## 5. Table gating — the current rule

Table selection is **disabled only for Delivery**. Enabled for Regular, Outlet, VIP|Club.

This also fixed a pre-existing inconsistency: the click handler used to disable tables
for take-away while the order-load paths enabled them. Now all three sites agree.
Rationale for allowing tables on take-away: a customer can be waiting at a table for a
take-away order.

---

## 6. Price fallback

An unset or zero tier price **falls back to the Regular `sale_price`**, both server-side
(`main_screen.php`) and client-side (`resolveItemPrice()`). An item with no VIP price
sells at its normal price rather than at zero.

---

## 7. Schema

`tbl_food_menus` → `sale_price_vip`, `sale_price_club` (FLOAT NULL)
`tbl_kitchen_sales` → `price_tier` (written at placement)
`tbl_sales` → `price_tier` (written at completion)
`tbl_holds` → `price_tier` (so a resumed hold keeps its pricing)
`tbl_counters` → `default_price_tier`
`tbl_price_tiers` → `id, tier_key, tier_name, price_column, is_active, sort_order, company_id, del_status`

`price_tier` lives on **both** `tbl_kitchen_sales` and `tbl_sales` because a POS order
is written to the kitchen table at placement and only reaches `tbl_sales` when the sale
completes and syncs. One alone would lose the tier.

Migration/rollback: `phaseD_01_migration.sql` / `phaseD_01_rollback.sql`.
**The rollback drops `sale_price_vip`/`sale_price_club`, discarding any tier prices entered.**

---

## 8. Completed since first release, and what remains

### Done (were previously deferred)

1. **~~Split VIP|Club into two independent buttons~~ — DONE.** Two buttons, `vip_button`
   (tier 4) and `club_button` (tier 5), each with its own `data-tier_key`; separate
   "Sale Price (VIP)" and "Sale Price (Club)" fields on the Food Menu add/edit form,
   both persisted by `FoodMenu.php`. Section 9's layout fix (`repeat(6, 1fr)` plus a
   `style2.css?v=` bump) was applied in the same change, and the row was re-verified as
   one row at 45px with no truncation at 1280 / 1440 / 1920.
2. **~~Counter default price tier dropdown~~ — DONE.** Added to counter add/edit,
   populated from `tbl_price_tiers`, defaulting to Regular. It also became the control
   for the counter tier **lock** (see below).

### Dropped by decision — not forgotten

3. **Admin UI to toggle tiers on/off.** Deliberately dropped in favour of the
   counter-lock design: a client who does not use a tier simply never assigns it to a
   counter, so the counter list *is* the practical on/off control. `tbl_price_tiers.is_active`
   still exists and is honoured by `Sale_model::getActivePriceTiers()`, so a true
   system-wide "pause this tier everywhere" switch remains available as a future option
   if one is ever needed. Disabling a tier affects **new** orders only — it never
   rewrites the tier recorded on an order already taken, so historical orders keep the
   price they were actually sold at.

### Still open

4. **Per-client configurable tier labels** — a Nuvora concern. Labels are currently the
   plain `$lang['vip']` / `$lang['club']` strings.
5. **French, Spanish and Arabic translations** for the Regular/Outlet renames (see
   section 3). English is complete. `vip`, `club` and the tier-lock strings were added
   to all four language files, but with English values in the non-English ones.
6. **The layered-vs-replace interaction question.** VIP and Club sit in the same button
   group and *replace* the Regular/Outlet/Delivery selection visually, while underneath
   the order stays dine-in. Whether a tier should instead display *alongside* the
   fulfilment type (e.g. "Regular + VIP") is an open design question.
7. **Wiring up `setHeightInComponent()`** as the durable fix for the button-row/footer
   layout fragility — see section 9.

### Counter tier lock (built after first release)

A counter's `default_price_tier` does more than pre-select. When set to anything other
than Regular, the **whole order-type row is locked** for ordinary staff at that counter:
all six buttons render disabled with the counter's tier selected, and both `price_tier`
and `order_type` are pinned server-side in `add_kitchen_sale_by_ajax()` so a re-enabled
button or a direct POST cannot bypass it. Admin and Manager (and any role named in
`priceTierOverrideRoleNames()`) keep full override. Counters left on Regular are
unaffected — free selection for everyone, exactly as before.

Locking the *whole row* rather than the tier alone was deliberate: it removes the
otherwise ambiguous case where a take-away order at a VIP counter would be billed at
VIP rates.

---

## 9. ⚠ Known fragility: the sale-screen button row holds exactly 6 buttons

**A seventh button in the order-type row will hide the Cancel / Draft / Quick Invoice /
Place Order footer.** This has already happened once and been fixed twice:

| Change | Buttons | Grid |
|---|---|---|
| Original vendor build | 4 (Dine In, Take Away, Delivery, Table) | `repeat(4, 1fr)` |
| VIP\|Club button added | 5 — **broke the footer**, fixed by raising the grid | `repeat(5, 1fr)` |
| VIP/Club split into two | 6 — grid raised **in the same change**, no breakage | `repeat(6, 1fr)` |

The second row is the incident this section was originally written about; the third is
the split, where the fix below was applied pre-emptively and the row never wrapped.

### What happens

`assets/POS/css/style2.css` styles that row as a CSS Grid with a **hardcoded column
count**:

```css
#main_part .left_item .main_middle .button_holder {
  display: grid;
  grid-template-columns: repeat(6, 1fr);   /* 4 originally, 5 with VIP|Club, 6 after the split */
  gap: 8px;
  padding: 5px; }
```

Exceed the column count and the extra button wraps to a second grid row. Measured
values from the VIP|Club incident: the row went from **45px to 88px** tall, and that
extra 43px pushed the footer button row below the bottom of the viewport. The cart and
"Total Payable" still rendered, so it looked like the footer had simply vanished.

**This is not width-dependent.** It wraps identically at 1280px and at 3840px, so
testing at different screen widths will not reveal or resolve it.

### Why nothing compensates

`setHeightInComponent()` in `application/views/sale/POS/main_screen.php` exists to size
`.main_center` as `winHeight` minus the surrounding chrome, and it explicitly subtracts
`.main_top`'s height — which would absorb a wrap. **It is defined but never called.**
Nothing in the views, `frequent_changing/js` or `assets/POS/js` invokes it. So the
layout has no give at all, and any height added to that row is height the footer loses.

This is pre-existing dead code, not introduced by Phase D.

### Before adding a seventh button

Any new button in that row needs all three steps, in the same change:

1. Raise the grid to `repeat(7, 1fr)` in `style2.css`, and
2. bump the `style2.css?v=` query string in `main_screen.php`, or browsers will serve the
   cached rule and the fix will appear not to work, and
3. verify the row still reports **one** row and ~45px height, and that no button label is
   truncated (the buttons already have `white-space: nowrap; text-overflow: ellipsis`, so
   they will ellipsize rather than wrap).

This is exactly the procedure followed for the VIP/Club split, which is why that change
never broke the footer. Verification was done with a throwaway harness loading the real
`style2.css` and the same DOM nesting, measured at 1280 / 1440 / 1920 — the app's own
sale screen needs a login, and the harness gives the buttons *less* room than the real
middle column, so it is a conservative check.

**Seven equal columns will be tight.** That is the point at which the durable fix is
probably worth doing instead: wire `setHeightInComponent()` to load and resize so the
layout absorbs its own content. Treat that as real work with its own testing — it changes
global page sizing and needs a POS login to verify.

**Do not forget the three `order_type` derivation sites** (section 4) when adding a tier
button. A button they do not recognise leaves `order_type = 0`.

Six equal columns in that space starts to get tight, so this is the point at which the
proper fix is probably worth doing instead: **call `setHeightInComponent()` on load and
on resize** so the layout adapts to its own content. That was deliberately left alone
here because it changes global page sizing and could not be exercised without a POS
login. Treat it as a real piece of work with its own testing, not a one-liner.

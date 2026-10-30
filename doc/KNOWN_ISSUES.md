# Known issues — found, not fixed

Things confirmed to be real, deliberately left alone, with the reason.

---

## Oversell guard in `add_kitchen_sale_by_ajax` is dead code

**Where:** `application/controllers/Sale.php`, lines **1425** and **1444**.

Line 1425 reads `$posted_order->items`; line 1444 is where `$posted_order` is
first assigned. So `isset()` is always false and the whole Phase F item 15
stock block never runs.

**Confirmed:** present in the baseline commit `7fa208f9` — it has never worked,
and is not a regression from the notifications work. Reproduced: an order for a
menu item whose recipe needs 9999 of an ingredient with 1 in stock is
**accepted and written**, with `allow_oversale = 'No'` on the company.

**Why it is not fixed here.** Moving the guard below the assignment is a
one-line change, but it is not a one-line *consequence*: the till would start
**refusing orders it currently accepts**, on every outlet, and would only be as
correct as the inventory data. On a trading venue that is a behaviour change
staff would feel immediately, so it needs a decision — refuse, or only warn —
rather than a quiet patch bundled into a release about notifications.

**If it is ever made live**, test through the real endpoint for: refusal when
stock is short; acceptance when `allow_oversale = 'Yes'`; acceptance when the
item has no recipe; and that modifying an existing order is not counted against
itself (the existing call already passes `sale_no` for exactly that reason).

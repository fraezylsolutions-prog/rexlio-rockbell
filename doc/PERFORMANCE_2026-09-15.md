# Performance investigation — Login, Running Order, Table Status (2026-09-15)

Client report: all three take 3 s+ on live (`rockbell.fraezyl.app`), should be "one touch".
Neither Step 1 nor Step 2 of the cross-till work is on live, so today's changes are ruled out
as the cause. Everything below is measured, not estimated. Local = WAMP on this machine, local
dev DB; live = public endpoints only (no credentials were used).

## 1. What the live network costs before any code runs
| Measurement (from Nigeria to the host) | Value |
|---|---|
| Host | Namecheap shared hosting, **Chicago, USA** (162.0.209.204), LiteSpeed |
| ICMP round trip | **256–290 ms** (avg 268) |
| 109-byte static file on a warm keep-alive connection | 0.278 s — i.e. one round trip |
| PHP login page on the same warm connection | 0.30–0.38 s → **server time ≈ 20–100 ms** |
| Cold connection (DNS + TCP + TLS) before the first byte | **≈ 1.1 s**, then +1 RTT |
| Static assets | brotli-compressed, `Cache-Control: max-age=604800` — cached 7 days after first visit |
| Dynamic pages | gzip (45 KB login page → 11 KB), `no-store` (always fetched) |

The live server is not slow; the distance is. Every screen open pays ≥ 0.27 s, and ≈ 1.4 s
whenever the browser has to open a new TLS connection. That alone is the difference between
"one touch" and "noticeably slow" and **no code change can remove it**. See §5.

## 2. LOGIN
"Login" for waiters and cashiers is the credential check **plus the POS page it lands on**.

| Step (local, warm) | Before | After | What was wrong |
|---|---|---|---|
| Login page GET | 66 ms, 11 queries | 41 ms, 9 | — |
| `loginCheck` POST (credential check) | 80 ms | 37 ms | — |
| `loginCheck` permission build, non-Admin | 1 query **per access row** (Cashier 44, Owner Admin 208), each a PK lookup | 1 `WHERE id IN (...)` query | N+1 loop in `Authentication::loginCheck` |
| **POS page (post-login landing)** | **2.92 s, 1,873 queries** | **0.37 s, 54 queries** | `getCompanyInfo()` read from the DB on every call — 1,247 times (≈6 per menu item via the price/format helpers); `Sale::POS` ran a variations query and a kitchen query **per menu item** (193 each); `main_screen.php` ran a promotion query per item (193) |

The POS HTML (800 KB, gzipped on live) is byte-identical before and after (verified with
variations, promotions and kitchen mappings seeded). With more menu items on live the "before"
number scales linearly; the "after" does not.

## 3. RUNNING ORDER screen
| Local, warm | Normal tables | At 40k session rows + 20k `tbl_orders_table` rows (months of live use) |
|---|---|---|
| Page, before | 129 ms, 19 queries | **247 ms** |
| Page, after indexes + memoization | 42 ms, 11 queries | 63 ms |
| Poll (`runningOrdersAjax`) | 96 → 48 ms | 220 → ~50 ms |

Bottlenecks, in order: (1) `tbl_sessions` has **no primary key** — the session is read *and*
written by `id` on every request, both full scans (15 ms at 16 rows, ~160 ms at 40k on a fast
SSD; worse on shared disk). (2) The card query counts kitchen items and lists tables with
correlated sub-queries, and neither `tbl_kitchen_sales_details(sales_id)` nor
`tbl_orders_table(sale_id)` was indexed — `tbl_orders_table` is never pruned (140 rows locally
for 2 open orders), so each card scanned it. (3) 9 repeated `tbl_companies` reads per page.
The query itself, with indexes, is 8–11 ms. The page renders its cards server-side and only
polls afterwards, so one HTML request + assets.

## 4. TABLE STATUS screen
Same profile: 157 ms → 50 ms normal; **271 ms → 56 ms** at live-like volume. Same three causes;
its main query went 52 ms → 1 ms with the indexes.

## 5. What remains after the fixes, and what to do about it
1. **Distance (dominant on live).** ≈ 0.27 s per request, ≈ 1.4 s on a cold connection. The
   host is in Chicago; the shop is in Nigeria. Moving the site to a European or South-African
   datacenter (Namecheap EU, or any VPS in Johannesburg/Lagos/Frankfurt) cuts the round trip to
   roughly 20–120 ms — the single biggest "one-touch" lever available, and it is hosting, not
   code. Same cPanel/LiteSpeed procedure as DEPLOYMENT.md.
2. **Asset weight on every admin screen.** The shared layout loads 24 scripts + 22 stylesheets
   (1.37 MB uncompressed) on Running Order / Table Status; the POS page 80 assets, 6.7 MB.
   Cached after first visit, but the browser still parses/executes them every open — on a
   low-end till this is a measurable 0.3–0.8 s. Not measured from here (needs DevTools on a
   till); a lean layout for the two Monitor screens is the follow-up if the owner's till
   measurement confirms it.
3. **Session garbage collection.** If the host's PHP never runs it, `tbl_sessions` grows
   forever. Migration 03 purges once; the primary key makes growth harmless afterwards.

## 6. Changes made (local only; not committed, not on live)
- `application/helpers/my_helper.php`: `irCompanyInfoCached()` — one `tbl_companies` read per
  id per request behind `getCompanyInfo()`, `getCompanyInfoById()`, `getMainCompany()`;
  `getKitchenNameAndId()` loads the outlet's category→kitchen map once; 
  `checkPromotionWithinDatePOS()` loads the outlet's promotions for the date once.
- `application/controllers/Sale.php` (`POS`): one `WHERE parent_id IN (...)` for all variations.
- `application/controllers/Authentication.php` (`loginCheck`): one `WHERE id IN (...)` for the
  role's access rows.
- `db/migrations/2026-09-15_03_perf-indexes.sql`: PK on `tbl_sessions`; indexes on
  `tbl_orders_table(sale_id)`, `(sale_no)`, `tbl_kitchen_sales_details(sales_id)`,
  `tbl_kitchen_sales(sale_no)`, `tbl_running_order_tables(sale_no)`; one-time purge of
  sessions older than a day. PASS on scratch and local.

Regression after the changes: Step 1 suites 13/13 · 18/18 · 13/13, Step 2 suites 21/21 · 23/23.

## 7. How the numbers were taken
`perf.sh` in the session scratchpad: per request, curl wall time plus MySQL
`performance_schema.events_statements_summary_by_digest` (query count, time, top digests)
filtered to the app schema, warm-up request first. Live: curl timing breakdown
(`time_connect`, `time_appconnect`, `time_starttransfer`) on public endpoints, keep-alive
reuse to separate server time from round trip, ICMP ping, IP geolocation.

---

## 8. POS Sale screen (Table Status → "New Order" / table card) — full timeline (2026-09-15, later)

The navigation target is `Sale/POS/{user}/{outlet}?open_sale_no=…` — the same page profiled
in §2. Server side is already fixed there (2.92 s → 0.37 s). This section measures the browser
half, which turned out to be the larger half.

### 8.1 Method
Real browser instrumentation (the desktop app's browser pane cannot carry the app's session
cookie, so the exact HTML the server produces for the waiter session was captured with curl and
served as a static file from the same WAMP host — same assets, same URLs, same size). Navigation
Timing, Resource Timing, a `longtask` observer and DOM milestone marks injected at the top of
`<head>`. The pane reports `document.visibilityState === 'hidden'`, so timers after
DOMContentLoaded are throttled there; **only DOMContentLoaded and network figures are quoted**,
and post-DCL milestones (gallery rebuild, IndexedDB) are explicitly NOT, because they could not
be measured honestly from this tool. Machine: 4 threads, 8 GB.

### 8.2 Where the time went (before today's page fixes)
| Phase | Cold cache (first visit) | Warm cache (Table Status → Sale) |
|---|---|---|
| Server (TTFB, after §2 fixes) | 0.32 s | 0.32 s |
| Requests | **122** | 107 (104 from cache) |
| Bytes transferred | **8.0 MB** (scripts 5.7, CSS 1.2, fonts 1.0) | 16 KB |
| HTML | 800 KB (51 KB gzipped on live) | same |
| DOMContentLoaded | **7.0 s** | **1.7–2.0 s** |
| `load` event | 10.7 s | (waits on 4 audio files + polling) |

Warm DCL is almost entirely **parse + synchronous script execution**: 800 KB of HTML and
5.7 MB of JavaScript, every `<script>` tag blocking. Nothing in it is the database.

### 8.3 What the 800 KB HTML was
| Part | Bytes | Note |
|---|---|---|
| `window.items` (193 items × 1.6 KB) | 305 KB | needed by the search/gallery JS |
| Gallery tiles (193 × 1.3 KB) | 253 KB | server-rendered; JS rebuilds them again → **386 tiles in the DOM** |
| Everything else (modals, inputs, layout) | ~240 KB | |

**Of that, ~400 KB was one picture.** The "no photo" placeholder — added earlier *in this
session* when the tile design was reworked — was an inline `data:` SVG (~1 KB) written once in
each tile's `<img src>` and once again in `window.items[].image`: 386 copies of the same drawing.
That is a regression I introduced, and it doubled the page.

### 8.4 What the 5.7 MB of JavaScript was
| File | Size | Used on this page? |
|---|---|---|
| `dataTable/pdfmake.min.js` + `vfs_fonts.js` + `jszip` + DataTables (+ a **second, unminified jQuery**, 398 KB) | **≈2.6 MB** | only for the PDF/Excel export buttons inside the register-details modal |
| `assets/graph/go.js` (GoJS) | **921 KB** | **referenced nowhere in the application** |
| `pos_script_v7.3.js` | 978 KB | yes — unminified |
| `jquery-ui.js` | 539 KB | yes (datepicker, drag) |
| Font Awesome: Pro CSS loaded **twice** (378 KB each) + Free 6 | ~860 KB CSS, ~20 woff2 files | icons |

### 8.5 Images / lazy loading (question 3)
Locally the 193 tiles reference **8 distinct image files, 0.1 MB in total** — nearly every item
has no photo (2 of 193 carry one). So images are not the cost here; the *placeholder markup*
was. On Rockbell the photo count is unknown to me; if the client uploads real photos, tiles
should get `loading="lazy"` (one attribute in the tile markup — hidden category tiles then load
only when shown). Not done now because there is nothing to measure it against locally.

### 8.6 Redundant work on every load (question 2)
- The 2-second connectivity check fetched **the whole public front page (45 KB HTML, a PHP
  render with its own queries)** — 30 times a minute, per till, for as long as the POS is open.
- `getWaiterOrders` in the 7-second loop and `pull_running_order_checker()` at load are
  `async:false`: the UI thread is frozen for the request — on live that is ≥ 0.27 s of freeze
  every 7 s on every till.
- The gallery is rendered by PHP and then rebuilt by JS from `window.items` (386 tiles).
- This session's additions: device-tag issue = one XHR **once per browser, ever**; version
  checks = nothing at load (one small async XHR in the 7 s loop when online-placed orders
  exist); price-tier lock, oversale check, waiter visibility = a handful of ms server-side
  (already inside the 54-query / 72 ms figure). None of them is measurable on this page.

### 8.7 Fixed now (local, uncommitted)
| Fix | Measured effect |
|---|---|
| Placeholder → two static SVG files (`assets/POS/img/ir_placeholder_*.svg`) | HTML **800 KB → 482 KB** (−40%); 2 cached fetches instead of 386 inline copies |
| Remove unused `go.js` | −921 KB JS |
| Connectivity check → `Sale/ping` (2-byte reply, still through PHP/session/DB so "online" keeps its meaning) | −45 KB every 2 s per till; no front-page render |
| **Warm DOMContentLoaded** (same pane, same conditions) | **1.7–2.0 s → 0.86 s** (two runs: 866 / 860 ms) |

### 8.8 Proposed next (measured where possible), biggest first
1. **Defer the DataTables/pdfmake bundle** (load it when the register-details modal opens):
   measured by removing those 9 tags from the snapshot — warm DCL **0.86 s → 0.51–0.55 s**.
   Moderate change (`register_details.js` needs a small on-demand loader).
2. **Make the 7 s poll and load-time checks async** (`getWaiterOrders`,
   `pull_running_order_checker`): removes a ≥ 0.27 s UI freeze every 7 s on live. Small change;
   needs care because the vendor code relies on ordering in two places.
3. **Minify `pos_script_v7.3.js`** (978 KB → ~400 KB; gzip on live already shrinks transfer,
   minification shrinks parse). Build step only; no logic change.
4. **Stop rebuilding the gallery in JS** when the server already rendered it (386 → 193 tiles);
   or the reverse — render nothing server-side and build once from `window.items`. Needs a
   foreground-browser measurement first (post-DCL work could not be timed here).
5. **One Font Awesome, not three** stylesheets (−~600 KB CSS, ~10 fewer font files on first
   visit). Cosmetic audit needed for the Pro-only icons.
6. `loading="lazy"` on tile images once Rockbell has real photos.
7. Distance (§5) still applies: every navigation pays ≥ 0.27 s before any of this.

Expected end state on a warm till after 1–3: DCL well under 0.5 s on this machine; on live,
0.27 s network + ~0.05 s server + browser work — "one touch" becomes realistic, with the
hosting distance the remaining floor.

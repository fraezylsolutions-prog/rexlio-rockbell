# Hotel Operations add-on — owner's guide and go-live checklist

Built 2026-09-16/17 (stages H0–H11; the engineering log is in `HOTEL_ADDON_PLAN.md`). This page is for
the owner and the managers who will run it: what it does, how to switch it on, who sees what, the
daily flow for the front desk and for housekeeping, and the checklist for putting it live.

## 1. What it is — and what it is not

**In:**
- **Rooms** — room types (name, rate per night) and rooms per outlet (number, floor, notes).
- **Front Desk** — a live room board per outlet: who is in which room, since when, whether the room is
  clean; check-in, check-out, out-of-order; a per-room history; a **Stay Log** with filters and CSV export.
- **Housekeeping board** — a full-screen tablet page (same idea as the Kitchen Panel): cleaning tasks
  appear automatically at check-out, staff Start / Finish them, a supervisor Verifies, and the room's
  status follows.

**Not in (deliberately):** reservations / availability by date, guest folios or any room billing, guest
ID capture. The rate on a room type only feeds the recorded value of a stay (section 5) — nothing about
rooms goes through the POS or a receipt. The add-on owns its own tables (`tbl_hotel_*`) and touches nothing in the restaurant side.

## 2. Switching it on and off

Settings › **Modules** › Hotel Operations › *Switch on*. The change is live on the next page load for
everyone — nobody signs out, nothing is deployed. *Switch off* hides everything (menu, pages, the
housekeeping landing); **nothing is deleted** — switching back on restores rooms, stays and tasks as
they were. Every flip is written to the audit log with who and when.

Who may flip it: **Admin**, or any role given the *Modules › update* permission under Settings › Roles.

The screen can also say:
- **Not installed — apply its database migration first**: migration `2026-09-16_01_modules.sql` has not
  been applied (there is no row for the module yet).
- **Tables missing — apply the migration `2026-09-16_02_hotel-schema.sql`** (with the missing table
  names): the module row exists but its tables do not. *Switch on* is withheld until the migration has
  been run, so a half-applied install cannot be switched on and fail on first use.

## 3. Who sees what — permissions

The add-on registers three permission groups. Grant them per role under Settings › Roles (a user picks
them up on their next login, as with every other permission).

| Group | Functions | What they unlock |
|---|---|---|
| **Hotel Rooms** (`hotel_rooms`) | view, add, update, delete | Room Types and Rooms screens |
| **Hotel Front Desk** (`hotel_front_desk`) | view, checkin, checkout, status, **value** | the board and Stay Log; check-in; check-out; out-of-order / back in service; **value** = type a rate at check-in, settle a check-out variance, Edit value on any stay |
| **Hotel Housekeeping** (`hotel_housekeeping`) | view, update_task, assign, verify | the board; Start / Finish own or pool tasks; New task / Assign / Cancel; Verify |
| **Hotel Reports** (`hotel_reports`) | view | every hotel report under Reports (section 6) |

Defaults set by the migrations: **Admin** and **Manager** — everything (incl. *value* and reports).
**Cashier** — front desk view, check-in, check-out; housekeeping view; no *value*, no reports. **Housekeeping** — a new role created for each business:
housekeeping view + update_task, plus the outlet chooser. Adjust freely; the code only ever asks
"does this role hold this function", never "what is the role called".

**Where staff land after login**
- Anyone with POS access lands where they always did (the POS / tables panel). The hotel screens are
  in the sidebar under **Hotel Operations**.
- A user whose role holds *Hotel Housekeeping › view* and **no POS access** (and is not Admin) lands
  straight on the Housekeeping board — a tablet in the housekeeping office needs nothing else. That rule
  is permission-based, so a custom "Room attendant" role works the same as the seeded Housekeeping role.
  With the module off, such users land where they used to.

## 4. Setting up

1. Settings › Modules › **Switch on**.
2. Hotel Operations › **Room Types**: e.g. Standard, Deluxe, Suite, each with its rate per night.
3. Hotel Operations › **Rooms**: one per room, at the outlet it belongs to — number, floor (used to
   group the board), type. New rooms start *vacant* and *clean*.
4. Settings › Roles: confirm who holds what (section 3). Create housekeeping users in the
   **Housekeeping** role and assign them to the outlet(s) they clean.
5. Open the Front Desk board and the Housekeeping board once on the devices that will use them.

## 5. The daily flow

### Front desk (`Hotel Operations › Front Desk`)
Each card is a room: number, type, **occupancy** (Vacant / Occupied / Out of order) and **housekeeping**
state (Clean / Dirty / Cleaning / Inspected) as badges, the guest and "since", and an open-task marker.
The board refreshes itself every 15 s.
- **Check in** — guest name is required; phone, adults / children, expected check-out, a reference
  (receipt or booking number, free text) and notes are optional. The room must be vacant. If it has not
  been cleaned since the last guest the button turns into **Check in anyway** — a warning, not a block.
- **Check out** — closes the stay, marks the room vacant **and dirty**, and puts a cleaning task in the
  housekeeping pool automatically.
- **Out of order / Back in service** — only on a vacant room, with a reason; both are logged.
- **History** — the room's status changes with who and when.
- **Stay Log** — every stay, filterable by outlet, status, room, check-in dates and guest / phone /
  reference; **Export CSV** gives the same filtered rows.

### Housekeeping (`Hotel Operations › Housekeeping Board`, or the automatic landing)
A full-screen page, refreshed every 10 s. Sections, in order:
- **My tasks** — assigned to me. **Start** (the room shows *Cleaning*) → **Finish** (the room shows
  *Clean* for cleaning / turndown tasks; inspection and maintenance leave the room's state alone).
- **Unassigned** — the pool. Starting a pool task claims it.
- **Assigned to others** — supervisors only (assign permission): Finish on their behalf, **Assign** to
  someone or back to the pool, **✕** cancel.
- **Done — awaiting verification** — supervisors only (verify permission): **Verify** marks the room
  *Inspected*; the task leaves the board.
- **New task** (assign permission): room, type (cleaning / turndown / inspection / maintenance), assignee
  or pool, note. A cleaning task on a clean room marks it dirty. A second open task of the same type on
  the same room is refused.

Status vocabulary, for reference: occupancy `vacant → occupied → vacant` (or `out_of_order`);
housekeeping `dirty → in_progress → clean → inspected`; task `pending → in_progress → done → verified`
(or `cancelled`).

### The value of a stay (recorded, not billed)
Every stay carries a **rate per night**, **nights** and an **amount** — the figure the reports call *value
generated*. It is **recorded for reporting only**: nothing reaches sales, registers, Today's Sale or a
receipt, and there is no guest folio.
- **At check-in** the rate comes from the room type; nights = expected check-out − today (minimum 1);
  amount = nights × rate, shown live in the modal. A rate above 0 needs the expected check-out; a room
  type with no rate is complimentary. Only a holder of *Hotel Front Desk › value* (Admin and Manager by
  default) may type a different rate — everyone else sees it read-only.
- **At check-out** the actual nights are recorded. If they differ from the expected nights, a *value*
  holder is asked whether to **keep the recorded value or adjust it** (with a reason). Staff without the
  permission simply check out; the difference shows as a *variance* badge in the Stay Log and the reports
  for a manager to settle later with **Edit value** (front desk card for in-house stays, Stay Log for any
  stay). Every change is kept in the room history with the old and new figure.

### Dashboards
- Main dashboard: while the module is on, the **Rooms checked-in** card (a live count, like Running
  Order) replaces the Transactions card and links to the Front Desk.
- Front Desk: the row above the board is **NOW** — rooms checked-in and their value in house, vacant rooms
  and their potential value at base rate, occupancy %, out-of-order and housekeeping counts, per category
  and in total — plus, kept apart, **Value generated this period** with its own date and clock-time
  filter and presets. The NOW figures never take the filter.

## 6. Reports (`Reports` menu, permission *Hotel Reports › view*)
All share one filter bar: outlet (or all), **View = Day / Week / Month / Year** (the columns or rows of
the period tables), a date range, presets (today / this week / this month / this year), plus report-specific
selects. Every table exports (print / copy / Excel / CSV / PDF). Everything is grouped by **room category**
as it exists in the data — a new category appears by itself.

| Report | Answers |
|---|---|
| **Value Generated** | amount per category per period (Day / Week / Month / Year columns), row / column / grand totals, stays and nights per cell. Counted on the check-in date, in-house included, cancelled excluded. |
| **Occupancy** | per period: rooms, available room-nights, occupied, occupancy %, arrivals, departures, value, RevPAR (value ÷ available); the same per category. |
| **Stays** | one row per stay with its value and a variance badge; sub-totals by period, by category, by staff; filters for category, staff, status. |
| **Housekeeping Productivity** | per attendant / task type / category / period: created, done, verified, cancelled, open, average wait (created → started), work (started → done) and check (done → verified). |
| **Room Turnaround** | per check-out: how long the room stayed dirty and how long until inspected; slowest first; averages per category. |
| **Out of Order** | every out-of-order spell: when, by whom, why, back in service, duration; room-days lost per category. |
| **Room Status History** | the room history as a printable list, by room and by kind (occupancy / housekeeping / value). |

## 7. If something looks wrong

| Symptom | Cause / what to do |
|---|---|
| "That feature is switched off" | The module is off. Settings › Modules. |
| No **Hotel Operations** group in the sidebar | Module off, or the role holds none of the three groups. |
| A housekeeping user lands on the POS instead of the board | Their role also holds POS access — remove it, or accept that they reach the board from the sidebar. |
| "Not installed" / "Tables missing" on the Modules screen | Apply migration 01 / 02 (section 8). |
| Front Desk or a report shows an error page after a code update | The database is behind the code: run `db/migrations/preflight_status.sql` and apply whatever it lists as MISSING. |
| A cashier cannot change the rate at check-in | By design — grant *Hotel Front Desk › value* to their role if they should. |
| Check-in button says "Check in anyway" | The room is not clean / inspected yet — intended; it warns, it does not block. |
| A room stays *Dirty* after cleaning | The task was not marked **Finish** on the board (or was cancelled). Finish it, or create a new cleaning task. |
| Someone else's task shows no buttons for me | Working another person's task needs the assign permission. |

## 8. Go-live checklist (owner)

Order matters: migrations first, code second, switch last (see `DEPLOYMENT.md` › Routine deploy).

- [ ] **Backup** the live database (verified restore point).
- [ ] Apply `db/migrations/2026-09-16_01_modules.sql` — prints `PASS`.
- [ ] Apply `db/migrations/2026-09-16_02_hotel-schema.sql` — prints `PASS` (creates the five tables,
      the permission rows, the Housekeeping role and the default grants; re-runnable).
- [ ] Apply `db/migrations/2026-09-17_01_hotel-stay-value.sql` — prints `PASS` (stay value columns, the
      *value* permission).
- [ ] Apply `db/migrations/2026-09-17_02_hotel-reports-permission.sql` — prints `PASS` (the reports
      permission). Or simply run `preflight_status.sql` first and apply what it lists as MISSING.
- [ ] Deploy the code (the two migrations are additive, so the order "migration then code" leaves live
      working at every step).
- [ ] Sign in as Admin › Settings › Modules: Hotel Operations shows **OFF** with *Switch on* (not
      "Not installed" / "Tables missing").
- [ ] **Switch on**. Confirm the **Hotel Operations** group appears in the sidebar without signing out.
- [ ] Room Types and Rooms entered for each outlet that has rooms.
- [ ] Roles reviewed (section 3); housekeeping users created in the Housekeeping role with their outlets.
- [ ] Dry run on real devices: check a test guest into a room → check out → task appears on the
      housekeeping tablet → Start → Finish → Verify on the front desk / manager screen → room *Inspected*.
      The test stay stays in the Stay Log as history; that is fine.
- [ ] Rollback if needed: Settings › Modules › **Switch off** (no data is lost); the migrations can stay.

## 9. Files, for the technical reader

Controller `application/controllers/Hotel.php` (rooms, front desk, housekeeping, stats, reports), model `Hotel_model.php`, views `views/hotel/*` (`report_*.php`, `_report_filter.php`),
scripts `frequent_changing/js/hotel_front_desk.js` and `hotel_housekeeping.js`, styles
`assets/dist/css/custom/hotel.css`. Module switch: `irModuleEnabled()` / `irRequireModule()` /
`irModuleRegistry()` in `helpers/my_helper.php`, screen `Setting::modules`. Landing rule:
`irHousekeepingLanding()`. Migrations `db/migrations/2026-09-16_01_modules.sql` and
`2026-09-16_02_hotel-schema.sql`.

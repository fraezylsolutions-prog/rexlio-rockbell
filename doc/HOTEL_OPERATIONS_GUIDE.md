# Hotel Operations add-on — owner's guide and go-live checklist

Built 2026-09-16 (stages H0–H4; the engineering log is in `HOTEL_ADDON_PLAN.md`). This page is for
the owner and the managers who will run it: what it does, how to switch it on, who sees what, the
daily flow for the front desk and for housekeeping, and the checklist for putting it live.

## 1. What it is — and what it is not

**In:**
- **Rooms** — room types (name, informational rate) and rooms per outlet (number, floor, notes).
- **Front Desk** — a live room board per outlet: who is in which room, since when, whether the room is
  clean; check-in, check-out, out-of-order; a per-room history; a **Stay Log** with filters and CSV export.
- **Housekeeping board** — a full-screen tablet page (same idea as the Kitchen Panel): cleaning tasks
  appear automatically at check-out, staff Start / Finish them, a supervisor Verifies, and the room's
  status follows.

**Not in (deliberately):** reservations / availability by date, guest folios or any room billing, guest
ID capture. The rate on a room type is for display only — nothing about rooms goes through the POS or a
receipt. The add-on owns its own tables (`tbl_hotel_*`) and touches nothing in the restaurant side.

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
| **Hotel Front Desk** (`hotel_front_desk`) | view, checkin, checkout, status | the board and Stay Log; check-in; check-out; out-of-order / back in service |
| **Hotel Housekeeping** (`hotel_housekeeping`) | view, update_task, assign, verify | the board; Start / Finish own or pool tasks; New task / Assign / Cancel; Verify |

Defaults set by the migration: **Admin** and **Manager** — everything. **Cashier** — front desk view,
check-in, check-out; housekeeping view. **Housekeeping** — a new role created for each business:
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
2. Hotel Operations › **Room Types**: e.g. Standard, Deluxe, Suite (the rate is informational).
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

## 6. If something looks wrong

| Symptom | Cause / what to do |
|---|---|
| "That feature is switched off" | The module is off. Settings › Modules. |
| No **Hotel Operations** group in the sidebar | Module off, or the role holds none of the three groups. |
| A housekeeping user lands on the POS instead of the board | Their role also holds POS access — remove it, or accept that they reach the board from the sidebar. |
| "Not installed" / "Tables missing" on the Modules screen | Apply migration 01 / 02 (section 7). |
| Check-in button says "Check in anyway" | The room is not clean / inspected yet — intended; it warns, it does not block. |
| A room stays *Dirty* after cleaning | The task was not marked **Finish** on the board (or was cancelled). Finish it, or create a new cleaning task. |
| Someone else's task shows no buttons for me | Working another person's task needs the assign permission. |

## 7. Go-live checklist (owner)

Order matters: migrations first, code second, switch last (see `DEPLOYMENT.md` › Routine deploy).

- [ ] **Backup** the live database (verified restore point).
- [ ] Apply `db/migrations/2026-09-16_01_modules.sql` — prints `PASS`.
- [ ] Apply `db/migrations/2026-09-16_02_hotel-schema.sql` — prints `PASS` (creates the five tables,
      the permission rows, the Housekeeping role and the default grants; re-runnable).
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

## 8. Files, for the technical reader

Controller `application/controllers/Hotel.php`, model `Hotel_model.php`, views `views/hotel/*`,
scripts `frequent_changing/js/hotel_front_desk.js` and `hotel_housekeeping.js`, styles
`assets/dist/css/custom/hotel.css`. Module switch: `irModuleEnabled()` / `irRequireModule()` /
`irModuleRegistry()` in `helpers/my_helper.php`, screen `Setting::modules`. Landing rule:
`irHousekeepingLanding()`. Migrations `db/migrations/2026-09-16_01_modules.sql` and
`2026-09-16_02_hotel-schema.sql`.

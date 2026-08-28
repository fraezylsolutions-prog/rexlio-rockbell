# iRestora PLUS Multi Outlet — Project Notes

## Base Project
- **Product:** iRestora PLUS Multi Outlet – Next Gen Restaurant POS
- **Framework:** CodeIgniter (PHP), MySQL 8.x — NOT Laravel
- **Source:** CodeCanyon, by doorsoftxyz
- **Status:** Already purchased, hosted live, and also downloaded locally
- **Dev environment decision:** WampServer (Francis is already familiar with it; Herd's Laravel-specific conveniences don't apply here)

## Confirmed Existing Features (from official documentation)
- **Offline Sync** — built-in, offline-first architecture; billing/orders continue without internet and auto-sync when reconnected.
- **Waiter Panel** — built-in, but it's a **web-based role/login panel**, not a separate native mobile app.
  - Create a user, assign "Waiter" designation, optionally link to an "Order Receiving Cashier."
  - Waiter logs into the same web system and sees only their own + linked waiter's running orders.
  - Gets in-panel notifications when kitchen marks an order done — visible only while actively viewing that browser tab.
- **In-app/in-panel notifications exist for:** kitchen order-done, QR code customer orders, reservations — all browser-only, no push.

## Confirmed Gaps (nothing found in documentation)
- **No push notifications anywhere in the system** — no Firebase/FCM, no browser Web Push, nothing beyond in-panel alerts.
- **No WhatsApp integration** — SMS and payment gateway integration exist, but WhatsApp is not mentioned at all.

## Planned Modifications
1. **WhatsApp End-of-Day Sales Notification**
   - Primary approach: free/unofficial library (e.g. Baileys) — no per-message cost, runs as a small Node.js microservice alongside the CodeIgniter app, communicating via internal HTTP API.
   - Future/parallel option: official Meta WhatsApp Cloud API — paid per message (utility-tier pricing, low cost at low volume), more reliable/ToS-compliant. To be added later, likely behind a provider-agnostic interface so both can coexist.
   - Trigger: scheduled job at closing time aggregates sales data, sends summary to owner's WhatsApp number(s).

2. **Web Push Notifications for Waiter Panel**
   - Since the waiter panel is browser-based (not a native app), Web Push API + service worker is the natural fit — works even when the tab isn't focused, no app store/native build needed.
   - Goal: alert waiters on their device when an order is marked ready, without requiring them to be staring at the panel.

3. **Additional modifications** — to be defined as the project progresses (not yet detailed as of this note).

## Working Notes
- Since this is a vendor-supplied CodeCanyon script (not custom-built), avoid editing core files directly where possible — use CodeIgniter hooks/extending controllers to protect against breaking on future script updates, and to preserve support/update eligibility.
- Always verify against the official documentation before assuming a feature needs to be built from scratch (several requested features turned out to already exist).

# Rockbell — holdback

Rockbell (`rockbell.fraezyl.app`) is **frozen**. It does not have the
notifications module and is not getting it in the first release.

This note exists so that nobody — including a future version of whoever wrote
the code — adds Rockbell to a cron list, a runbook or a deploy script by
reflex, and so the reasons are written down rather than remembered.

## What this means in practice

- **No cron entry for Rockbell.** `doc/NOTIFICATIONS_RUNBOOK.md` gives
  Goloviana's line only. Adding a second line "for symmetry" would run a worker
  against code that is not there.
- **No migrations.** None of the five notification migrations are applied to
  Rockbell's database. They are additive and idempotent, so applying them would
  do no visible harm — and it would still be wrong, because it would put schema
  on a site whose code cannot use it and quietly widen what a rollback has to
  undo.
- **No settings screen.** The Notifications menu entry comes from a permission
  row the N1 migration creates. Without the migration it does not appear, which
  is the correct outcome.
- **Rockbell is not a fallback or a test bed.** If something needs trying,
  it goes on the local scratch database, not on a live venue.

## Why Rockbell is frozen and Goloviana is not

Goloviana is the site this work has been built and tested against. Rockbell is
live, in use, and stable; the module has no feature Rockbell has asked for, and
every switch would ship off anyway — so the only thing a deploy there could
realistically produce is a regression on a working site. The upside is zero
until somebody at Rockbell wants alerts.

Both sites share one cPanel account (`citytjgo`) and one PHP installation, so
"it is the same server" is not a reason to treat them as one deployment. They
are separate docroots, separate databases and separate repository clones.

## What has to be true before Rockbell gets the module

1. Goloviana has been running it in production long enough to have seen a real
   end-of-day, a real escalation and a real acknowledge — not just a test send.
2. Somebody at Rockbell actually wants it, and has said which events and which
   recipients.
3. A verified database backup, as for any migration.
4. The same deployment order as Goloviana's first release, from the release
   plan: migrations, then code, then cron, then switch on with test mode ON.

## The one thing that is deliberately waiting for Rockbell

The **cross-site heartbeat** (Option 1). The heartbeat shipped as an external
ping (Option 2) because it has to be able to notice a dead worker from outside
the server. The cheaper variant — Rockbell's cron checking Goloviana's
`last-run.txt` and vice versa, each alerting through its own working
notification path — needs both sites to have the module. It is worth doing when
that is true, as a second layer: it costs nothing, involves no third party, and
catches the common failure (one site's cron entry removed or its PHP path
changed by an upgrade). It does not replace the external ping, because both
sites are on one account and an account-level outage would take both watchers
down with both workers.

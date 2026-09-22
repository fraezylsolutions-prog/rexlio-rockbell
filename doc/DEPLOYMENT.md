# Rexlio POS — deployment

## Environments

| | Where | Role |
|---|---|---|
| **Local** | WampServer, `C:\wamp64\www\rexlio` | Primary build-and-test. All work starts and is proven here. |
| **Live** | Rockbell subdomain (cPanel) | Final acceptance testing by the dev team, on changes already working locally. |

Live is the step *after* local, not a substitute for it. Nothing reaches live
that has not already been tested locally.

**Every command that touches live is run by the project owner, not by Claude.**
Claude prepares commits, migration scripts and the exact commands; the "go" on
live is a human decision, every time.

---

## STANDING PROCEDURE — standing up a NEW host

**Never stand up a new host by copying files and importing a database dump.
Always run the vendor's own `/install` process first.**

This is the single most expensive lesson of the project so far, and it applies
to every future client deployment, not just Rockbell.

### What went wrong (Rockbell, 2026-09-08)

The first attempt at `rockbell.fraezyl.app` was a manual deployment: copy the
application files up, import a database dump, edit `database.php`. The site
returned a blank page / 501. Two theories were chased and both were wrong:

- **`base_url` fallback** — plausible because `config.php:27` derives `base_url`
  from `$_SERVER['HTTP_HOST']`, so a host-header difference looked like a
  candidate. It was not.
- **A missing or misplaced `index.php`** — also not the cause.

Both were red herrings, and the time spent on them is the reason this section
exists.

### The actual cause

This product is not a plain "copy the files and point it at a database"
application. **The vendor's installer generates state that a raw file copy
cannot produce**, and without it the application does not boot — failing in a
way that looks like a server or routing fault rather than a missing
installation, which is precisely what makes it so expensive to diagnose.

**Established from the deployment itself:** the manual copy failed; running
`/install` fixed it. That is the operative fact and the reason for this
procedure.

**CONFIRMED 2026-09-22 (supersedes the caveat below).** The generated state is
`assets/blueimp/REST_API*.json`. `REST_API_I.json` holds `username`,
`purchase_code` and `installation_url`, all ROT13-encoded; the installation_url
is the address of whichever machine ran `/install`. The folder is spelled
**blueimp**, not bluezimp, which is why the earlier code search found nothing.
Deploying this machine's copies set live's installation_url to
`http://localhost/rexlio/`, and the application then refuses to run until
`/install` rewrites them - exactly the "every manual update needs /install"
behaviour seen on this project. The three JSON files are now git-ignored and
excluded in `.cpanel.yml`, so a deploy never touches them and no re-install is
needed. `assets/blueimp/index.php` and `index.html` (the 403 stubs) stay tracked.

**Originally reported, before it was pinned down:** the generated state was identified as files under
`assets/bluezimp/`. Two caveats on that specific, so nobody over-trusts it
later — `assets/bluezimp/` does not exist in the local working tree and is not
tracked in git (consistent with it being generated rather than shipped), but a
search of the application code found **no reference to `bluezimp` anywhere** —
not in controllers, models, helpers, libraries, config, `system/`,
`third_party/` or `install/`. So the directory name is plausible but
unconfirmed; the licence surface may sit elsewhere, or be constructed
dynamically. **Do not build tooling that depends on that path** without
verifying it first on a host that has actually been installed.

Either way the conclusion is unchanged: the installer produces something a copy
does not, so run the installer.

### The procedure

1. Upload the application files to the new host.
2. **Run `/install` and complete the vendor's installation flow.** Let it create
   its own database and activation state. Do not skip this because you already
   have a database.
3. Only then import the data you actually want to carry over, and re-point
   `application/config/database.php`.
4. Block or remove `install/` once the installation is complete — it is in the
   repository because it is needed for exactly this step, but it must not stay
   reachable on a live host.

### Why a database export still works

Exporting the local `rexlio` database and importing it after installation is
fine, and is the normal way to carry a prepared menu and settings to a new
host. What is *not* fine is treating that import as a substitute for running
the installer. The database is data; the installer produces state that does not
live in the database at all.

Note also that a local export already contains every migration applied locally,
so a freshly-installed host seeded this way needs no separate migration run.

### What a database export never carries

`images/` and `uploads/` are excluded from both the repository and any SQL
export. Menu items arrive with their names, prices and categories intact but
**without photographs**, which have to be re-uploaded on the new host.

## What the repository contains

Tracked: application code, `system/`, `vendor/` (there is no `composer.json`,
so vendor is committed code — omitting it breaks the site), `.htaccess`,
`config.php`, and `database.php.example`.

Not tracked, and why:

| Excluded | Reason |
|---|---|
| `application/config/database.php` | Hardcoded credentials, different per server. Deploying it would point live at the local database. |
| `pw.php`, `application/config/testing/` | Local test harness pinned to `ENVIRONMENT='testing'` and used with a seeded session. **On a public host this is an authentication bypass.** |
| `images/`, `uploads/`, `asset/excel/` | User-uploaded content. Belongs to whichever server it was uploaded on; versioning it causes binary churn and lets a deploy overwrite live uploads. |
| `application/logs/`, `application/cache/`, `_temp/` | Generated at runtime. |
| `iRestora PLUS v7.5 Nulled/` | 99 MB vendor archive, not part of the deployed app. |
| `rexlio_backups/`, `doc/old/`, `doc/*.zip` | Backups and scratch material. |

`config.php` **is** tracked and is safe to deploy: `base_url` is derived from
`$_SERVER['HTTP_HOST']` (config.php:27), so it is portable between environments.

### Consequence to be aware of

Because `images/` and `uploads/` are excluded, **food photos and category
images uploaded locally will not appear on live**, and vice versa. They have to
be uploaded on whichever environment needs them. This is the right trade — the
alternative is a deploy silently overwriting images the client uploaded on live
— but it does mean live will look image-sparse until photos are added there.

## One-time setup on live (owner runs)

Two options. Both end with the same deploy step.

### Option A — private GitHub repo, cPanel pulls from it *(recommended)*

Gives off-machine history and lets the dev team collaborate.

    # local, once
    git remote add origin git@github.com:<org>/rexlio.git
    git push -u origin master

Then in cPanel → **Git™ Version Control** → *Create*:
- Clone URL: the GitHub SSH URL
- Repository path: `/home/<cpaneluser>/repositories/rexlio`  ← **outside the docroot**

### Option B — cPanel host is the remote, push straight to it

No third party involved.

In cPanel → **Git™ Version Control** → *Create* (no clone URL), path
`/home/<cpaneluser>/repositories/rexlio`. Then locally:

    git remote add live ssh://<cpaneluser>@<host>/home/<cpaneluser>/repositories/rexlio
    git push live master

### Why the repo path is outside the docroot

If the repository is cloned directly into the web root, `.git/` becomes
web-accessible and the entire source and history can be downloaded. Keeping the
repo outside the docroot and copying files in (below) avoids that completely.

### After the first deploy, once

    # on live, one time only - this file is not in the repo
    cp application/config/database.php.example application/config/database.php
    # then edit it with the live database credentials

Also confirm the `install/` directory is not reachable on live — it is in the
repo (needed for a fresh install elsewhere) but should be blocked or removed on
a live host.

## Routine deploy

**1 — Local: commit the change**

    git add -A
    git commit -m "<what changed and why>"
    git push origin master        # or: git push live master

**2 — If the change includes a schema change: migration FIRST**

See `db/migrations/README.md`. Back up, apply the migration, confirm it prints
`PASS`, and only then continue. If it does not print PASS, stop here — live is
still consistent with the old code.

    # back up live first (owner runs, on live)
    mysqldump -u <user> -p --single-transaction --routines --triggers \
      <livedb> > ~/backups/rexlio_$(date +%Y%m%d_%H%M%S).sql

    # confirm the dump is real before trusting it
    ls -lh ~/backups/rexlio_*.sql | tail -1

    # apply the migration - run from the REPO clone, not the docroot.
    # db/ is excluded from the deploy on purpose: migration scripts have no
    # business being reachable from the web.
    cd /home/<cpaneluser>/repositories/rexlio
    git pull                       # so the migration file is actually present
    mysql -u <user> -p <livedb> < db/migrations/<file>.sql

**3 — Deploy the code** (cPanel → Git Version Control → *Update from Remote*,
then *Deploy HEAD Commit*; or over SSH):

    cd /home/<cpaneluser>/repositories/rexlio
    git pull
    # deployment tasks in .cpanel.yml copy files into the docroot

**4 — Smoke-test live**

Sale screen loads · a category filters · an item adds to cart · dashboard
renders with charts · one real order placed end to end.

Bump the asset version in `main_screen.php` / `userHome.php` when CSS or JS
changed, or browsers will keep serving the cached copy and the deploy will look
like it did nothing.

## Rollback

Code only:

    git revert <sha> && git push        # then redeploy
    # or, on live: git checkout <previous-sha> && redeploy

Schema: **there is no code-side rollback.** MySQL DDL is not transactional.
Restore the backup taken in step 2. This is exactly why the backup is not
optional and why migrations go first — a failed migration leaves live untouched
and running the old code.

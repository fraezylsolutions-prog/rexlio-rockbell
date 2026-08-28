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

<?php
/**
 * CLI entry point for Rexlio.
 *
 * WHY THIS FILE EXISTS
 * config.php builds base_url from $_SERVER["HTTP_HOST"], which does not exist
 * when PHP runs from a shell or from cron. Every CLI run through index.php
 * therefore logs "Undefined array key HTTP_HOST" TWICE - config.php is loaded
 * twice - which at once a minute is 2,880 lines a day burying anything that
 * actually matters in the log. Measured: index.php 2 per run, cli.php 0.
 *
 * It is logged rather than printed because index.php sets display_errors to 0
 * under ENVIRONMENT production while leaving E_WARNING in error_reporting, so
 * running the worker by hand looks clean and the log fills up regardless.
 *
 * The fix belongs here rather than in config.php: config.php is a vendor file
 * that an iRestora update will overwrite, and a patch that gets silently
 * reverted on upgrade is worse than no patch. This file is ours.
 *
 * HTTP_HOST is only used to build base_url, which a CLI job never follows, so
 * the value just has to be present and harmless. It is read from
 * RTD_CLI_HOST when set, so a site whose emails need real links in them can
 * put its own domain there without touching code.
 *
 * USE IT INSTEAD OF index.php for every CLI call, including cron:
 *     php cli.php notification_worker run
 *     php cli.php notification_worker status
 *
 * index.php still works and still warns; nothing else changes.
 */

if (PHP_SAPI !== 'cli') {
    /* This is not a second front door. Serving it over HTTP would run CLI-only
       controllers from a browser with no session behind them. */
    http_response_code(403);
    exit('CLI only');
}

if (empty($_SERVER['HTTP_HOST'])) {
    $host = getenv('RTD_CLI_HOST');
    $_SERVER['HTTP_HOST'] = ($host !== FALSE && $host !== '') ? $host : 'localhost';
}

/* config.php builds base_url as http:// unless $_SERVER['HTTPS'] is set, and
   nothing sets it under CLI.
   That was invisible until N5 began generating LINKS from the worker: an
   escalation raised by cron carried http://localhost/ack/... - a link that goes
   nowhere from a manager's phone. The FIRST alert was always correct, because
   it is raised inside a real web request that knows its own host and scheme;
   only worker-generated messages were wrong, which is the kind of difference
   nobody notices until the night it matters.
   A named host means a real site, and real sites are https. RTD_CLI_HTTPS=0
   forces plain http for the rare install that needs it. */
if (empty($_SERVER['HTTPS'])) {
    $https = getenv('RTD_CLI_HTTPS');
    $named = ($_SERVER['HTTP_HOST'] !== 'localhost'
              && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== 0);
    if ($https === '0' || $https === 'off') {
        /* deliberately left unset: config.php tests isset(), not the value */
    } elseif (($https !== FALSE && $https !== '') || $named) {
        $_SERVER['HTTPS'] = 'on';
    }
}

/* config.php also reads SCRIPT_NAME to work out the sub-folder. Under CLI it is
   this file's path, which would put "cli.php" in base_url, so it is pinned to
   the same value a web request would give. */
$_SERVER['SCRIPT_NAME'] = '/index.php';

/* SERVER_NAME and REQUEST_URI: the vendor's activation check needs them.
 *
 * iRestora's CI_Model constructor (system/core/Model.php) checks the install
 * against $_SERVER on EVERY model load and die()s on a mismatch, rendering an
 * obfuscated view that does not exist - so the symptom is the baffling
 *   "Unable to load the requested file: supplierPayment/supplierPayments/bak0719.php"
 * and the process stops dead. Under cron there is no request, so those vars are
 * absent and the check cannot evaluate at all: EVERY model load from CLI died,
 * whatever the code was doing. Nothing to do with stock or notifications.
 *
 * This supplies the same two values Apache would, so the check can do its job.
 * It is NOT a way round it: given a host this install is not licensed for, it
 * still refuses - verified with SERVER_NAME=notourdomain.example.com.
 *
 * RTD_CLI_PATH is the path the application is installed under, because the
 * check compares HTTP_HOST . explode('/', REQUEST_URI)[1] against the recorded
 * installation URL:
 *   installed at a docroot (Rockbell, Goloviana)  ->  '/'          (the default)
 *   installed in a folder, e.g. localhost/rexlio  ->  '/rexlio/index.php'
 * Default '/' is right for both live sites. Get it wrong and the only symptom
 * is that cron silently does nothing, so notification_worker status reports it.
 */
if (empty($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
}
if (empty($_SERVER['REQUEST_URI'])) {
    $path = getenv('RTD_CLI_PATH');
    if ($path === FALSE || $path === '') {
        $path = '/';
    } elseif ($path[0] !== '/') {
        /* A leading slash is optional, so RTD_CLI_PATH=rexlio/index.php works
           as well as /rexlio/index.php. Not cosmetic: Git Bash on Windows
           rewrites a leading '/' in an argument or variable into a Windows path
           (/rexlio/index.php became C:/Program Files/Git/rexlio/index.php),
           and the workaround for that - MSYS_NO_PATHCONV - then breaks every
           other POSIX path handed to a Windows PHP. Accepting both spellings
           avoids the whole argument. */
        $path = '/' . $path;
    }
    $_SERVER['REQUEST_URI'] = $path;
}

chdir(__DIR__);
require __DIR__ . '/index.php';

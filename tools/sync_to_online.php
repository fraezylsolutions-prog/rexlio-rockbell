<?php
/**
 * Rexlio hybrid sync - LOCAL side. Two clicks: double-click "Sync to Online.bat", press Y.
 *
 * What it does, in order, stopping loudly at the first failure:
 *   1. checks both sides run the same Rexlio version (local git commit vs the online build.txt)
 *   2. dumps the local database to a dated file (kept, newest 10)
 *   3. counts the rows of every table locally (the online side must end up identical)
 *   4. copies the dump + counts to the online host (scp) and runs tools/remote/rexlio_sync_remote.sh there,
 *      which: backs up the ONLINE database first, restores the dump, verifies the counts, and puts the
 *      backup straight back if anything fails
 *   5. prints DONE, ROLLED BACK or FAILED with the reason, and writes a log file
 *
 * Nothing on the local database is ever changed. The online copy's own data is replaced on purpose
 * (it is for remote viewing); tbl_sessions is left alone so people viewing it stay signed in.
 *
 * Usage:  php sync_to_online.php [--yes] [--ignore-version] [--test]
 *   --yes             skip the confirmation (for a scheduled task)
 *   --ignore-version  proceed even if the two versions differ (prints a warning; only when told to by support)
 *   --test            use the 'local_test' settings instead of ssh (rexlio_scratch stands in for the online site)
 */
error_reporting(E_ALL); ini_set('display_errors', '1'); date_default_timezone_set('Africa/Lagos');
define('BASEPATH', TRUE);   // lets application/config/database.php be included
$ROOT = realpath(__DIR__ . '/..');
$args = array_slice($argv, 1);
$opt = function ($name) use ($args) { return in_array($name, $args); };

/* ---------- output helpers ---------- */
$LOGDIR = __DIR__ . '/sync_logs'; if (!is_dir($LOGDIR)) { @mkdir($LOGDIR, 0755, TRUE); }
$LOG = $LOGDIR . '/sync_' . date('Ymd_His') . '.log';
function say($s = '') { echo $s, "\n"; file_put_contents($GLOBALS['LOG'], $s . "\n", FILE_APPEND); }
function ok($s) { say('   [OK]      ' . $s); }
function info($s) { say('            ' . $s); }
function stop($why, $hint = '') {
    say(''); say('*************************************************************');
    say('*  FAILED  - ' . $why);
    if ($hint !== '') { say('*  ' . $hint); }
    say('*  Nothing on the local system was changed.');
    say('*  Log: ' . $GLOBALS['LOG']);
    say('*************************************************************');
    exit(1);
}
function run($cmd, &$out = NULL, $extraEnv = array(), $stdoutFile = NULL) {
    /* $stdoutFile: write the program's output straight to that file (no shell redirection needed) */
    $d = array(0 => array('pipe', 'r'), 1 => $stdoutFile ? array('file', $stdoutFile, 'w') : array('pipe', 'w'), 2 => array('pipe', 'w'));
    $env = array(); foreach (getenv() as $k => $v) { if (is_string($v)) { $env[$k] = $v; } } foreach ($extraEnv as $k => $v) { $env[$k] = (string) $v; }
    $p = proc_open($cmd, $d, $pipes, NULL, $env);
    if (!is_resource($p)) { $out = 'could not start: ' . $cmd; return 127; }
    fclose($pipes[0]);
    $so = $stdoutFile ? '' : stream_get_contents($pipes[1]); $se = stream_get_contents($pipes[2]);
    if (!$stdoutFile) { fclose($pipes[1]); } fclose($pipes[2]);
    $code = proc_close($p);
    $out = trim($so . ($se !== '' ? "\n" . $se : ''));
    return $code;
}
function q($s) { return '"' . str_replace('"', '\"', $s) . '"'; }

say('Rexlio - Sync to Online   ' . date('Y-m-d H:i:s'));
say('==========================================================');

/* ---------- settings ---------- */
$cfgFile = __DIR__ . '/sync_config.php';
if (!is_file($cfgFile)) { stop('settings file missing: tools\\sync_config.php', 'Copy sync_config.example.php to sync_config.php and fill it in (Technical Guide > Two-Click Sync).'); }
$cfg = include $cfgFile;
$test = $opt('--test') || (isset($cfg['transport']) && $cfg['transport'] === 'local');
$keep = isset($cfg['keep_backups']) ? (int) $cfg['keep_backups'] : 10;
$skip = array_values(array_unique(array_merge(array('tbl_sessions'), isset($cfg['skip_tables']) ? $cfg['skip_tables'] : array())));

/* local database from the app's own config */
$dbFile = $ROOT . '/application/config/database.php';
if (!is_file($dbFile)) { stop('cannot find application/config/database.php'); }
$active_group = 'default'; $db = array(); include $dbFile;
$L = isset($db['default']) ? $db['default'] : NULL;
if (!$L || empty($L['database'])) { stop('local database settings could not be read from database.php'); }
$Lhost = isset($L['hostname']) && $L['hostname'] !== '' ? $L['hostname'] : 'localhost';

/* mysql binaries */
$bin = isset($cfg['mysql_bin']) && $cfg['mysql_bin'] !== '' ? rtrim($cfg['mysql_bin'], '/\\') : '';
if ($bin === '') { $dirs = glob('C:/wamp64/bin/mysql/mysql*'); natsort($dirs); $bin = $dirs ? end($dirs) . '/bin' : ''; }
$mysqldump = $bin . '/mysqldump.exe'; $mysql = $bin . '/mysql.exe';
if (!is_file($mysqldump) || !is_file($mysql)) { stop('mysqldump.exe / mysql.exe not found under ' . $bin, 'Set mysql_bin in sync_config.php to WampServer\'s MySQL bin folder.'); }

$localDir = rtrim(isset($cfg['local_dir']) ? $cfg['local_dir'] : 'C:/rexlio_sync', '/\\');
if (!is_dir($localDir) && !@mkdir($localDir, 0755, TRUE)) { stop('cannot create ' . $localDir); }

/* ---------- what is about to happen ---------- */
$onlineUrl = rtrim($cfg['online_url'], '/') . '/';
if ($test) {
    $T = $cfg['local_test'];
    info('TEST MODE: the "online" side is the local database ' . $T['db'] . ' (no SSH).');
    $target = $T['db'] . ' (local test)';
} else {
    $S = $cfg['ssh'];
    foreach (array('host', 'user', 'key', 'remote_dir', 'online_db') as $k) { if (empty($S[$k])) { stop("ssh.$k is not set in sync_config.php"); } }
    if (!is_file($S['key'])) { stop('SSH key file not found: ' . $S['key'], 'Download it from cPanel > SSH Access > Manage SSH Keys (see the Technical Guide).'); }
    $target = $S['online_db'] . ' on ' . $S['host'];
}
say('');
say('  This will copy the LOCAL database   ' . $L['database']);
say('  over the ONLINE copy                ' . $target);
say('  The online copy is backed up first and put back if anything fails.');
say('  Tables left untouched online: ' . implode(', ', $skip));
say('');
if (!$opt('--yes')) {
    echo '  Continue? Type Y and press Enter (anything else cancels): ';
    $ans = trim((string) fgets(STDIN));
    if (strtoupper($ans) !== 'Y') { say('  Cancelled. Nothing was done.'); exit(0); }
}
say('');

/* ---------- 1. same version on both sides ---------- */
say('1. Checking versions');
$localBuild = '';
$head = @file_get_contents($ROOT . '/.git/HEAD');
if ($head !== FALSE) {
    $head = trim($head);
    if (preg_match('/^ref: (.+)$/', $head, $m)) {
        $ref = @file_get_contents($ROOT . '/.git/' . trim($m[1]));
        if ($ref !== FALSE) { $localBuild = substr(trim($ref), 0, 8); }
        elseif (is_file($ROOT . '/.git/packed-refs') && preg_match('#^([0-9a-f]{40}) ' . preg_quote(trim($m[1]), '#') . '$#m', file_get_contents($ROOT . '/.git/packed-refs'), $pm)) { $localBuild = substr($pm[1], 0, 8); }
    } else { $localBuild = substr($head, 0, 8); }
}
if ($localBuild === '') { stop('cannot read the local version (.git/HEAD)', 'The local folder must be the git checkout of Rexlio.'); }
$ctx = stream_context_create(array('http' => array('timeout' => 20, 'ignore_errors' => TRUE)));
$remoteBuild = @file_get_contents($onlineUrl . 'build.txt', FALSE, $ctx);
$remoteBuild = $remoteBuild === FALSE ? '' : trim($remoteBuild);
if ($remoteBuild === '' || stripos($remoteBuild, '<') !== FALSE) {
    if (!$opt('--ignore-version')) { stop('the online site has no build.txt at ' . $onlineUrl . 'build.txt', 'It has not been deployed through the pipeline yet. Deploy first (Technical Guide > Deployment Pipeline). Support may tell you to run with --ignore-version once.'); }
    say('   [WARNING] online version unknown - continuing because --ignore-version was given');
} else {
    $rb = preg_split('/\s+/', $remoteBuild); $rb = $rb[0];
    $same = strpos($rb, substr($localBuild, 0, 7)) === 0 || strpos($localBuild, substr($rb, 0, 7)) === 0;
    if (!$same && !$opt('--ignore-version')) { stop("version mismatch - online runs $rb, local is $localBuild", 'Deploy the same version to both sides first (Technical Guide > Deployment Pipeline).'); }
    if (!$same) { say("   [WARNING] versions differ (online $rb, local $localBuild) - continuing because --ignore-version was given"); }
    else { ok("both sides run $localBuild"); }
}

/* ---------- 2. dump the local database ---------- */
say('2. Backing up the local database');
$stamp = date('Ymd_His');
$dump = $localDir . '/local_' . $L['database'] . '_' . $stamp . '.sql';
$ignore = ''; foreach ($skip as $t) { $ignore .= ' --ignore-table=' . $L['database'] . '.' . $t; }
/* the password goes to mysqldump through its environment, never on the command line or on disk */
$cmd = q($mysqldump) . ' --user=' . $L['username'] . ' --host=' . $Lhost . ' --single-transaction --skip-lock-tables --add-drop-table --routines --triggers --default-character-set=utf8mb4' . $ignore . ' ' . $L['database'];
$code = run($cmd, $out, array('MYSQL_PWD' => (string) $L['password']), $dump);
if ($code !== 0 || !is_file($dump) || filesize($dump) < 1000) { stop('local backup failed: ' . $out); }
$tail = file_get_contents($dump, FALSE, NULL, max(0, filesize($dump) - 200));
if (strpos($tail, 'Dump completed') === FALSE) { stop('local backup file is incomplete: ' . $dump); }
ok(basename($dump) . '  (' . number_format(filesize($dump) / 1024) . ' KB)');
$olds = glob($localDir . '/local_*.sql'); sort($olds); while (count($olds) > $keep) { @unlink(array_shift($olds)); }

/* ---------- 3. row counts ---------- */
say('3. Counting rows in every table');
$m = @new mysqli($Lhost, $L['username'], $L['password'], $L['database']);
if ($m->connect_error) { stop('cannot connect to the local database: ' . $m->connect_error); }
$counts = ''; $n = 0;
$r = $m->query("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");
while ($row = $r->fetch_assoc()) { if (in_array($row['t'], $skip)) { continue; } $c = $m->query('SELECT COUNT(*) AS c FROM `' . $row['t'] . '`')->fetch_assoc(); $counts .= $row['t'] . ' ' . $c['c'] . "\n"; $n++; }
$m->close();
$countsFile = $localDir . '/expected_counts_' . $stamp . '.txt';
file_put_contents($countsFile, $counts);
ok("$n tables counted");

/* ---------- 4. hand over to the online side ---------- */
$script = __DIR__ . '/remote/rexlio_sync_remote.sh';
if (!is_file($script)) { stop('missing tools/remote/rexlio_sync_remote.sh'); }
$scriptBody = str_replace("\r\n", "\n", file_get_contents($script));
if ($test) {
    say('4. Running the online steps in TEST MODE against ' . $T['db']);
    $dir = rtrim($T['dir'], '/\\'); if (!is_dir($dir) && !@mkdir($dir, 0755, TRUE)) { stop('cannot create ' . $dir); }
    if (!is_file($dir . '/online.cnf')) { stop("test mode needs $dir/online.cnf ([client] user/password for the test database)"); }
    copy($dump, $dir . '/upload.sql'); copy($countsFile, $dir . '/expected_counts.txt');
    file_put_contents($dir . '/rexlio_sync_remote.sh', $scriptBody);
    $tenv = array('MYSQL_BIN' => $T['mysql_bin'] . '/mysql.exe', 'MYSQLDUMP_BIN' => $T['mysql_bin'] . '/mysqldump.exe');
    $code = run(q($T['bash']) . ' ' . q($dir . '/rexlio_sync_remote.sh') . ' ' . q($dir) . ' ' . $T['db'] . ' ' . q($dir . '/upload.sql') . ' ' . q($dir . '/expected_counts.txt') . ' ' . $keep, $out, $tenv);
} else {
    say('4. Sending to ' . $S['host'] . ' and running the online steps');
    $sshBase = 'ssh -i ' . q($S['key']) . ' -p ' . (int) $S['port'] . ' -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=25 ' . $S['user'] . '@' . $S['host'];
    $scpBase = 'scp -i ' . q($S['key']) . ' -P ' . (int) $S['port'] . ' -o BatchMode=yes -o StrictHostKeyChecking=accept-new -q';
    $rd = $S['remote_dir'];
    $code = run($sshBase . ' ' . q("mkdir -p $rd && chmod 700 $rd && test -r $rd/online.cnf && echo READY || echo NOCNF"), $out);
    if ($code !== 0) { stop('cannot reach the online host over SSH: ' . $out, 'Check ssh.host / user / key in sync_config.php, and that SSH Access is enabled in cPanel.'); }
    if (trim($out) !== 'READY') { stop("$rd/online.cnf is missing on the online host", 'Create it once in cPanel > File Manager (Technical Guide > Two-Click Sync, one-time setup).'); }
    ok('connected');
    $tmpScript = $localDir . '/rexlio_sync_remote.sh'; file_put_contents($tmpScript, $scriptBody);
    $code = run($scpBase . ' ' . q($dump) . ' ' . q($countsFile) . ' ' . q($tmpScript) . ' ' . $S['user'] . '@' . $S['host'] . ':' . $rd . '/', $out);
    if ($code !== 0) { stop('upload to the online host failed: ' . $out); }
    ok('dump uploaded (' . number_format(filesize($dump) / 1024) . ' KB)');
    $code = run($sshBase . ' ' . q("bash $rd/rexlio_sync_remote.sh $rd " . $S['online_db'] . " $rd/" . basename($dump) . " $rd/" . basename($countsFile) . " $keep"), $out);
}
@unlink($countsFile);

/* ---------- 5. read the online side's report ---------- */
$result = ''; $detail = '';
foreach (preg_split('/\r?\n/', $out) as $line) {
    if (preg_match('/^STEP (\w+) (OK|FAILED|MISMATCH)\s*(.*)$/', $line, $mm)) {
        if ($mm[2] === 'OK') { ok('online: ' . $mm[1] . ' ' . $mm[3]); } else { say('   [' . $mm[2] . '] online: ' . $mm[1] . ' ' . $mm[3]); }
    } elseif (preg_match('/^RESULT (\w+)\s*(.*)$/', $line, $mm)) { $result = $mm[1]; $detail = $mm[2]; }
    elseif (trim($line) !== '') { info($line); }
}
say('');
if ($result === 'DONE') {
    say('==========================================================');
    say('  DONE - the online copy now matches the local database.');
    say('  Online backup kept on the host: ' . $detail);
    say('  Log: ' . $LOG);
    say('==========================================================');
    exit(0);
}
if ($result === 'ROLLED_BACK') { stop('the online update failed and was ROLLED BACK - the online copy is exactly as it was before (' . $detail . ')', 'Send the log to support. Nothing is lost on either side.'); }
if ($result === 'FAILED') { stop('the online side reported: ' . $detail, ($code === 5 ? 'IMPORTANT: the rollback also failed - the online copy may be inconsistent. Restore the named backup by hand (Technical Guide > Backup & Recovery) and call support.' : 'Send the log to support.')); }
stop('no result from the online side (exit code ' . $code . ')', 'Send the log to support.');

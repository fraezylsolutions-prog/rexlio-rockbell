<?php
/* Rexlio hybrid sync - settings for tools/sync_to_online.php.
   Copy this file to sync_config.php (same folder) and fill it in. sync_config.php is NOT in git.
   See the Technical Guide, section "Two-Click Sync to Online", for where each value comes from. */
return array(

    /* The online copy's web address (used to read build.txt and check both sides run the same version). */
    'online_url'   => 'https://rockbell.fraezyl.app/',

    /* How to reach the online host. 'ssh' for the real site; 'local' is the test mode used on rexlio_scratch. */
    'transport'    => 'ssh',

    'ssh' => array(
        'host'      => 'rockbell.fraezyl.app',      // the cPanel host name (or its IP)
        'port'      => 22,                          // cPanel usually 22; some hosts use 2222
        'user'      => 'rockbell1',                 // the cPanel user name (cPanel home > General Information > Current User)
        'key'       => 'C:/rexlio_sync/id_rsa_pem', // the PRIVATE key file made on this PC in PEM format (never share it)
        'remote_dir'=> '~/rexlio_sync',             // a folder in the cPanel home, OUTSIDE the website folder
        'online_db' => 'rockbell1_rexlio',          // the online database name (cPanel > MySQL Databases)
    ),

    /* Test mode only: run the same steps against a second LOCAL database, no SSH. */
    'local_test' => array(
        'dir'       => 'C:/rexlio_sync_test',
        'db'        => 'rexlio_scratch',
        'bash'      => 'C:/Program Files/Git/bin/bash.exe',
        'mysql_bin' => 'C:/wamp64/bin/mysql/mysql8.4.7/bin',
    ),

    /* Where the local dumps are kept (one per sync, the newest 10). */
    'local_dir'    => 'C:/rexlio_sync',
    'keep_backups' => 10,

    /* Tables that are never copied to the online site. tbl_sessions is always excluded (remote viewers stay signed in). */
    'skip_tables'  => array(),

    /* Path to WampServer's mysqldump / mysql (the tool finds the newest version under C:/wamp64/bin/mysql if left empty). */
    'mysql_bin'    => '',
);

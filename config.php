<?php

// =====================================================================================================================
// configure the database that should be exported and the path for the export
// !!! these settings are being overridden if you call mysqldump.php with parameters !!!
define('CONFIG_HOSTNAME',       'localhost');                   // The server on which MySQL is running on
define('CONFIG_USERNAME',       'root');                        // The username
define('CONFIG_PASSWORD',       '');                            // The password for the user
define('CONFIG_DATABASE',       'test');                        // The MySQL DB you want to export
define('CONFIG_PORT',           '3306');                        // MySQL DB Port (default is 3306)
define('CONFIG_EXPORT_FILE',    './exports/{FILENAME}.sql');    // use {FILENAME} to let the script generate a filename
define('CONFIG_LOG_FILE',       './logs/{FILENAME}.log');       // use {FILENAME} to let the script generate a filename

// =====================================================================================================================
// configure the behaviour of the export
define('CONFIG_BUCKET_SIZE',        100);       // How many Rows should at maximum be included in one INSERT Statement?
define('CONFIG_MAX_QUERY_LENGTH',   10000);     // What is the maximum character length of an INSERT Statement?

// =====================================================================================================================
// the mysql commands that are being inserted at the beginning/end of the script and before/after the insert statements
// !!! you probably shouldn't change these settings unless you know what you're doing !!!

define('CONFIG_COMMAND_START', [
    '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;',
    '/*!40101 SET NAMES utf8 */;',
    '/*!50503 SET NAMES utf8mb4 */;',
    '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;',
    '/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\' */;'
]);

define('CONFIG_COMMAND_BEFORE_INSERTS', [
    '/*!40000 ALTER TABLE `{TABLE}` DISABLE KEYS */;',
]);

define('CONFIG_COMMAND_AFTER_INSERTS', [
    '/*!40000 ALTER TABLE `{TABLE}` ENABLE KEYS */;',
]);

define('CONFIG_COMMAND_END', [
    '/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, \'\') */;',
    '/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;',
    '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;',
]);

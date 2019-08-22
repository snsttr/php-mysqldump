<?php

@set_time_limit(3600);
@ini_set('memory_limit', '256M');

$log = '';

// echoes messages
function output($pText, $pSeperatorAfter = false)
{
    try {
        global $log;

        // print to console
        $now = DateTime::createFromFormat('U.u', microtime(true));
        $output = $now->format('m-d-Y H:i:s.u') . ' ' . $pText . PHP_EOL;
        if ($pSeperatorAfter) {
            $output .= '==========================' . PHP_EOL;
        }
        echo $output;

        // add to logfile
        $log .= $output;
    }
    catch(Exception $e) {}
}

// writes sql statements to export file
function export($pTarget, $pSql) {
    try {
        file_put_contents($pTarget, $pSql, FILE_APPEND);
    }
    catch(Exception $e) {
        output('Writing to file not possible: ' . $e->getMessage());
    }
}

// load config
if (!@include_once('config.php')) {
    output('config.php could not be found');
    exit(2);
}

try {
    // abort if script has not been executed through cli
    if (php_sapi_name() !== 'cli') {
        output(basename(htmlentities($_SERVER['SCRIPT_FILENAME'])) . ' is a cli-only script.');
        exit(3);
    }

    // determine arguments
    $hostname = isset($argv[1]) && $argv[1] !== '*' ? $argv[1] : CONFIG_HOSTNAME;
    $username = isset($argv[2]) && $argv[2] !== '*' ? $argv[2] : CONFIG_USERNAME;
    $password = isset($argv[3]) && $argv[3] !== '*' ? $argv[3] : CONFIG_PASSWORD;
    $database = isset($argv[4]) && $argv[4] !== '*' ? $argv[4] : CONFIG_DATABASE;
    $port = !empty($argv[5]) && is_numeric($argv[5]) && $argv[5] !== '*' ? $argv[5] : CONFIG_PORT;
    $exportFile = isset($argv[6]) && $argv[6] !== '*' ? $argv[6] : CONFIG_EXPORT_FILE;
    $exportFile = str_replace('{FILENAME}', date('Y-m-d His') . '-' . $database, $exportFile);
    $logFile = isset($argv[7]) && $argv[7] !== '*' ? $argv[7] : CONFIG_LOG_FILE;
    $logFile = str_replace('{FILENAME}', date('Y-m-d His') . ' ' . $database, $logFile);

    // shutdown functions makes sure that log file is being written
    register_shutdown_function(function($pTarget){
        try {
            global $log, $logFile;
            if (!file_exists(dirname($logFile))) {
                @mkdir(dirname($logFile));
            }
            @file_put_contents(str_replace('.sql', '.log', $logFile), $log);
        }
        catch (Exception $e) {}
    }, $exportFile);

    // connect to mysql
    $mysql = new mysqli($hostname, $username, $password, $database, $port);

    if (mysqli_connect_errno()) {
        output('Connection to mysql failed: ' . mysqli_connect_error());
        exit(4);
    }

    // create export directory if not existent
    $targetDir = dirname($exportFile);
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir) && !is_dir($targetDir)) {
            output('Directory "' . $targetDir . '" could not be created');
            exit(5);
        }
    }

    $infoText = $database . '@' . $hostname . ':' . $port .' (user "' . $username . '") to "' . $exportFile . '" (log: "' . $logFile . '")';
    output('starting export of ' . $infoText, true);

    // add info-header to export file
    export($exportFile, '-- --------------------------------------------------------------------------------------' . PHP_EOL);
    export($exportFile, '-- ' . date('Y-m-d Y-m-d H:i:s') . PHP_EOL);
    export($exportFile, '-- Export of ' . $infoText . PHP_EOL);
    export($exportFile, '-- --------------------------------------------------------------------------------------' . PHP_EOL . PHP_EOL);

    // add 'start command'
    export($exportFile, implode(PHP_EOL, CONFIG_COMMAND_START) . PHP_EOL . PHP_EOL);

    // determine all tables of schema
    $queryDatabase = $mysql->prepare('SELECT TABLE_NAME, TABLE_TYPE FROM `information_schema`.`tables` WHERE TABLE_SCHEMA = ?;');
    $queryDatabase->bind_param('s', $database);
    $queryDatabase->execute();
    $resultDatabase = $queryDatabase->get_result();

}
catch (Exception $e) {
    output('Exception on startup: ' . $e->getMessage());
    exit(6);
}

// export all tables and views
while ($table = $resultDatabase->fetch_assoc()) {
    try {
        $tableName = $table['TABLE_NAME'];
        $infoText = $database . '.' . $tableName;
        output('exporting ' . $table['TABLE_TYPE'] . ' "' . $infoText . '" ...');
        export($exportFile, PHP_EOL . '-- Structure Export of ' . $infoText . '"' . PHP_EOL);

        // handle tables
        if('BASE TABLE' === $table['TABLE_TYPE']) {
            // generate create table statement
            $queryCreateTable = $mysql->query('SHOW CREATE TABLE `' . $database . '`.`' . $tableName . '`;');
            $resultCreateTable = $queryCreateTable->fetch_assoc();
            export($exportFile, $resultCreateTable['Create Table'] . ';' . PHP_EOL . PHP_EOL);
            $queryCreateTable->free();

            // fetch data
            $queryTableData = $mysql->prepare('SELECT * FROM `' . $database . '`.`' . $tableName . '`;');
            $queryTableData->execute();
            $resultTableData = $queryTableData->get_result();

            if ($queryTableData->affected_rows > 0) {

                // add 'before insert command'
                export($exportFile, '-- Data Export of ' . $infoText . ' (' . $resultTableData->num_rows . ' rows)' . PHP_EOL);
                export($exportFile, str_replace('{TABLE}', $tableName, implode(PHP_EOL,CONFIG_COMMAND_BEFORE_INSERTS) . PHP_EOL));

                $bucketSize = CONFIG_BUCKET_SIZE;
                $maxStatementLength = CONFIG_MAX_QUERY_LENGTH;
                $count = 0;
                $generatedSqlStart = false;
                $written = false;
                $first = true;

                // export every row
                while ($tableRow = $resultTableData->fetch_assoc()) {
                    try {
                        $written = false;

                        // get field names
                        if ($first) {
                            if (false === $generatedSqlStart) {
                                $generatedSqlStart .= 'INSERT INTO `' . $tableName . '` (`' . implode('`, `', array_keys($tableRow)) . '`) VALUES (';
                            }
                            $generatedSql = $generatedSqlStart;
                            $first = false;
                        } else {
                            $generatedSql .= ', (';
                        }

                        // insert data
                        $firstField = true;
                        foreach ($tableRow as $tableValue) {
                            if (!$firstField) {
                                $generatedSql .= ', ';
                            } else {
                                $firstField = false;
                            }
                            if (is_string($tableValue)) {
                                $generatedSql .= '\'' . $mysql->real_escape_string($tableValue) . '\'';
                            } elseif (is_null($tableValue)) {
                                $generatedSql .= 'NULL';
                            } else {
                                $generatedSql .= $tableValue;
                            }
                        }

                        $generatedSql .= ')';

                        // finish bucket and write to file
                        if ($bucketSize === ++$count || mb_strlen($generatedSql) > $maxStatementLength) {
                            export($exportFile, $generatedSql . ';' . PHP_EOL);
                            $written = true;
                            $generatedSql = $generatedSqlStart;
                            $count = 0;
                            $first = true;
                        }
                    } catch (Exception $e) {
                        output('Exception while processing table row: ' . $e->getMessage());
                        exit(7);
                    }
                }

                // write to export file if there is data left
                if (!$written) {
                    export($exportFile, $generatedSql . ';' . PHP_EOL);
                }

                // add 'after insert command'
                export($exportFile, str_replace('{TABLE}', $tableName, implode(PHP_EOL,CONFIG_COMMAND_AFTER_INSERTS) . PHP_EOL . PHP_EOL));
            }

            // clean up
            $resultTableData->free();
            unset($queryCreateTable, $resultCreateTable, $queryTableData, $resultTableData, $generatedSqlStart, $tableRow, $generatedSql, $tableRow, $tableValue);
        }
        // handle views
        elseif('VIEW' === $table['TABLE_TYPE']) {
            // generate create view statement
            $queryCreateTable = $mysql->query('SHOW CREATE VIEW `' . $database . '`.`' . $tableName . '`;');
            $resultCreateTable = $queryCreateTable->fetch_assoc();
            export($exportFile, $resultCreateTable['Create View'] . ';' . PHP_EOL . PHP_EOL);
            $queryCreateTable->free();
        }
        else {
            output('Skipped (unknown TABLE_TYPE "' . $table['TABLE_TYPE'] . '".');
        }

        output('... finished exporting "' . $tableName . '".', true);
    }
    catch (Exception $e) {
        output('Exception while processing table row: ' . $e->getMessage());
        exit(8);
    }
}

// add 'end command'
export($exportFile, $string = implode(PHP_EOL,CONFIG_COMMAND_END) . PHP_EOL);

// clean up
@$queryDatabase->free_result();
@$mysql->close();

output('export was successful: ' . $exportFile);
exit(0);
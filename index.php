<?php
require 'export.php';

// Start Up
$configFile = (array) json_decode(file_get_contents("./config.json"));
if (!$configFile) {
    die("Config File is required");
}

$variables = ['serverName', 'username', 'password', 'port', 'databaseName', 'tables'];

foreach ($variables as $variable) {
    if (!array_key_exists($variable, $configFile)) {
        die("$variable is required in config file");
    }
    if (is_null($configFile[$variable]) && $configFile[$variable] != "") {
        die("$variable is required in config file");
    }
}

$mysqlConnection = new MysqlConnection(
    $configFile[$variables[0]],
    $configFile[$variables[1]],
    $configFile[$variables[2]],
    $configFile[$variables[3]],
    $configFile[$variables[4]]
);
$export = $mysqlConnection->exportTables($configFile[$variables[5]]);

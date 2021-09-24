<?php
require 'import.php';
require 'export.php';

function configCheck(array $variables, array $configFileArray, string $errMsg = "")
{
    foreach ($variables as $variable) {
        if (!array_key_exists($variable, $configFileArray)) {
            die("$errMsg $variable is required in config file");
        }

        if (is_null($configFileArray[$variable]) && $configFileArray[$variable] != "") {
            die("$errMsg $variable is required in config file");
        }
    }
}

// Start Up
$configFile = (array) json_decode(file_get_contents("./config.json"));
if (!$configFile) {
    die("Config File is required");
}

$startUpVariable = ["operationType", "importConfig", "exportConfig"];

configCheck($startUpVariable, $configFile);

if ($configFile['operationType'] == "import") {
    $importConfig = (array) $configFile[$startUpVariable[1]];
    $importConfigvariables = ['serverName', 'username', 'password', 'port', 'databaseName'];

    // check import config 
    configCheck($importConfigvariables, $importConfig, "Import");

    var_dump('proceed to import');

    $importConnection = new ImportConnection(
        $importConfig[$importConfigvariables[0]],
        $importConfig[$importConfigvariables[1]],
        $importConfig[$importConfigvariables[2]],
        $importConfig[$importConfigvariables[3]],
        $importConfig[$importConfigvariables[4]],
    );
} else if ($configFile['operationType'] == "export") {
    $exportConfig = (array) $configFile[$startUpVariable[2]];
    $exportConfigVariables = ['serverName', 'username', 'password', 'port', 'databaseName', 'tables'];

    // check export config 
    configCheck($exportConfigVariables, $exportConfig, "Export");

    $exportConnection = new ExportConnection(
        $exportConfig[$exportConfigVariables[0]],
        $exportConfig[$exportConfigVariables[1]],
        $exportConfig[$exportConfigVariables[2]],
        $exportConfig[$exportConfigVariables[3]],
        $exportConfig[$exportConfigVariables[4]],
    );

    $export = $exportConnection->exportTables($exportConfig[$exportConfigVariables[5]]);
} else {
    die("Invalid Operation Type");
}

<?php

class MysqlConnection
{
    private $server_name = "localhost";
    private $username = "root";
    private $password = "";
    private $port = "3306";
    private $db_name = "";
    private $conn = null;
    private $sql_migration_query = "";
    private $tables = [];
    private $column_names = [];
    private $columns_obj = [];
    private $insertion_column_names_cahced;

    public function __construct($db_name)
    {
        $this->db_name = $db_name;
        $this->createConnection();
    }

    private function createConnection()
    {
        if ($this->conn != null) {
            return;
        }

        $this->conn = new mysqli($this->server_name, $this->username, $this->password, $this->db_name, $this->port);
        if ($this->conn->connect_error) {
            die("Connection failed:" . $this->conn->connect_error);
        }
    }

    function getConn()
    {
        return $this->conn;
    }

    private function getColumnDefaultDesc($field, $default)
    {
        if ($default != "") {
            return "DEFAULT " . $default;
        }
        return "";
    }

    private function getColumnExtraDesc($field, $extra)
    {
        return $extra;
    }

    function getColumnKeyDesc($field, $key)
    {
        if ($key == "PRI") {
            return "PRIMARY KEY";
        } else if ($key == "UNI") {
            return "UNIQUE";
        }/* else if ($key == "MUL") {
		return "index(" . $field . ")";
	}*/
        return "";
    }

    function getRowsInRange($table_name, $offset, $limit)
    {
        $query = "SELECT * FROM $table_name LIMIT $limit OFFSET $offset";
        return $this->executeQueryFetch($query);
    }

    function executeQuery(string $query)
    {
        $result = $this->conn->query($query);
        if (!$result) die(mysqli_error($this->conn));
        return $result;
    }

    function executeQueryFetch(string $query)
    {
        $result = $this->executeQuery($query)->fetch_all();
        return $result;
    }

    function exportTables(array $tables = [])
    {
        if (!$this->dbExist($this->db_name)) {
            die("Invalid Database Name");
        }

        if (count($tables) > 0) {
            ['isError' => $isError, 'errMsg' => $errMsg] = $this->tablesExist($tables);
            if ($isError) die($errMsg);
        } else {
            $tables = $this->fetchAllTables();
        }

        $this->tables = $tables;

        $this->createMigrationSchema();
    }

    function createMigrationSchema()
    {
        $this->sql_migration_query = "";
        foreach ($this->tables as $table) {
            $tbl_schema = $this->createTableSchema($table);
            $this->sql_migration_query .= "\n\n" . $tbl_schema . "\n\n";

            $tbl_insert_schema = $this->createInsertTableSchema($table);
        }
    }


    private function createTableSchema($table_name)
    {
        $query = "CREATE TABLE IF NOT EXISTS '$table_name'(\n";
        $this->insertion_column_names_cahced = "";
        $rows = $this->getTableHeader($table_name);
        $rows_count = count($rows);

        foreach ($rows as $key => $val) {
            $col = new MySQLColumns();
            $col->Field = $val[0];
            $col->Type = $val[1];
            $col->Null = $val[2];
            $col->Key = $val[3];
            $col->Default = $val[4];
            $col->Extra = $val[5];

            array_push($this->columns_obj, $col);
            array_push($this->column_names, $col->Field);

            $query .= " " . $col->Field . " " . $col->Type . " " . $this->getColumnExtraDesc($col->Field, $col->Extra) . "" .
                (!$col->Null ? "NOT NULL" : "") . " " . $this->getColumnDefaultDesc($col->Field, $col->Default) . " " . $this->getColumnKeyDesc($col->Field, $col->Key)
                . ($key < $rows_count - 1 ? "," : "") . "\n";
            $this->insertion_column_names_cahced .= $col->Field . "" . ($key < $rows_count - 1 ? ", " : "");
        }

        $query .= ");";

        return $query;
    }

    private function createInsertTableSchema($table_name, $offset = 0, $limit = 50)
    {
        $get_data_in_range = $this->getRowsInRange($table_name, $offset, $limit);
        $get_data_in_range_count = count($get_data_in_range);
        if ($get_data_in_range_count == 0) return "";
        $query = "INSERT INTO " . $table_name . " (" .
            $this->insertion_column_names_cahced . ") VALUES (";
        $column_names_count = count($this->column_names);
        foreach ($get_data_in_range as $key => $val) {
            // $query .=
            foreach ($this->column_names as $column_key => $column_val) {
                $column_val = 'null';
                if($val->$columnVal)
            }
        }

        $query .= ";";
    }

    /**
     * @param array $tables
     * 
     * @return array ['isError' => $isError, 'errMsg' => $errMsg]
     */
    function tablesExist(array $tables)
    {
        foreach ($tables as $table) {
            $query = "DESCRIBE $table";
            $tb = $this->executeQueryFetch($query);
            if (!count($tb) > 0) {
                return ['isError' => true, 'errMsg' => 'Invalid table ' . $table];
            }
        }
    }

    function dbExist($db_name)
    {
        $db = $this->executeQueryFetch("SHOW DATABASES LIKE '$db_name'");
        return count($db) > 0 ? true : false;
    }

    function fetchAllDatabases()
    {
        $result_row = $this->executeQueryFetch("SHOW DATABASES;");
        return $result_row;
    }

    function fetchAllTables()
    {
        $result_row = $this->executeQueryFetch("SHOW TABLES");
        return $result_row;
    }

    function getTableHeader($table_name)
    {
        $query = "DESCRIBE $table_name";
        $result_row = $this->executeQueryFetch($query);
        return $result_row;
    }
}

class MySQLColumns
{
    public $Field;
    public $Type;
    public $Null;
    public $Key;
    public $Default;
    public $Extra;
}

$mysqlConnection = new MysqlConnection('healthtouch_main');
$export = $mysqlConnection->exportTables([
    'providers',
    'providercategory'
]);

// function backupDatabaseTables($db_name, array $tables = [])
// {
//     $db = new mysqli("localhost", "root", "", $db_name);

//     if (count($tables) == 0) {
//         $tables = array();
//         $result = $db->query("SHOW TABLES");
//         while ($row = $result->fetch_row()) {
//             $tables[] = $row[0];
//         }
//     }

//     // var_dump($tables);

//     $return = "";

//     foreach ($tables as $table) {
//         // select * table data
//         $result = $db->query("SELECT * FROM $table");
//         $numColumns = $result->field_count;

//         // select create table query
//         $result2 = $db->query("SHOW CREATE TABLE $table");
//         $row2 = $result2->fetch_row();

//         $return .= "\n\n" . $row2[1] . ";\n\n";


//         for ($i = 0; $i < $numColumns; $i++) {
//             while ($row = $result->fetch_row()) {
//                 $return .= "INSERT INTO $table VALUES(";
//                 for ($j = 0; $j < $numColumns; $j++) {
//                     $row[$j] = addslashes($row[$j]);
//                     $row[$j] = $row[$j];
//                     if (isset($row[$j])) {
//                         $return .= '"' . $row[$j] . '"';
//                     } else {
//                         $return .= '""';
//                     }
//                     if ($j < ($numColumns - 1)) {
//                         $return .= ',';
//                     }
//                 }
//                 $return .= "); \n";
//             }
//         }
//         $return .= "\n\n\n";
//     }

//     $handle = fopen($db_name . time() . '.sql', 'w+');
//     fwrite($handle, $return);
//     fclose($handle);

//     echo "Database Export Successfully";
// }

// backupDatabaseTables("healthtouch_main", ['providercategory']);

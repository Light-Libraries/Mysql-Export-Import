<?php

set_time_limit(0);
ini_set('memory_limit', '1024M');

class ExportConnection
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

    public function __construct($server_name, $username, $password, $port, $db_name)
    {
        $this->server_name = $server_name;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
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
            return "DEFAULT '" . $default . "'";
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

    function getRowsInRange($table_name,  $limit, $offset)
    {
        $query = "SELECT * FROM $table_name LIMIT $limit OFFSET $offset";
        return $this->executeQueryFetch($query);
    }

    function getTableInfo($table_name)
    {
        $query = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='$table_name'";
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
        try {
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
            $this->createSqlFile();

            return $this->sql_migration_query;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    function createJsonFile($query)
    {
        if ($query == "" || $query == null) return;

        $filename = 'queries.json';
        $dir = './' . $filename;

        $data = [
            "query" => $query,
            'execute' => false,
            'success' => false
        ];

        if (!file_exists($dir)) {
            $queries = [];
            array_push($queries, $data);
            $handle = fopen($filename, "w+");
            fwrite($handle, json_encode($queries));
            fclose($handle);
        } else {
            // get and push to qurery
            $prevqueries = (array) json_decode(file_get_contents($dir));
            array_push($prevqueries, $data);

            $handle = fopen($filename, "w");
            fwrite($handle, json_encode($prevqueries));
            fclose($handle);
        }
    }

    function createSqlFile()
    {
        $handle = fopen($this->db_name . time() . '.sql', 'w+');
        fwrite($handle, $this->sql_migration_query);
        fclose($handle);
    }

    function createMigrationSchema()
    {
        $this->sql_migration_query = "";
        foreach ($this->tables as $table) {
            $table = is_array($table) ? $table[0] : $table;

            $tbl_schema = $this->createTableSchema($table);
            $this->sql_migration_query .= "\n\n" . $tbl_schema . "\n\n\n";
            $this->createJsonFile($tbl_schema);

            $tbl_insert_schema = $this->createInsertTableSchema($table);
            $this->sql_migration_query .= "\n\n" . $tbl_insert_schema . "\n\n\n";
            $this->createJsonFile($tbl_insert_schema);
        }
    }

    private function createTableSchema($table_name)
    {
        $query = "CREATE TABLE IF NOT EXISTS `$table_name`(\n";
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

            $query .= " `" . $col->Field . "` " . $col->Type . " " . $this->getColumnExtraDesc($col->Field, $col->Extra) . "" . (!$col->Null ? "NOT NULL" : "") . " " . $this->getColumnDefaultDesc($col->Field, $col->Default) . " " . $this->getColumnKeyDesc($col->Field, $col->Key) . ($key < $rows_count - 1 ? "," : "") . "\n";
            $this->insertion_column_names_cahced .= "`" . $col->Field . "`" . ($key < $rows_count - 1 ? "," : "");
        }

        $query .= ");";
        return $query;
    }

    private function createInsertTableSchema($table_name)
    {
        $table_info = $this->getTableInfo($table_name)[0];
        $table_rows = $table_info[7];

        if ($table_rows == 0) return "";
        $default_limit = 500;
        $default_offset = 0;
        $run_count = ceil($table_rows / $default_limit);
        $table_data = [];

        $query = "INSERT INTO `$table_name` (" .
            $this->insertion_column_names_cahced . ") VALUES ";
        $track = 0;

        for ($i = 0; $i <= $run_count; $i++) {
            if ($i == 1) {
                $default_offset = $default_limit;
            } else if ($i > 1) {
                $default_offset += $default_limit;
            }

            $get_data_in_range = $this->getRowsInRange($table_name, $default_limit, $default_offset);
            for ($j = 0; $j < count($get_data_in_range); $j++) {
                $track++;
                $query .= "(";
                $curr = $get_data_in_range[$j];
                for ($k = 0; $k < count($curr); $k++) {
                    $col_val = 'NULL';
                    if ($curr[$k] != null && $curr[$k] != "") {
                        $col_val = "'" . $curr[$k] . "'";
                    }
                    $query .= $col_val . ($k == count($curr) - 1 ? "" : ",") . "";
                }
                $query .= ")" . ($track == $table_rows ? ";" : ", \n") . "";
            }
        }
        return $query;
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
        $tables = [];
        foreach ($result_row as $key => $val) {
            array_push($tables, $val);
        }
        return $tables;
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

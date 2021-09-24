<?php

set_time_limit(0);
ini_set('memory_limit', '1024M');

class ImportConnection
{
    private $server_name = "localhost";
    private $username = "root";
    private $password = "";
    private $port = "3306";
    private $db_name = "";
    private $conn = null;
    private $filename = 'queries.json';

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

    function importDatabase()
    {
        try {
            if (!$this->dbExist($this->db_name)) {
                die("Invalid Database Name");
            }

            $dir = './' . $this->filename;

            if (!file_exists($dir)) {
                die($this->filename . " file does not exist");
            }

            $queries = (array) json_decode(file_get_contents($dir));

            for ($i = 0; $i < count($queries); $i++) {
                $currQuery = $queries[$i]->query;
                $currExecute = $queries[$i]->execute;
                $currSuccess = $queries[$i]->success;

                if ($currExecute == false && $currSuccess == false) {
                    // execute fresh query
                    $this->queryOps($queries, $currQuery, $i, true, $currSuccess);
                } else if ($currExecute == true && $currSuccess == false) {
                    // execute failed query
                    $this->queryOps($queries, $currQuery, $i, $currExecute, $currSuccess);
                }
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    function queryOps($queries, $currQuery, $index, $currExecute, $currSuccess)
    {
        var_dump("queryOps");
        $result = $this->conn->query($currQuery);
        $currSuccess =  !$result ? false : true;
        $this->updateQueryFile($queries, $index, $currExecute, $currSuccess);
    }

    function updateQueryFile(array $queries, int $index, bool $execute, bool $success)
    {

        $queries[$index] = [
            "query" => $queries[$index]->query,
            'execute' => $execute,
            'success' => $success
        ];

        $handle = fopen($this->filename, 'w+');
        fwrite($handle, json_encode($queries));
        fclose($handle);
    }

    function dbExist($db_name)
    {
        $db = $this->executeQueryFetch("SHOW DATABASES LIKE '$db_name'");
        return count($db) > 0 ? true : false;
    }

    function executeQueryFetch(string $query)
    {
        $result = $this->executeQuery($query)->fetch_all();
        return $result;
    }

    function executeQuery(string $query)
    {
        $result = $this->conn->query($query);
        if (!$result) die(mysqli_error($this->conn));
        return $result;
    }
}

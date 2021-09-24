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
}

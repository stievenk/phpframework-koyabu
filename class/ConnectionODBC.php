<?php
namespace Koyabu\Webapi;

class ConnectionODBC {
    public $conn;
    public $error;
    public $config;

    function __construct($config) {
        $this->config = $config;

        try {
            $dsn  = $config['dsn'];     // e.g: "SQL Anywhere 17 Sample"
            $user = $config['user'] ?? "";
            $pass = $config['pass'] ?? "";

            $this->conn = odbc_connect($dsn, $user, $pass);

            if (!$this->conn) {
                throw new \Exception(odbc_errormsg(), 1);
            }

        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'done'=> 0,
                'response' => $this->error,
                'error' => 'Internal server Error 500',
                'code' => 500,
                'status' => 'error'
            ]);
            exit;
        }
    }

    public function query($query) {
        $result = @odbc_exec($this->conn, $query);

        if (!$result) {
            $this->error = odbc_errormsg($this->conn);
            return false;
        }

        return $result;
    }

    public function fetch_assoc($result) {
        return odbc_fetch_array($result);
    }

    public function fetch_row($result) {
        return odbc_fetch_row($result) ? true : false;
    }

    public function fetch_array($result) {
        $row = [];
        odbc_fetch_into($result, $row);
        return $row;
    }

    public function num_rows($result) {
        return odbc_num_rows($result);
    }

    public function insert_id() {
        // SQL Anywhere / MSSQL bisa pakai SELECT @@IDENTITY
        $res = $this->query("SELECT @@IDENTITY AS id");
        $row = $this->fetch_assoc($res);
        return $row['id'] ?? null;
    }

    public function escape_string($string) {
        return str_replace("'", "''", $string);
    }

    function __destruct() {
        if ($this->conn) {
            odbc_close($this->conn);
        }
    }
}
?>
<?php
namespace Koyabu\Webapi;

class ConnectionPDO {
    public $conn;
    public $error;
    public $config;

    function __construct($config) {
        $this->config = $config;

        try {
            $dsn = $config['dsn'] ?? "mysql:host={$config['host']};dbname={$config['data']}";
            $user = $config['user'] ?? null;
            $pass = $config['pass'] ?? null;

            $this->conn = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'done' => 0,
                'response' => $this->error,
                'error' => 'Internal server Error 500',
                'code' => 500,
                'status' => 'error'
            ]);
            exit;
        }
    }

    public function query($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function fetch_assoc($stmt) {
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function fetch_array($stmt) {
        return $stmt->fetch(\PDO::FETCH_BOTH);
    }

    public function fetch_row($stmt) {
        return $stmt->fetch(\PDO::FETCH_NUM);
    }

    public function num_rows($stmt) {
        return $stmt->rowCount();
    }

    public function insert_id() {
        return $this->conn->lastInsertId();
    }

    public function escape_string($string) {
        return substr($this->conn->quote($string), 1, -1);
    }

    function __destruct() {
        $this->conn = null;
    }
}
?>
<?php
namespace Koyabu\Webapi;

class Connection {

    public $conn;
    public $error;
    public $config;

    function __construct($config) {
        $this->config = $config;
        try {
            if (!$this->conn = new \mysqli($config['host'],$config['user'],$config['pass'],$config['data'])) {
                throw new \Exception($this->conn->connect_error, 1);
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            http_response_code(500);
            header('Content-Type: application/json');
            $data = array('done'=> 0, 'response' => $this->error, 'error' => 'Internal server Error 500', 'code' => 500, 'status' => 'error');
            echo json_encode($data); exit;
        }
    }

    /* =============================
       LEGACY FUNCTIONS (UNCHANGED)
       ============================= */

    public function error($die=0) {
        $this->error = $this->conn->error;
        return $this->error;
    }

    public function query($query) {
        try {
            if ($query) {
                $qry = $this->conn->query($query);
                if ($qry) { return $qry; } else {
                    throw new \Exception($this->conn->error, 1);
                    // return false;
                }
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function multi_query($query) {
        return $this->conn->multi_query($query);
    }

    public function store_result() {
        return $this->conn->store_result();
    }

    public function next_result() {
        return $this->conn->next_result();
    }

    public function more_results() {
        return $this->conn->more_results();
    }

    public function insert_id() {
        return $this->conn->insert_id;
    }

    public function fetch_assoc($result) {
        return $result->fetch_assoc();
    }

    public function fetch_array($result) {
        return $result->fetch_array();
    }

    public function fetch_row($result) {
        return $result->fetch_row();
    }

    public function fetch_field($result) {
        return $result->fetch_field();
    }

    public function fetch_fields($result) {
        return $result->fetch_fields();
    }

    public function num_rows($result) {
        return $result->num_rows;
    }

    public function fetch_length($result) {
        return $result->length;
    }

    public function escape_string($string) {
        if (is_array($string)) {
            return array_map([$this, 'escape_string'], $string);
        }
        
        // Pastikan nilai dikonversi ke string sebelum di-escape
        return $this->conn->real_escape_string((string) $string);
    }


    /* =============================
       NEW PREPARED STATEMENT CORE
       ============================= */

    public function detect_type($value) {
        if (is_int($value)) return "i";
        if (is_float($value)) return "d";
        if (is_null($value)) return "s";
        return "s";
    }

    public function build_types($params) {
        $types = "";
        foreach ($params as $v) {
            $types .= $this->detect_type($v);
        }
        return $types;
    }

    public function run_prepared($sql, $params = []) {

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            $this->error = $this->conn->error;
            return false;
        }

        if ($params) {
            $types = $this->build_types($params);
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $this->error = $stmt->error;
            return false;
        }

        return $stmt;
    }


    /* =============================
       QUERY BUILDERS
       ============================= */

    public function build_where($where) {

        $sql = [];
        $params = [];

        foreach ($where as $k => $v) {
            $sql[] = "`$k`=?";
            $params[] = $v;
        }

        return [
            "sql" => implode(" AND ", $sql),
            "params" => $params
        ];
    }


    /* =============================
       AUTO INSERT
       ============================= */

    public function insert($table, $data) {

        $fields = array_keys($data);
        $params = array_values($data);

        $columns = "`" . implode("`,`", $fields) . "`";
        $placeholders = implode(",", array_fill(0, count($fields), "?"));

        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";

        $stmt = $this->run_prepared($sql, $params);

        if (!$stmt) return false;

        return $this->conn->insert_id;
    }


    /* =============================
       AUTO UPDATE
       ============================= */

    public function update($table, $data, $where) {

        $set = [];
        $params = [];

        foreach ($data as $k=>$v) {
            $set[] = "`$k`=?";
            $params[] = $v;
        }

        $whereBuild = $this->build_where($where);

        $params = array_merge($params, $whereBuild['params']);

        $sql = "UPDATE `$table` SET ".implode(",",$set)." WHERE ".$whereBuild['sql'];

        $stmt = $this->run_prepared($sql, $params);

        if (!$stmt) return false;

        return $stmt->affected_rows;
    }


    /* =============================
       AUTO SELECT
       ============================= */

    public function select($table, $where=[], $fields="*") {

        $params = [];

        $sql = "SELECT $fields FROM `$table`";

        if ($where) {

            $whereBuild = $this->build_where($where);

            $sql .= " WHERE ".$whereBuild['sql'];

            $params = $whereBuild['params'];
        }

        $stmt = $this->run_prepared($sql,$params);

        if (!$stmt) return false;

        if (method_exists($stmt,'get_result')) {
			return $stmt->get_result();
		}

		$stmt->store_result();
		return $stmt;
    }


    /* =============================
       AUTO DELETE
       ============================= */

    public function delete($table, $where) {

        $whereBuild = $this->build_where($where);

        $sql = "DELETE FROM `$table` WHERE ".$whereBuild['sql'];

        $stmt = $this->run_prepared($sql,$whereBuild['params']);

        if (!$stmt) return false;

        return $stmt->affected_rows;
    }


    /* =============================
       TRANSACTION SAFE
       ============================= */

    public function start_transaction() {
		$this->conn->begin_transaction();
	}

	public function commit_transaction() {
		$this->conn->commit();
	}

	public function rollback_transaction() {
		$this->conn->rollback();
	}

    public function transaction($callback) {

        try {

            $this->conn->begin_transaction();

            $callback($this);

            if ($this->error) {
                throw new \Exception($this->error);
            }

            $this->conn->commit();

        } catch (\Exception $e) {

            $this->conn->rollback();

            $this->error = $e->getMessage();

            return false;
        }

        return true;
    }


    /* =============================
       DESTRUCTOR
       ============================= */

    function __destruct() {
        $this->conn->close();
    }
}
?>
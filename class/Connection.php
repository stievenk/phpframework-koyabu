<?php
namespace Koyabu\Webapi;

class Connection {
   public $conn;
	public $error;
	public $config;
	
	function __construct($config) {
		try {
			if (!$this->conn = new \mysqli($config['host'],$config['user'],$config['pass'],$config['data'])) {
				throw new \Exception($this->conn->connect_error, 1);
			}
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			$data = array('done'=> 0, 'response' => $this->error);
			echo json_encode($data); exit;
		}

	}

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
					return false;
				}
			}
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			return false;
		}
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
		return $this->conn->real_escape_string($string);
	}

	function __destruct() {
		$this->conn->close();
	}
}
?>
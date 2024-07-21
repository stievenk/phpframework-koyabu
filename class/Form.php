<?php
namespace Koyabu\Webapi;
/** 
 * Koyabu Framework
 * version: 8.1.0
 * last update: 21 Juli 2024
 * min-require: PHP 8.1 
 * MariaDB: 10+ (recommended) or MySQL : 8+
 * Author: stieven.kalengkian@gmail.com
*/
class Form {
    public $Version = '8.1.0'; // 21 Juli 2024
    public $Database;
    public $config;
    public $error;

    function __construct($config) {
        $this->config = $config;
        $this->SQLConnection($config);
    }

    public function SQLConnection($config) {
        try {
            if ($config['mysql']) {
                $this->Database = new Connection($config['mysql']);
            } else {
                throw new \Exception("Mysql config error:".$this->Database->error, 1);
                
            }
        } catch (\Exception $e) {
            $error['response'] = $e->getMessage();
            echo json_encode($error); exit;
        }
    }

    public function get($id,$table,$fld='id') {
        try {
            if (is_array($id)) {
                $f = array();
                foreach($id as $k => $v) {
                    $f[]="`$k` = '". $this->Database->escape_string($v) ."'";
                }
                if (!$g = $this->Database->query("select * from `{$table}` where ". implode(" and ",$f) ."")) {
                    throw new \Exception($this->Database->error, 1);
                }
            } else {
                if (!$g = $this->Database->query("select * from `{$table}` where `{$fld}`='". $this->Database->escape_string($id) ."'")) {
                    throw new \Exception($this->Database->error, 1);
                }
            }
            return $this->Database->fetch_assoc($g);
         } catch (\Exception $e) {
            $this->error = $e->getMessage();
            echo json_encode(array('done' => 0, 'response' => $this->error)); exit;
         }
	}

    public function save($data,$table,$method='INSERT',$primary='id') {
        $error = array('done' => 0, 'response' => '');
        try {
            if (is_array($data)) {
                $datas = $data;
                $f = $this->parse($data);
                $fl = array();
                if (is_array($primary)) {
                    $new = $method == 'REPLACE' ? 1 : 0;
                    foreach($primary as $v) {
                        $fl[] = "`{$v}` = '". $this->Database->escape_string($datas[$v]) ."'";
                        if (!trim($datas[$v])) { $new = 1; }
                    }
                    if ($new == 0) { 
                        $NEW = 0; 
                        $SQL = "UPDATE `{$table}` SET {$f['field']} WHERE ". implode(" and ",$fl) .""; 
                        $ID = $datas[$primary[0]];
                    }
                    else {
                        $SQL = "{$method} INTO `{$table}` SET {$f['field']}"; 
                        $NEW = 1;
                        $ID = ($method == 'REPLACE') ? $datas[$primary[0]] : 0;
                    }
                } else {
                    if ($datas[$primary] and $method != 'REPLACE') {
                        $SQL = "UPDATE `{$table}` SET {$f['field']} WHERE `{$primary}`='{$datas[$primary]}'";
                        $NEW = 0;
                        $ID = $datas[$primary];
                    } else {
                        $SQL = "{$method} INTO `{$table}` SET {$f['field']}"; 
                        $NEW = 1;
                        $ID = ($method == 'REPLACE') ? $datas[$primary] : 0;
                    }
                }

                if ($this->Database->query($SQL)) {
                    if ($NEW == 1) {
                        $this->NEW=1;
                        $ID = $ID ? $ID : $this->Database->insert_id();
                    } else {
                        $log = array('query' => $SQL, 'msg' => "UPDATE RECORD {$table} #{$ID}");
                    }
                    return $ID;
                } else { 
                    $this->error = $this->Database->error()." ({$SQL})"; 
                    throw new \Exception($this->error, 1);    
                }
            } else {
                throw new \Exception("Invalid Parameters", 1);
            }
        } catch (\Exception $e) {
            $error['response'] = $e->getMessage();
            echo json_encode($error); exit;
        }
	}

    function delete($params,$table,&$data) {
		if ($table == 'query') {
			if ($this->Database->query($params)) {
				$log = array('msg' => $params);
				return true;
			} else {
				$this->error = $this->Database->error(); 
				$log = array('errmsg' => $this->error, 'query' => $params);
				return false;
			}
		} else {
			try {
				if (is_array($params)) {
					$key = array_keys($params);
					$field = $key[0];
					$id = $this->escape_string($params[$field]);
					$data = $this->get($id,$table,$field);
					if ($this->Database->query("DELETE FROM `{$table}` where `$field`='{$id}'")) {
						return true;
					} else {
						throw new \Exception($this->Database->error(), 1);
						$log = array('errmsg' => $this->error, 'raw' => $DATA);
						return false;
					}
				} else { throw new \Exception("Invalid Arguments", 1); }
			} catch (\Exception $e) {
				$this->error = $e->getMessage();
                echo json_encode(array('response' => $this->error, 'done' => 0)); exit;
			}
			
		}
	}

    public function query($query) {
        try {
            if (!$result = $this->Database->query($query)) {
                throw new \Exception("Error: ".$this->Database->error, 1);
            }
            return $result;
        } catch (\Exception $e) {
            echo json_encode(array('done' => 0, 'response' => $e->getMessage())); exit;
        }
    }
    
    public function select($query) { return $this->query($query); }

    public function fetch($object,$tipe = 'assoc') {
        try {
            if ($tipe == 'assoc') {
                $o = $this->Database->fetch_assoc($object);
            } else {
                $o = $this->Database->fetch_assoc($object);
            }
            return $o;
        } catch (\Exception $e) {
            echo json_encode(array('done' => 0, 'response' => $e->getMessage())); exit;
        }
    }

    function parse($data,$idyes=0) {
		$id = 0;
        $f = array();
		foreach($data as $k => $v) {
			if ($k == 'id' and $idyes == 0) { $id = $v; }
			else {
				$k = $this->escape_string(trim($k));
				if (!is_array($v)) {
					$f[]="`{$k}` = '". $this->escape_string(trim($this->stripHTML($v))) ."'";
				}
			}
		}
		if (count($f) > 0) {
			$s = implode(", ",$f);
			return array('id' => $id,'field' => $s);
		} else { return false; }
	}

    function ignoreParams($data,$chars='') {
		$p = array();
		foreach($data as $k => $v) {
			if (preg_match("#{$chars}#",$k)) {
				unset($data[$k]);
			} else {
				//$p[trim($k)] = !is_array($v) ? strip_tags($v) : $v;
				$p[trim($k)] = $v;
			}
		}
		return $p;
	}
	
	function stripHTML($m) {
			
		if ($this->stripHTMLEnable == 1) {
			if ($this->disableHTML == 1) { $m = strip_tags($m); }
			if ($this->disableCSS == 1) {
				if ($this->toEntities == 1) {
					$m =  htmlentities($m,ENT_QUOTES);
				} else {
					$m = preg_replace("(<style(|.+?)>(.+?)</style>)","<!--style \\2 style-->",$m);
					$m = preg_replace("(<link(|.+?)>)","<!--link \\1 -->",$m);
					$m = preg_replace('#style="(.+?)"#','style=""',$m);
					//$m = preg_replace('#class="(.+?)"#','class=""',$m);
					$m = preg_replace('#href="(.+?)"#','',$m);
				} 
			}
			if ($this->disableSCRIPT == 1) { 
				if ($this->toEntities == 1) {
					$m = htmlentities($m,ENT_QUOTES);
				} else {
					$m = preg_replace("(<script(|.+?)>(.+?)</script>)","<!--script \\2 script-->",$m);
				}
			}
		}
		return $m;
		
	}

    public function escape_string($string) {
        return $this->Database->escape_string($string);
    }

    function save_dbx($file,$dir='') {
        $data = array('done' => 0, 'response' => '');
        $HOME_DIR = $config['dropbox']['home_dir'].$dir;
		$HOME_DIR = $HOME_DIR ? $HOME_DIR.'/' : '';
        try {
            if (!$this->config['dropbox']['access_token']) {
                throw new \Exception("Dropbox Access Token not set", 1);
            }
            if (!file_exists($file)) {
                throw new \Exception("File ".basename($file)." not found", 1);
            }
            $DBX = new Dropbox($config['dropbox']['access_token']); 
            $DBX->upload($file,'overwrite','/'.$HOME_DIR);
			$d = $DBX->create_shared_link($HOME_DIR.basename($file));
            if ($d['url']) {
				$url = str_replace("dl=0","raw=1",$d['url']);
				if (file_exists($file)) { unlink($file); }
				return $url;
			} else {
                throw new \Exception("Dropbox Error, file can not upload", 1);
                return false; 
            }
        } catch(\Exception $e) {
            $this->error = $e->getMessage();
            $data['response'] = $this->error;
            return false;
        }
	}

    function __destruct() {

    }
}
?>
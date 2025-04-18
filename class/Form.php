<?php
namespace Koyabu\Webapi;
use Koyabu\Webapi;
use chillerlan\QRCode\{QRCode, QROptions};
/** 
 * Koyabu Framework
 * version: 8.2.0
 * last update: 27 Oktober 2024
 * min-require: PHP 8.1 
 * MariaDB: 10+ (recommended) or MySQL : 8+
 * Author: stieven.kalengkian@gmail.com
*/
class Form {
    public $Version = '8.2.0';
    public $Database;
    public $config;
    public $error;

    function __construct($config) {
        $this->config = $config;
        $this->SQLConnection($config);
    }

    public function SQLConnection($config) {
        try {
            if (!isset($config['mysql'])) {
					throw new \Exception("Mysql config error: no config found", 1);
            }
				$this->Database = new Connection($config['mysql']);
        } catch (\Exception $e) {
            $error['response'] = $e->getMessage();
            echo json_encode($error);
        }
    }

	 function errorReturn($params) {
		switch ($params['error_return']) {
			default : echo json_encode(array('done' => 0, 'response' => $this->error)); break;
			case 'string' : $this->error; break;
			case 'array' : return array('done' => 0, 'response' => $this->error); break;
			case 'true' :
			case '1' :
			case 'return' :
				echo "OKOKO";
				 return $this->error; break;
		}
	 }

	 function returnData($data,$return='json') {
		if (!is_array($data)) {
			$data[] = $data;
		}

		switch ($return) {
			default : echo json_encode($data); break;
			case 'string' : return json_encode($data); break;
			case 'array' : 
			case true :
			case 'true' :
			case 1 :
			case '1' :
			case 'return' : return $data; break;
		}
	 }

	 /**
	  * Get Data from Table
	  * @params	Array()
	  * @return	Array() | JSON | String
	  * Get Data from Table: where single field
	  * Form::get(['field' => 'id', 'data' => '1', 'table' => 'table_name']);
	  * Get Data from Table: where multiple field (using AND operation)
	  * Form::get(['table' => 'table_name', 
	  	'data' => [
	 		'id' => 1,
			'name' => 'stieven'
		]
	  	]);
	  */
    public function get($params) {
		//$id,$table,$fld='id'
		$params['return'] = isset($params['return']) ? $params['return'] : 'json';
		$params['error_return'] = isset($params['error_return']) ? $params['error_return'] : 'json';
		$params['field'] = isset($params['field']) ? $params['field'] : 'id';
		try {
			if (is_array($params['data'])) {
					$f = array();
					foreach($params['data'] as $k => $v) {
						$f[]="`$k` = '". $this->Database->escape_string($v) ."'";
					}
					if (!$g = $this->Database->query("select * from `{$params['table']}` where ". implode(" and ",$f) ."")) {
						throw new \Exception($this->Database->error, 1);
					}
			} else {
					if ($params['data'] == 'last') {
						$g = $this->Database->query("select * from `{$params['table']}` order by `{$params['field']}` desc limit 1");
					} else {
						if (!isset($params['data']) or !isset($params['field'])) {
							throw new \Exception("Field and Value can not blank ".__CLASS__."::->get(['field' => 'id', 'data' => '1', 'table' => 'table_name'])", 1);
						}
						if (!$g = $this->Database->query("select * from `{$params['table']}` where `{$params['field']}`='". $this->Database->escape_string($params['data']) ."'")) {
							throw new \Exception($this->Database->error, 1);
						}
					}
			}
			return $this->Database->fetch_assoc($g);
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			$this->errorReturn($params);
		}
	}

    public function save($data,$table,$method='INSERT',$primary='id') {
        $error = array('done' => 0, 'response' => '');
		  $field = [];
			$g = $this->Database->query("select * from `{$table}` limit 1");
			$r = $this->Database->fetch_fields($g);
			for ($i = 0; $i < count($r); $i++) {
					array_push($field,$r[$i]->name);
			}
			foreach ($data as $k => $v) {
					if (!in_array($k,$field)) { unset($data[$k]); }
			}
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
                    if ($new == 0 or $method == 'UPDATE') { 
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
            $this->error = $e->getMessage();
        }
	}

    function delete($params,$table) {
		if ($table == 'query') {
			if ($this->Database->query($params)) {
				return true;
			} else {
				$this->error = $this->Database->error(); 
				return false;
			}
		} else {
			try {
				if (is_array($params)) {
					$key = array_keys($params);
					$field = $key[0];
					$id = $this->escape_string($params[$field]);
					$data = $this->get(['data' =>$id, 'table' => $table, 'field' => $field]);
					if ($this->Database->query("DELETE FROM `{$table}` where `$field`='{$id}'")) {
						return true;
					} else {
						throw new \Exception($this->Database->error(), 1);
						return false;
					}
				} else { throw new \Exception("Invalid Arguments", 1); }
			} catch (\Exception $e) {
				$this->error = $e->getMessage();
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
				$this->error = $e->getMessage();
        }
    }
    
    public function select($query) { return $this->query($query); }

    public function fetch($object,$tipe = 'assoc') {
        try {
            if ($tipe == 'assoc') {
                $o = $this->Database->fetch_assoc($object);
            } else {
                $o = $this->Database->fetch_row($object);
            }
            return $o;
        } catch (\Exception $e) {
				$this->error = $e->getMessage();
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
					$f[]="`{$k}` = '". $this->escape_string(trim($v)) ."'";
				}
			}
		}
		if (count($f) > 0) {
			$s = implode(", ",$f);
			return array('id' => $id,'field' => $s);
		} else { return false; }
	}

	function escape_string($data) {
		return $this->Database->escape_string($data);
	}

	function escstr($data) {
		return $this->Database->escape_string($data);
	}

	public function serverURL() {
        /* Thanks to phpBB for this Script */
		// We have to generate a full HTTP/1.1 header here since we can't guarantee to have any of the information
		// available as used by the redirect function
		$server_name = (!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : getenv('SERVER_NAME');
		$server_port = (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');
		$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 1 : 0;
		$script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
		if (!$script_name)
		{
			$script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
		}
	
		// Replace any number of consecutive backslashes and/or slashes with a single slash
		// (could happen on some proxy setups and/or Windows servers)
		$script_path = trim(dirname($script_name)) . '/';
		$script_path = preg_replace('#[\\\\/]{2,}#', '/', $script_path);
		if ($n == 1) {
			$url = $server_name;
		} else {
			$url = (($secure) ? 'https://' : 'http://') . $server_name;
		}
	
		if ($server_port && (($secure && $server_port <> 443) || (!$secure && $server_port <> 80)))
		{
			$url .= ':' . $server_port;
		}
	
		$url .= $script_path;
		$SERVER_URL = $url;
		unset($url);
		return $SERVER_URL;
		/* End of phpBB Script */
    }

	function numberShort($num,$lan = 'ID') {
		if ($num >= 1000000000000000000) { return round($num / 1000000000000000,2). ($lan == 'ID' ? 'Ki' : 'Qi'); }
		else if ($num >= 1000000000000000) { return round($num / 1000000000000000,2). ($lan == 'ID' ? 'K' : 'Q'); }
		else if ($num >= 1000000000000) { return round($num / 1000000000000,2). ($lan == 'ID' ? 'T' : 'T'); }
		else if ($num >= 1000000000) { return round($num / 1000000000,2).($lan == 'ID' ? 'M' : 'B'); }
		else if ($num >= 1000000) { return round($num / 1000000,2).($lan == 'ID' ? 'Jt' : 'M'); }
		else if ($num >= 1000) { return round($num / 1000,2).($lan == 'ID' ? 'Rb' : 'K'); }
		else return $num;
	}

	public function normalize_str($str,$remove_space = 0) {
		if (trim($str)) {
			$str = strip_tags($str);
			$str = str_replace(array("'",'"'),"",$str);
			$str = preg_replace("#style=\".+?\"#si","",$str);
			$str = trim($str);
		} 

		if ($remove_space == 1 or $remove_space == true) {
			$str = str_replace(" ","",$str);
		}
		return $str;
	}

	function table_attrib($data, $prefix = '',$quote='"') {
		if (is_array($data)) {
			foreach($data as $k => $v) {
				if ($quote == '"') {
					$att[] = "{$prefix}{$k}=\"{$v}\"";
				} else {
					$att[] = "{$prefix}{$k}='{$v}'";
				}
			}
			$attrib=implode(" ",$att);
			return $attrib;
		} else {
			return '';
		}
	}

	function geoDistance($lat1,$lon1,$lat2,$lon2) {
		$R = 6371; // Radius of the earth in km
		$dLat = deg2rad($lat2-$lat1);  // deg2rad below
		$dLon = deg2rad($lon2-$lon1); 
		$a = 
		  sin($dLat/2) * sin($dLat/2) +
		  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
		  sin($dLon/2) * sin($dLon/2)
		  ; 
		$c = 2 * atan2(sqrt($a), sqrt(1-$a)); 
		$d = $R * $c; // Distance in km
		return $d;
	}
	  
	function timeShort($u) {
		$c = strtotime($u);
		$n = date("U");
		if ($n > $c) { $time = ($n - $c); }
		else { $time = ($c - $n); }
		return $this->timeToShort($time);
	}
	
	function timeToShort($time) {
		if ($time > (3600 * 24)) { $d = ceil($time / (3600 * 24)) ." hari"; }
		else if ($time > (3600)) { $d = ceil($time / 3600) ." jam"; }
		else if ($time > (60)) { $d = ceil($time / 60) ." menit"; }
		else if ($time > 15) { $d = $time ." detik"; }
		else { $d = $time.' detik'; }
		return $d;
	}

	function SecTimeStamp($time) {
		$hari = floor($time / 3600 / 24);
		if ($hari > 0) {
			$h = "{$hari}d ";
		}
		$time = $time - (3600 * 24 * $hari);
		$jam = floor($time / 3600);
		$time = $time - (3600 * $jam);
		$menit = floor($time / 60);
		$time = $time - (60 * $menit);

		return "{$h}". str_pad($jam,2,'0',STR_PAD_LEFT).":".str_pad($menit,2,'0',STR_PAD_LEFT).":".str_pad($time,2,'0',STR_PAD_LEFT)."";
	}

	function umur($tgl) {
		$tgl = date("Y-m-d",strtotime($tgl));
		list($y,$m,$d)=explode("-",$tgl);
		$umur = date("Y") - $y;
		if ($m > date("m")) { $umur = $umur - 1; }
		if ($m == date("m") and $d > date("d")) { $umur = $umur - 1; }
		return $umur;
	}

    public function cekKTP($nik,$tanggal_lahir){
		if(strlen($nik) != 16){
		  return false;
		}
		$d = substr($nik, 6, 2);
		$m = substr($nik, 8, 2);
		$y = substr($nik, 10, 2);
		
		$tahun = date("y",strtotime($tanggal_lahir));
		$bulan = date("m",strtotime($tanggal_lahir));
		$tanggal = date("d",strtotime($tanggal_lahir));
		//jika tahun full, ambil 2 digit terakhir
		if(strlen($tahun) ==4){
		  $tahun = substr($tahun,2,2);
		}
		if ((int) $d > 40) {
		  //Wanita
		  $d = (int) $d - 40; 
		}
		if((int) $tanggal / (int) $d != 1){
			
		  return false;
		}
		
		if((int) $bulan / (int) $m != 1){
			
		  return false;
		}
		
		if((int) $tahun / (int) $y != 1){
			
		  return false;
		}
		
		return true;
	  }

	  function QRcode($data,$base64 = true, $filename='') {
		$options = new QROptions;
		// $options->version      = 7;
		$options->outputBase64 = $base64;
		if ($filename) { 
			$options->outputBase64 = false;
			$options->cachefile = $filename;
			$qrcode = (new QRCode($options))->render($data);
		} else {
			$qrcode = (new QRCode($options))->render($data);
			return $qrcode;
		}
	  }

	  function QRcodeRead($file) {
		try{
			$result = (new QRCode)->readFromFile($file); // -> DecoderResult
		
			// you can now use the result instance...
			$content = $result->data;
		
			// ...or simply cast the result instance to string to get the content
			// $content = (string)$result;
			return $content;
		}
		catch(Throwable $exception){
			// handle exception...
		}
	  }

	  function G2FA_genQRcode($company,$user) {
			$google2fa = new \PragmaRX\Google2FA\Google2FA();
			$secret = $google2fa->generateSecretKey();
			$g2faUrl = $google2fa->getQRCodeUrl(
			    $company,
			    $user,
			    $secret
			);
			$QRcode = $this->QRcode($g2faUrl);
			$r =  array(
				'secret' => $secret,
				'url' => $g2faUrl,
				'qrcode' => $QRcode
			);
			return $r;
	  }

	  function G2FA_getCurrentOTP($secret) {
		$google2fa = new \PragmaRX\Google2FA\Google2FA();
		$currentOTP = $google2fa->getCurrentOtp($secret);
		return $currentOTP;
	  }

    function __destruct() {

    }
}
?>
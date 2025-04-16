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
			case 'return' : return $this->error; break;
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
			case 'true' :
			case '1' :
			case 'return' : return $data; break;
		}
	 }

	 /**
	  * Get Data from Table
	  * @params	Array()
	  * @return	Array() | JSON | String
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
					if (!isset($params['data']) or !isset($params['field'])) {
						throw new \Exception("Field and Value can not blank ".__CLASS__."::->get(['field' => 'id', 'data' => '1', 'table' => 'table_name'])", 1);
					}
					if (!$g = $this->Database->query("select * from `{$params['table']}` where `{$params['field']}`='". $this->Database->escape_string($params['data']) ."'")) {
						throw new \Exception($this->Database->error, 1);
					}

			}
			$this->returnData($this->Database->fetch_assoc($g),1);
			
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
            $error['response'] = $e->getMessage();
            // echo json_encode($error);
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
                // echo json_encode(array('response' => $this->error, 'done' => 0));
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
            // echo json_encode(array('done' => 0, 'response' => $e->getMessage())); exit;
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
            // echo json_encode(array('done' => 0, 'response' => $e->getMessage())); exit;
        }
    }

	 public function table_exists($table) {
		$g = $this->query("show tables like '{$table}'");
		$t = $this->fetch($g,'row');
		return $t[0] > 0 ? true : false;
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

	/** 
	 * Alias for old version
	*/
	public function form_select_array($array,$val='') {
		$this->form_select($array,$val);
	}

	
	/**
	 * Generate OPTION list for SELECT form
	 * filename or string pipe or array object
	 * 
	 * * $array String filename : kategori.txt
	 * * $array String Pipe : 'FATHER|MOTHER|SON|DAUGTHER|GRANDMA|GRANDPA'
	 * * $array Object : ['MAN','WOMAN','BOY','GIRL']
	 * 
	 * @param string|array	$array	Option name and value
	 * @param mixed	$val	default value for selected
	 */
	public function form_select($array,$val='') {
		if (in_array($array,array("MONTH","YEAR"))) {
			$this->option_list($array,$val);
		} else {
			if (is_array($array)) {
				$arrays = $array;
			} else if (is_file($array)){
				if (file_exists($array)) {
					$data = file_get_contents($array);
					$arrays = explode("\n",$data);
				}
			} else {
				$arrays = explode("|",$array);
			}
			if (array_is_list($arrays)) {
				foreach($arrays as $v) {
					// $v = trim($v);
					// $val = trim($v);
					if (is_array($v)) {
						// print_r($v);
						echo '<option value="'.trim($v['id']).'" '. (trim($v['id']) == trim($val) ? 'selected="selected"' : '') .'>'.$v['name'].'</option>';
					} else {
						echo '<option value="'. trim($v).'" '. (trim($v) == trim($val) ? 'selected="selected"' : '') .'>'.$v.'</option>';
					}
				}
			} else {
				foreach($arrays as $k => $v) {
					$val = trim($val);
					echo '<option value="'.trim($v).'" '. (trim($v) == trim($val) ? 'selected="selected"' : '') .'>'.str_replace("_"," ",$k).'</option>';
				}
			}
		}
	}

	/** 
	 * Alias for version 8.1.2
	*/
	public function form_option($array,$val='') {
		$this->form_select($array,$val);
	}

	/** 
	 * Alias for version 8.1.2
	*/
	function form_option_query($table,$fld,$default_value='',$orderby='') {
		$this->select_option_list($table,$fld,$default_value,$orderby);
	}

	function select_query($table,$fld,$default_value='',$orderby='') {
		$this->select_option_list($table,$fld,$default_value,$orderby);
	}
	public function form_option_list($table,$fld,$default_value='',$orderby='') {
		$this->select_option_list($table,$fld,$default_value,$orderby);
	}

	public function select_option_list($table,$fld,$default_value='',$orderby='', $attribute = true) {
		if (is_array($fld)) {
			$x = explode("|",$fld[1]);
			if (count($x) > 1) {
				$cc = ", concat_ws(' ',".implode(",",$x).") as name";
				$fld[1] = 'name';
			}
			if (preg_match("#^select#si",$table)) {
				$SQL = $table;
			} else {
				$SQL = "select * {$cc} from {$table} {$orderby}";
			}
			$g = $this->Database->query($SQL);
			while($t = $this->Database->fetch_assoc($g)) {
				$attrib = array();
				if ($attribute == true) {
					foreach($t as $k => $v) {
						$attrib[] = 'data-'.$k.'="'. addslashes(trim($v)).'"';
					}
				}
				echo '<option '. implode(" ",$attrib) .' value="'.trim($t[$fld[0]]).'" '. (trim($t[$fld[0]]) == $default_value ? 'selected="selected"' : '') .'>'.trim($t[$fld[1]]).'</option>';
			}
		} else { echo "Invalid type"; }
	}

    public function ignoreParams($data,$chars='^userid$|^lat$|^lng$|^username$|^token|^app_') {
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
	
	public function stripHTML($m) {
			
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

    function save_dbx($file,$dir='',$removeFile=true) {
		global $config;
        $data = array('done' => 0, 'response' => '');
        $HOME_DIR = $this->config['dropbox']['home_dir'].$dir;
		  $HOME_DIR = $HOME_DIR ? $HOME_DIR.'/' : '';
        try {
            if (!$this->config['dropbox']['access_token']) {
                throw new \Exception("Dropbox Access Token not set", 1);
            }
            if (!file_exists($file)) {
                throw new \Exception("File ".basename($file)." not found", 1);
            }
            $DBX = new Dropbox($this->config['dropbox']['access_token']); 
            $DBX->upload($file,'overwrite','/'.$HOME_DIR);
				$d = $DBX->create_shared_link($HOME_DIR.basename($file));
            if ($d['url']) {
				$url = str_replace("dl=0","raw=1",$d['url']);
				if ($removeFile == true) { if (file_exists($file)) { unlink($file); } }
				return $url;
			} else {
				if (preg_match("#already_exists#si",$d['error_summary'])) {
					$d = $DBX->get_shared_link($HOME_DIR.basename($file));
					$url = str_replace("dl=0","raw=1",$d['links'][0]['url']);
					if ($removeFile == true) { if (file_exists($file)) { unlink($file); } }
					return $url;
				}
            else { 
					throw new \Exception("Dropbox Error, file can not upload ".json_encode($d), 1);
                	return false; 
				}
         }
        } catch(\Exception $e) {
            $this->error = $e->getMessage();
            $data['response'] = $this->error;
            return false;
        }
	}

    function delete_dbx($url,$dir = '',$replace_dir='') {
		global $config;
		$HOME_DIR = $this->config['dropbox']['home_dir'].$dir;
		$HOME_DIR = $HOME_DIR ? $HOME_DIR.'/' : '';
		if ($replace_dir != '') { $HOME_DIR = $replace_dir; }
		if ($this->config['dropbox']['access_token']) {
			$DBX = new Dropbox($this->config['dropbox']['access_token']);
			$d = $DBX->get_shared_link_file($url);
			if ($d['name']) { 
				$x = $DBX->delete($HOME_DIR.$d['name']); 
				// $path = dirname($d['path_lower']);
				// $path = strlen($path) <= 1 ? '' : $path;
				// if ($d['path_lower']) {
				// 	$x = $DBX->delete($path.$d['name']); 
				// } else {
				// 	$x = $DBX->delete($this->config['dropbox']['home_dir'].$dir.'/'.$d['name']); 
				// }
				// $x['hh'] = $d['path_lower'];
				// $x['path'] = $path.$d['name'];
			}
			// file_put_contents($this->config['HOME_DIR'] . 'cache/dbx.txt',json_encode($d).json_encode($xx));
			return $x;
		} else { return false; }
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

	function resize($file,$w=900, $h=900,$newfile='',$reso=80) {
		if (file_exists($file)) {
			$img=getimagesize($file);
			list($origin_width,$origin_height)=$img;
			$orien = $origin_width > $origin_height ? 'L' : 'P';
			$attribut = array('width' => $origin_width, 'height' => $origin_height, 'orientasi' => $orien);
			// Resize 
			$w = $origin_width < $w ? $origin_width : $w;
			$h = $origin_height < $h ? $origin_height : $h;
			$resizeW = $w;
			$resizeH = $h;
			
			if ($origin_width > $w) {
				$origin_height=round(($w/$origin_width)*$origin_height,0);
				$origin_width=$w;
			}
			if ($origin_height > $h) {
					$origin_width=round(($h/$origin_height)*$origin_width,0);
					$origin_height=$h;
			}
			//$newfile = $newfile != '' ? $newfile : dirname($file).DIRECTORY_SEPARATOR.basename($newfile);
			$img_src=imagecreatetruecolor($origin_width,$origin_height);
			$white = imagecolorallocate($img_src,255,255,255);
			imagefill($img_src,0,0,$white);
			$des_src=@imagecreatefromjpeg($file);
			
			if ($this->Watermark and file_exists($this->Watermark)) {
			/**/
				$img_logo=getimagesize($this->Watermark);
				if (preg_match("#png#si",$img_logo['mime'])) {
					$logo = imagecreatefrompng($this->Watermark);
				} else {
					$logo = imagecreatefromjpeg($this->Watermark);
				}
				list($logo_width,$logo_height)=$img_logo;
			/**/
			}
			
			@imagecopyresampled($img_src,$des_src,0,0,0,0,$origin_width,$origin_height,$attribut['width'],$attribut['height']); 
			
			if ($this->Watermark and file_exists($this->Watermark)) {
				//echo $this->Watermark_size; exit;
			/**/
			switch ($this->Watermark_pos) {
				default : 
				case 'top-left' :
					$lw = 3; $lh = 3; 
				break;	
				case 'center' :
					$lw = ($origin_width - ($logo_width * $this->Watermark_size)) / 2;
					$lh = (($origin_height - ($logo_height * $this->Watermark_size)) / 2); // + ($origin_height * $this->Watermark_size);
				break;
				case 'top-right' :
					$lw = $origin_width - ($logo_width * $this->Watermark_size) - 3;
					$lh = 3;
				break;
				case 'bottom-right' :
					$lw = $origin_width - ($logo_width * $this->Watermark_size) - 3;
					$lh = $lh = (($origin_height - ($logo_height * $this->Watermark_size)) - 3);
				break;
				case 'bottom-left' :
					$lw = 3;
					$lh = $lh = (($origin_height - ($logo_height * $this->Watermark_size)) - 3);
				break;
			}
			
			imagecopyresampled($img_src,$logo,$lw,$lh,0,0,($logo_width * $this->Watermark_size),($logo_height * $this->Watermark_size),$logo_width,$logo_height); 
			/**/
			}
			
			$newfile = $newfile != '' ? $newfile : $file;
			@imagejpeg($img_src,$newfile,$reso);
		}
	}

	function imgWatermark($FILESRC,$watermark,$pos = 'center') {
		
		if (file_exists($FILESRC)) {
			$img=getimagesize($FILESRC);
			list($imagewidth,$imageheight)=$img;
			$ow = $imagewidth;
			$oh = $imageheight;
			$img_src = imagecreatetruecolor($imagewidth,$imageheight);
			$black = imagecolorallocate($img_src, 0, 0, 0);
			$white = imagecolorallocate($img_src, 255, 255, 255);
			$des_src = imagecreatefromjpeg($FILESRC);
			imagecopyresampled($img_src,$des_src,0,0,0,0,$imagewidth,$imageheight,$imagewidth,$imageheight);
			if ($watermark and file_exists($watermark)) {
				list($wt_w,$wt_h)=getimagesize($watermark);
				$wt_src = imagecreatefrompng($watermark);
				imagealphablending( $wt_src, false );
				imagesavealpha( $wt_src, true );
				$wt_ww = $wt_w;
				$wt_hh = $wt_h;
				if ($wt_w > $ow) {
					$wt_ww = $ow / $wt_w * $wt_w;
					$wt_hh = $ow / $wt_w * $wt_h; 
				}
				
				switch ($pos) {
					default : 
					case 'center' :
						$wt_y = round(($oh - $wt_hh) / 2);
						$wt_x = round(($ow - $wt_ww) / 2);
					break;
					case 'top-left' :
						$wt_x = 5; $wt_y = 5; 
					break;	
					
					case 'top-right' :
						$wt_x = $ow - $wt_ww - 5;
						$wt_y = 5;
					break;
					case 'bottom-right' :
						$wt_x = $ow - $wt_ww - 5;
						$wt_y = $oh - $wt_hh - 5;
					break;
					case 'bottom-left' :
						$wt_x = 5;
						$wt_y = $oh - $wt_hh - 5;
					break;
				}
				imagecopyresampled($img_src,$wt_src,$wt_x,$wt_y,0,0,$wt_ww,$wt_hh,$wt_w,$wt_h);
				imagejpeg($img_src,$FILESRC,100);
				imagedestroy($img_src);
				imagedestroy($des_src);
				imagedestroy($wt_src);
			}
		}
	}

	function imgText($FILESRC,$text, $extraHeight = 0.20, $extraWidth = 0.02) {
		$teks = explode("\n",$text);
		if (file_exists($FILESRC)) {
			$img=getimagesize($FILESRC);
			list($imagewidth,$imageheight)=$img;
			$ow = $imagewidth;
			$oh = $imageheight;
			$ee = ($imageheight * $extraHeight);
			$ww = $imagewidth + ($imagewidth * $extraWidth);
			$hh = $imageheight + ($ee < 150 ? 150 : $ee);
			$x = ($ww - $ow) / 2;
			$y = ($ww - $ow) / 2;
			$img_src = imagecreatetruecolor($ww,$hh);
			$black = imagecolorallocate($img_src, 0, 0, 0);
			$white = imagecolorallocate($img_src, 255, 255, 255);
			imagefill($img_src,0,0,$black);
			$des_src = imagecreatefromjpeg($FILESRC);
			imagecopyresampled($img_src,$des_src,$x,$y,0,0,$imagewidth,$imageheight,$ow,$oh);
			
			for($i = 0; $i < count($teks); $i++) {
				if (trim($teks[$i])) {
					imagestring($img_src, 5, $x, $oh + $y + 5 + ($i * 20), trim($teks[$i]), $white);
				}
			}
			imagejpeg($img_src,$FILESRC,100);
			imagedestroy($img_src);
			imagedestroy($des_src);
			return true;
		}
	}

	function imgThumbs($FILESRC,$THUMBS='',$w=100,$h=0,$ratio=true,$color='#FFFFFF') {
		if ($THUMBS == '') { $THUMBS=$FILESRC; }
        if (file_exists($FILESRC)) {
                $img=@getimagesize($FILESRC);
				list($imagewidth,$imageheight)=$img;
				//$w = $imagewidth < $w ? $imagewidth : $w;
				//$h = $imageheight < $h ? $imageheight : $h;
				switch($img['mime']) {
					default : $ext = ''; break;
					case 'image/jpg' :
					case 'image/jpeg' : $ext = 'jpg'; break;
					case 'image/png' : $ext = 'png'; break;
					case 'image/gif' : $ext = 'gif'; break;
				}

                $ow=$imagewidth;
                $oh=$imageheight;
				$twidth = $w;
				$theight = $h;
                if ($imagewidth > $w) {
                        $imageheight=round(($w/$imagewidth)*$imageheight,0);
                        $imagewidth=$w;
                }
				if ($h > 0) {
					if ($imageheight >= $h) {
							$imagewidth=round(($h/$imageheight)*$imagewidth,0);
							$imageheight=$h;
					}
				}
                $cc=floor(($h-$imageheight)/2);
                $ch=floor(($w-$imagewidth)/2);
				
                if ($ratio === true) {  
					 $img_src=@imagecreatetruecolor($imagewidth,$imageheight);
               		 if ($ext == 'png') { $des_src=@imagecreatefrompng($FILESRC); } 
					 else { 
					 	$des_src=@imagecreatefromjpeg($FILESRC); 
					 }
					 @imagecopyresampled($img_src,$des_src,0,0,0,0,$imagewidth,$imageheight,$ow,$oh);
				}
				else if ($ratio == 'box') {
					 $img_src=imagecreatetruecolor($w,$h);
					 $warna = $this->html2rgb($color);
					 $white = imagecolorallocate($img_src,$warna[0],$warna[1],$warna[2]);
					 imagefill($img_src,0,0,$white);
               		 if ($ext == 'png') { $des_src=@imagecreatefrompng($FILESRC); } 
					 else { $des_src=imagecreatefromjpeg($FILESRC); }
					 $wss = round(($w - $imagewidth) / 2);
					 $hss = round(($h - $imageheight) / 2);
					 imagecopyresampled($img_src,$des_src,$wss,$hss,0,0,$imagewidth,$imageheight,$ow,$oh);
				}
				else {
					$img_src=@imagecreatetruecolor($twidth,$theight);
               		if ($ext == 'png') { $des_src=@imagecreatefrompng($FILESRC); }
					else { $des_src=@imagecreatefromjpeg($FILESRC); }
					$sx=round(($ow-$twidth)/4);
					$sy=round(($oh-$theight)/4);
					@imagecopyresampled($img_src,$des_src,0,0,$sx,$sy,$twidth+$sx,$theight+$sy,$ow,$oh);
					
				}
                if ($ext == 'png') {
					$black = imagecolorallocate($img_src, 0, 0, 0);
					imagecolortransparent($img_src, $black);
					//imagealphablending($img_src, false);
					//imagesavealpha($img_src, true);
					@imagepng($img_src,$THUMBS,9,PNG_ALL_FILTERS);
				} else { 
					@imagejpeg($img_src,$THUMBS,80);
					//@imagejpeg($print_src,dirname($THUMBS).'/print-'.basename($THUMBS),100);
				}
                @imagedestroy($img_src);
				@imagedestroy($des_src);
        }
	}
	
	function html2rgb($color)
	{
		if ($color[0] == '#')
			$color = substr($color, 1);
	
		if (strlen($color) == 6)
			list($r, $g, $b) = array($color[0].$color[1],
									 $color[2].$color[3],
									 $color[4].$color[5]);
		elseif (strlen($color) == 3)
			list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
		else
			return false;
	
		$r = hexdec($r); $g = hexdec($g); $b = hexdec($b);
	
		return array($r, $g, $b);
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

	  
	  /** 
	   * Generate list <option></option> for <select></select>
	   * @param	mixed	$tipe	Tipe option: year|month
	   * @param string	$default	Default value for selected option
	  */
	  function option_list($tipe,$default='',$yearstart='') {
		$tipe = strtolower($tipe);
		$year = date("Y");
		switch($tipe) {
			case 'bulan' :
			case 'month' :
				for($i = 1; $i <= 12; $i++) {
					echo '<option value="'.$i.'" '. ($i == $default ? 'selected' : '') .'>'. date("F",mktime(0,0,0,$i,1,$year)) .'</option>';
				}
			break;
			case 'year' :
			case 'tahun' :
				$yearstart = $yearstart ? $yearstart : 2020;
				for($i = $yearstart ; $i<=$year+2; $i++) {
					echo '<option value="'.$i.'" '. ($i == $default ? 'selected' : '') .'>'. $i .'</option>';
				}
			break;
		}
	}

	public function pagination_bs5($page,$ap,$link) {
		echo '<nav>
		<div class="d-flex justify-content-between gap-1">
		<ul class="pagination justify-content-start">
		<li class="page-item">
			<a class="page-link" href="'.$link.'&p='. ($page - 1) .'" aria-label="Previous">
				<span aria-hidden="true">&laquo;</span>
			</a>
			</li>
		</ul>
		<ul class="pagination justify-content-between">';

			if ($ap <= 9) {
				for ($i = 1; $i <= $ap; $i++) {
					echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="'.$link.'&p='. $i .'">'.$i.'</a></li>';
				}
			} else {
				
				for ($i = 1; $i < 3; $i++) {
					echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="'.$link.'&p='. $i .'">'.$i.'</a></li>';
				}
				if ($page+1 > 2 and $page < $ap) {
						$p = $page <= 2 ? $page+1 : $page;
						$ep = ($page+2);
						$ep = $ep > $ap - 1 ? $ap - 1 : $ep;
						if ($page > 3) {
							echo '<li class="page-item"><a class="page-link">..</a></li>';
						}
						for ($i = $p; $i < $ep; $i++) {
							echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="'.$link.'&p='. $i .'">'.$i.'</a></li>';
						}
						if ($page < $ap-2) {
							echo '<li class="page-item"><a class="page-link">..</a></li>';
						}
				} else {
					echo '<li class="page-item"><a class="page-link">..</a></li>';
				}
				// if ($ap >= 7) {
				// 	if ($page >= 3) {
				// 		for ($i = $page; $i <= $ap-3; $i++) {
				// 			echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="'.$link.'&p='. ($i) .'">'. ($i).'</a></li>';
				// 		}
				// 	}
				// 	echo '<li class="page-item"><a class="page-link">...</a></li>';
				// }
				for ($i = $ap-1; $i <= $ap; $i++) {
					echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="'.$link.'&p='. $i .'">'.$i.'</a></li>';
				}
			}
			
			echo '
		</ul>
		<ul class="pagination"><li class="page-item">
			<a class="page-link" href="'.$link.'&p='. ($page + 1) .'" aria-label="Next">
				<span aria-hidden="true">&raquo;</span>
			</a>
			</li></ul>
		</div>
		</nav>';
	}

	public function getServerInfo($url,$Headers,$params,$return = 'json') {
		$option = array(
			'curl' => array( CURLOPT_SSL_VERIFYPEER => false),
			'verify' => false
		);
		$client = new \GuzzleHttp\Client($option);
		$res = $client->request('POST',$url,array(
            'headers' => array(
               'app_id' => $Headers['app_id'],
					'Authorization' => $Headers['app_key'], // base64
					'app_version' => $Headers['app_version'],
					// 'app_secret' => $Headers['app_secret'],
               'content-type' => 'application/x-www-form-urlencoded'),
            'form_params' => $params
        ));
        $result = $res->getBody();
		// echo $result;
		if ($return == 'json') {
			return json_decode($result,true);
		} else { return $result; }
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

	function numberShort($num,$lan = 'ID') {
		if ($num >= 1000000000000000000) { return round($num / 1000000000000000,2). ($lan == 'ID' ? 'Ki' : 'Qi'); }
		else if ($num >= 1000000000000000) { return round($num / 1000000000000000,2). ($lan == 'ID' ? 'K' : 'Q'); }
		else if ($num >= 1000000000000) { return round($num / 1000000000000,2). ($lan == 'ID' ? 'T' : 'T'); }
		else if ($num >= 1000000000) { return round($num / 1000000000,2).($lan == 'ID' ? 'M' : 'B'); }
		else if ($num >= 1000000) { return round($num / 1000000,2).($lan == 'ID' ? 'Jt' : 'M'); }
		else if ($num >= 1000) { return round($num / 1000,2).($lan == 'ID' ? 'Rb' : 'K'); }
		else return $num;
	}

	function GetDirectorySize($path){
		$bytestotal = 0;
		$path = realpath($path);
		if($path!==false && $path!='' && file_exists($path)){
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object){
				$bytestotal += $object->getSize();
			}
		}
		//
		return $bytestotal;

	}

	function array_filter_recursive(array $array) {
		foreach ($array as &$value) {
			if (is_array($value)) {
					$value = $this->array_filter_recursive($value);
			}
		}

		return array_filter($array, function($value) {
			return !is_null($value);
		});
	}

    function __destruct() {

    }
}
?>
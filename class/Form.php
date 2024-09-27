<?php
namespace Koyabu\Webapi;
use Koyabu\Webapi;
use chillerlan\QRCode\{QRCode, QROptions};
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
                throw new \Exception("Mysql config error:". json_encode($config['mysql']), 1);
                
            }
        } catch (\Exception $e) {
            $error['response'] = $e->getMessage();
            echo json_encode($error);
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
            echo json_encode(array('done' => 0, 'response' => $this->error));
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
				$val = trim($val);
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

	public function select_option_list($table,$fld,$default_value='',$orderby='') {
		if (is_array($fld)) {
			$x = explode("|",$fld[1]);
			if (count($x) > 1) {
				$cc = ", concat_ws(' ',".implode(",",$x).") as name";
				$fld[1] = 'name';
			}
			$SQL = "select * {$cc} from {$table} {$orderby}";
			$g = $this->Database->query($SQL);
			while($t = $this->Database->fetch_assoc($g)) {
				echo '<option value="'.trim($t[$fld[0]]).'" '. (trim($t[$fld[0]]) == $default_value ? 'selected="selected"' : '') .'>'.trim($t[$fld[1]]).'</option>';
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
				if ($removeFile == true) { if (file_exists($file)) { unlink($file); } }
				return $url;
			} else {
				if (preg_match("#already_exists#si",$d['error_summary'])) {
					$d = $DBX->get_shared_link($HOME_DIR.basename($file));
					// echo $HOME_DIR;
					// echo '<pre>'; print_r($d); echo '</pre>';
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
		$HOME_DIR = $config['dropbox']['home_dir'].$dir;
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

	  function QRcode($data,$base64 = true) {
		$options = new QROptions;
		// $options->version      = 7;
		$options->outputBase64 = $base64;
		$qrcode = (new QRCode($options))->render($data);
		return $qrcode;
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
	  function option_list($tipe,$default='') {
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
				for($i = 2020; $i<=$year+2; $i++) {
					echo '<option value="'.$i.'" '. ($i == $default ? 'selected' : '') .'>'. $i .'</option>';
				}
			break;
		}
	  }

    function __destruct() {

    }
}
?>
<?php
namespace Koyabu\Webapi;
use Exception;
use Koyabu\Webapi;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\Output\QROutputInterface;
/** 
 * Koyabu Framework
 * version: 8.2.2
 * last update: 14 April 2026
 * min-require: PHP 8.3+ 
 * MariaDB: 10+ (recommended) or MySQL : 8+
 * Author: stieven.kalengkian@gmail.com
*/
class Form {
    public $Version = '8.2.2';
    public $Database;
    public $config;
    public $error;
	public $METHOD, $USER;

	public $debugPathFile = 'cache/debug.log';
	public $debugSaveToFile = false;
	public $debugShow = false;
	public $sanitize = false;

    function __construct($config) {
        $this->config = $config;
        $this->SQLConnection($config);
		$this->METHOD = $_SERVER['REQUEST_METHOD'];
    }

    public function SQLConnection($config) {
        try {
            if (empty($config['mysql'] ?? $config['database'])) {
					throw new \Exception("Database config error: no config found", 1);
            }
			$dbs = $config['mysql'] ?? $config['database'];
			switch ($dbs['driver']) {
				default :
				case 'mysql' :
				case 'mysqli':
					$this->Database =  new Connection($dbs); break;
				case 'pdo':
					$this->Database = new ConnectionPDO($dbs); break;
				case 'odbc':
					$this->Database = new ConnectionODBC($dbs); break;
			}
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
				 return $this->error; break;
		}
	 }

	 function returnData($data,$return='json', $exit = true) {
		$data = isset($data) ? $data : ['done' => 0, 'response' => '', 'code' => 404];
			switch($return) {
				default : 
				case 'string' : $d = json_encode($data); break;
				case 'array' : $d = $data;
			}
		if ($exit === true) {
			if (ob_get_length()) ob_end_clean();
			echo $d; exit;
		} else {
			return $d;
		}
	 }

	 public function sel($params,$table) {
			return $this->get(['data' => $params, 'table' => $table]);
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

	public function saveTable($params) {
		try {
			if (!$params['data'] or !is_array($params['data'])) {
				throw new \Exception("Invalid Parameters", 1);
			}
			if (!isset($params['table'])) {
				throw new \Exception("Table not defined", 1);
			}
			$ID = false;
			$fields = [];
			$table = $params['table'];
			$method = isset($params['method']) ? strtoupper($params['method']) : 'INSERT';
			$primary = isset($params['primary']) ? $params['primary'] : 'id';
			$data = $params['data'];
			$this->sanitize = $params['sanitize'] ?? false;
			$g = $this->Database->query("select * from `{$table}` limit 1");
			$r = $this->Database->fetch_fields($g);
			// Filter data sesuai field tabel
			for ($i = 0; $i < count($r); $i++) {
					array_push($fields,$r[$i]->name);
			}
			foreach ($data as $k => $v) {
					if (!in_array($k,$fields)) { unset($data[$k]); }
			}

			$fl = [];
			foreach ($data as $k => $v) {
				$v = $this->sanitize == true ? filter_var($v,FILTER_SANITIZE_SPECIAL_CHARS) : $v;
				
				if (is_array($primary)) {
					if (!in_array($k,$primary)) {
						$ffl[] = "`{$k}` = '". $this->Database->escape_string(trim($v)) ."'";	
					}
				} else {
					if (($v !== null and $v != '') or $k != $primary) {
						if ($v == 'NULL') {
							$ffl[] = "`{$k}` = NULL";
						} else {
							$ffl[] = "`{$k}` = '". $this->Database->escape_string(trim($v)) ."'";
						}
					}
				}
			}
			$where = '1';
			if ($primary) {
				if (is_array($primary)) {
					$pk = $primary[0];
					foreach($primary as $v) {
						$data[$v] = $this->sanitize == true ? filter_var($data[$v],FILTER_SANITIZE_SPECIAL_CHARS) : $data[$v];
						$where .= " and `{$v}` = '". $this->Database->escape_string($data[$v]) ."'";
					}
				} else {
					$pk = $primary;
					$data[$pk] = $this->sanitize == true ? filter_var($data[$pk],FILTER_SANITIZE_SPECIAL_CHARS) : $data[$pk];
					$where .= " and `{$pk}` = '". $this->Database->escape_string($data[$pk]) ."'";
				}
				if ($data[$pk] and $method != 'REPLACE') { 
					// $method = $method || 'UPDATE';  
					$method = 'DUPLICATEUPDATE';
				}
			}
 			switch($method) {
				default :
				case 'INSERT' : 
					$SQL = "INSERT INTO `{$table}` SET ". implode(", ",$ffl) ."";	
					break;
				case 'UPDATE' : 
					if ($params['where']) {
						$where = $params['where'];
					}
					$SQL = "UPDATE `{$table}` SET ". implode(", ",$ffl) ." WHERE {$where}";
					$ID = $data[$pk];
					break;
				case 'REPLACE' : 
					$SQL = "REPLACE INTO `{$table}` SET ". implode(", ",$ffl) ."";	
					$ID = $data[$pk];
					break;
				case 'DUPLICATEUPDATE' :
				case 'INSERTUPDATE' :
					$SQL = "INSERT INTO `{$table}` SET ". implode(", ",$ffl) . " ON DUPLICATE KEY UPDATE ". implode(", ",$ffl) ."";
					$ID = $data[$pk];
				break;
			}
		
			if ($this->Database->query($SQL)) {
				$ID = $ID ? $ID : $this->Database->insert_id();
				return $ID;
			} else {
				$this->error = $this->Database->error()." ({$SQL})"; 
				throw new \Exception($this->error, 1);    
			}
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}

    public function save($data,$table,$method='INSERT',$primary='id') {	
		return $this->saveTable([
			'data' => $data,
			'table' => $table,
			'method' => $method,
			'primary' => $primary
		]);
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
				return false;
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
					$v = $this->sanitize == true ? filter_var($v,FILTER_SANITIZE_SPECIAL_CHARS) : $v;
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
		if (is_array($data)) {
			return $data;
		}
		return $this->Database->escape_string($data);
	}

	function escstr($data) {
		if (is_array($data)) {
			return $data;
		}
		return $this->Database->escape_string($data);
	}

	function num($result) {
		return $this->Database->num_rows($result);
	}

	function form_option($option,$default='') {
		$this->form_select($option,$default);
	}

	function form_select($option,$default='') {
		if (is_array($option)) {
			if ($option['table']) {
				$option['query'] = $option['where'] ? $option['where'] : $option['query'];
				$option['query'] = $option['query'] ? $option['query'] : "";
				$g = $this->select("select * from `{$option['table']}` {$option['query']}");
				while($t = $this->fetch($g)) {
					echo '<option '. ($default == $t[$option['value']] ? 'selected' : '') .' value="'.$t[$option['value']].'" '. $this->table_attrib($t,[],'data-') .'>'.$t[$option['text']].'</option>';
				}
				
			} 
			else if ($option['file']) {
				if (file_exists($option['file'])) {
					$ext = pathinfo($option['file'], PATHINFO_EXTENSION);
					switch($ext) {	
						default :
							$o = file_get_contents($option['file']);
							$d = explode("\n",$o);
							foreach($d as $v) {
								echo '<option '. ($default == trim($v) ? 'selected' : '').' value="'.trim($v).'">'.trim($v).'</option>';
							}
						break;
						case 'csv' :
							$namecol = $option['namecol'] ?? 1;
							$valuecol = $option['valuecol'] ?? 0; 
							if (($handle = fopen($option['file'], "r")) !== FALSE) {
								while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
									echo '<option '. ($default == trim($data[$valuecol]) ? 'selected' : '').' value="'.trim($data[$valuecol]).'">'.trim($data[$namecol]).'</option>';
								}
								fclose($handle);
							}
						break;
						case 'json' :
							$o = file_get_contents($option['file']);
							$d = json_decode($o);
							foreach($d as $v) {
								echo '<option '. ($default == $v->value ? 'selected' : '').' value="'.$v->value.'">'.($v->text ?? $v->name).'</option>';
							}
						break;
					}
				}
			}
			else {
				if (is_array($option['data'])) {
					foreach($option['data'] as $v) {
						if (is_array($v)) {
							echo '<option '.($default == $v['value'] ? 'selected' : '').' value="'.$v['value'].'">'.$v['text'].'</option>';
						} else {
							echo '<option '.($default == $v ? 'selected' : '').' value="'.$v.'">'.$v.'</option>';
						}
					}
				} else {
					foreach($option as $v) {
						if (is_array($v)) {
							echo '<option '.($default == $v['value'] ? 'selected' : '').' value="'.$v['value'].'">'.$v['text'].'</option>';
						} else {
							echo '<option '.(trim($default) == trim($v) ? 'selected' : '').' value="'.$v.'">'.$v.'</option>';
						}
					}
				}
			}
		} else {
			if (preg_match("#.+?\|.+#si",$option)) {
				$d = explode("|",$option);
				foreach($d as $v) {
					echo '<option value="'.trim($v).'" '. ($default == trim($v) ? 'selected' : '') .'>'.trim($v).'</option>';
				}
			} else if (preg_match("#.+?,.+#si",$option)) {
				$d = explode(",",$option);
				foreach($d as $v) {
					echo '<option value="'.trim($v).'" '. ($default == trim($v) ? 'selected' : '') .'>'.trim($v).'</option>';
				}
			} else if (preg_match("#.+?;.+#si",$option)) {
				$d = explode(";",$option);
				foreach($d as $v) {
					echo '<option value="'.trim($v).'" '. ($default == trim($v) ? 'selected' : '') .'>'.trim($v).'</option>';
				}
			} else if (strtoupper($option) == 'MONTH') {
				for($i = 1; $i <= 12; $i++) {
					echo '<option value="'.$i.'" '. ($default == $i ? 'selected' : '') .'>'.date("M",mktime(0,0,0,$i,1,date(("Y")))).'</option>';
				}
			} else if (strtoupper($option) == 'DAY') {
				for($i = 1; $i <= 31; $i++) {
					echo '<option value="'.$i.'" '. ($default == $i ? 'selected' : '') .'>'.date("d",mktime(0,0,0,0,$i,date(("Y")))).'</option>';
				}
			}
		}
	}

	public function serverURL($path=true) {
        /* Thanks to phpBB for this Script */
		// We have to generate a full HTTP/1.1 header here since we can't guarantee to have any of the information
		// available as used by the redirect function
		$server_name = (!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : getenv('SERVER_NAME');
		$server_port = (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');
		$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 1 : 0;
		// $script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
		// if (!$script_name)
		// {
			$script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
		// }
	
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
	
		if ($path === true) { $url .= $script_path; } else {
			$url .= '/';
		}
		$SERVER_URL = $url;
		unset($url);
		return $SERVER_URL;
		/* End of phpBB Script */
    }

         
	/* Alias */
	function resizeImage($params = []) {
		return $this->resizeAndWatermarkImage($params);
	}

	function resizeAndWatermarkImage($params = []) {
		// Set nilai default untuk parameter
		$defaultParams = [
			'file' => null,
			'width' => 900,
			'height' => 900,
			'newfile' => null,
			'quality' => 80,
			'watermark' => [
					'file' => null,
					'pos' => 'top-left', // Default position
					'size' => 0.5,       // Default size as a ratio
			]
		];

		// Gabungkan parameter yang diberikan dengan nilai default
		$options = array_replace_recursive($defaultParams, $params);

		// Ambil parameter dari array $options
		$file = $options['file'];
		$w = $options['width'];
		$h = $options['height'];
		$newFile = $options['newfile'];
		$quality = $options['quality'];
		$watermark = $options['watermark'];

		if (!file_exists($file)) {
			return false;
		}

		$imageInfo = getimagesize($file);
		if (!$imageInfo) {
			return false;
		}

		list($originalWidth, $originalHeight) = $imageInfo;
		$mimeType = $imageInfo['mime'];

		// Menentukan fungsi untuk membuat dan menyimpan gambar berdasarkan MIME type
		$imageCreateFunc = null;
		$imageSaveFunc = null;
		switch ($mimeType) {
			case 'image/jpeg':
					$imageCreateFunc = 'imagecreatefromjpeg';
					$imageSaveFunc = 'imagejpeg';
					break;
			case 'image/png':
					$imageCreateFunc = 'imagecreatefrompng';
					$imageSaveFunc = 'imagepng';
					$quality = 9 - ceil($quality / 10); // Konversi kualitas JPEG ke skala PNG (0-9)
					break;
			case 'image/gif':
					$imageCreateFunc = 'imagecreatefromgif';
					$imageSaveFunc = 'imagegif';
					break;
			default:
					return false; // Jenis file tidak didukung
		}

		$sourceImage = $imageCreateFunc($file);
		if (!$sourceImage) {
			return false;
		}

		$aspectRatio = $originalWidth / $originalHeight;
		if ($originalWidth > $w || $originalHeight > $h) {
			if (($w / $h) > $aspectRatio) {
					$newWidth = $h * $aspectRatio;
					$newHeight = $h;
			} else {
					$newWidth = $w;
					$newHeight = $w / $aspectRatio;
			}
		} else {
			$newWidth = $originalWidth;
			$newHeight = $originalHeight;
		}

		$targetImage = imagecreatetruecolor($newWidth, $newHeight);
		
		// Menjaga transparansi untuk PNG
		if ($mimeType === 'image/png') {
			imagealphablending($targetImage, false);
			imagesavealpha($targetImage, true);
			$transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
			imagefill($targetImage, 0, 0, $transparent);
		}

		imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
		imagedestroy($sourceImage);

		if ($params['filter']['pixelate']) {
			imagefilter($targetImage, IMG_FILTER_PIXELATE, $params['filter']['pixelate'], true);
		}
		if ($params['filter']['negatif']) {
			imagefilter($targetImage, IMG_FILTER_NEGATE);
		}
		if ($params['filter']['smooth']) {
			imagefilter($targetImage, IMG_FILTER_SMOOTH, $param['filter']['smooth']);
		}
		if ($params['filter']['color']) {
			imagefilter($targetImage, IMG_FILTER_COLORIZE, $params['filter']['color']['red'] ?? 255, $params['filter']['color']['green'] ?? 255, $params['filter']['color']['blue'] ?? 255,$params['filter']['color']['alpha'] ?? 50);
		}
		
		if ($params['filter']['blur']) {
			for ($i = 0; $i < ($params['filter']['blur'] ?? 2); $i++) {
				imagefilter($targetImage, IMG_FILTER_GAUSSIAN_BLUR);
			}
		}
		if ($params['filter']['selective_blur']) {
			for ($i = 0; $i < ($params['filter']['selective_blur'] ?? 2); $i++) {
				imagefilter($targetImage, IMG_FILTER_SELECTIVE_BLUR);
			}
		}

		// Menambahkan watermark jika ada
		if (!empty($watermark['file']) && file_exists($watermark['file'])) {
			$watermarkInfo = getimagesize($watermark['file']);
			if ($watermarkInfo) {
					$watermarkMime = $watermarkInfo['mime'];
					$watermarkCreateFunc = ($watermarkMime === 'image/png') ? 'imagecreatefrompng' : 'imagecreatefromjpeg';
					$watermarkImage = $watermarkCreateFunc($watermark['file']);
					
					if ($watermarkImage) {
						list($logoWidth, $logoHeight) = $watermarkInfo;
						
						// Konversi ukuran string ke rasio jika perlu
						$size = floatval($watermark['size']);
						if ($size > 1) { // jika ukuran diberikan sebagai persen atau nilai absolut
							$scale = min($size / $logoWidth, $size / $logoHeight); // Skalakan agar tidak lebih dari 100%
						} else {
							$scale = $size;
						}
						
						$watermarkWidth = $logoWidth * $scale;
						$watermarkHeight = $logoHeight * $scale;
						
						$padding = 10;
						$x = 0;
						$y = 0;

						switch ($watermark['pos']) {
							case 'center':
									$x = ($newWidth - $watermarkWidth) / 2;
									$y = ($newHeight - $watermarkHeight) / 2;
									break;
							case 'top-right':
									$x = $newWidth - $watermarkWidth - $padding;
									$y = $padding;
									break;
							case 'bottom-right':
									$x = $newWidth - $watermarkWidth - $padding;
									$y = $newHeight - $watermarkHeight - $padding;
									break;
							case 'bottom-left':
									$x = $padding;
									$y = $newHeight - $watermarkHeight - $padding;
									break;
							case 'top-center':
									$x = ($newWidth - $watermarkWidth) / 2;
									$y = $padding;
									break;
							case 'bottom-center':
									$x = ($newWidth - $watermarkWidth) / 2;
									$y = $newHeight - $watermarkHeight - $padding;
									break;
							case 'middle-left':
									$x = $padding;
									$y = ($newHeight - $watermarkHeight) / 2;
									break;
							case 'middle-right':
									$x = $newWidth - $watermarkWidth - $padding;
									$y = ($newHeight - $watermarkHeight) / 2;
									break;
							case 'top-left':
							default:
									$x = $padding;
									$y = $padding;
									break;
						}

						imagecopyresampled($targetImage, $watermarkImage, $x, $y, 0, 0, $watermarkWidth, $watermarkHeight, $logoWidth, $logoHeight);
						imagedestroy($watermarkImage);
					}
			}
		}

		$finalFile = !empty($newFile) ? $newFile : $file;
		if ($imageSaveFunc === 'imagepng') {
			$imageSaveFunc($targetImage, $finalFile, $quality);
		} else {
			$imageSaveFunc($targetImage, $finalFile, $quality);
		}
		
		imagedestroy($targetImage);
		return true;
	}
	
	public function fileUpload($params)
	{
		$files          = $params['files'] ?? $_FILES;
		$max_size       = $params['max_size'] ?? 2000000; // default 2MB
		$target_upload  = $params['target_upload'] ?? 'uploads/';
		$unique_name    = $params['unique_name'] ?? false;
		$overwrite      = $params['overwrite'] ?? true;
		$type_allow     = $params['type_allow'] ?? ['jpg','jpeg','png','pdf'];
		$input_name     = $params['input_name'] ?? null;
		$custom_name	= $params['custom_name'] ?? null;
		
		// MIME mapping (lengkap + audio & video)
		$mime_allow_map = [

			// ===== IMAGE =====
			'jpg'  => ['image/jpeg'],
			'jpeg' => ['image/jpeg'],
			'png'  => ['image/png'],
			'gif'  => ['image/gif'],
			'webp' => ['image/webp'],

			// ===== DOCUMENT =====
			'pdf'  => ['application/pdf'],
			'doc'  => ['application/msword'],
			'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
			'xls'  => ['application/vnd.ms-excel'],
			'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
			'ppt'  => ['application/vnd.ms-powerpoint'],
			'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
			'txt'  => ['text/plain'],
			'csv'  => ['text/csv'],

			// ===== ARCHIVE =====
			'zip'  => ['application/zip'],
			'rar'  => ['application/x-rar-compressed'],

			// ===== AUDIO =====
			'mp3'  => ['audio/mpeg'],
			'wav'  => ['audio/wav','audio/x-wav'],
			'ogg'  => ['audio/ogg'],
			'm4a'  => ['audio/mp4','audio/x-m4a'],
			'aac'  => ['audio/aac'],
			'flac' => ['audio/flac'],

			// ===== VIDEO =====
			'mp4'  => ['video/mp4'],
			'mkv'  => ['video/x-matroska'],
			'avi'  => ['video/x-msvideo'],
			'mov'  => ['video/quicktime'],
			'wmv'  => ['video/x-ms-wmv'],
			'webm' => ['video/webm'],
			'3gp'  => ['video/3gpp']
		];

		// pastikan folder ada
		if (!is_dir($target_upload)) {
			mkdir($target_upload, 0755, true);
		}

		if ($input_name && isset($files[$input_name])) {
			$file = $files[$input_name];
		} else {
			$file = reset($files);
		}

		if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
			throw new Exception('Upload file gagal');
		}

		// validasi size
		if ($file['size'] > $max_size) {
			throw new Exception('Ukuran file terlalu besar');
		}

		// ambil extension
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		// ambil MIME type asli
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime  = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);

		// validasi type
		if (!in_array('all', $type_allow)) {

			if (!in_array($ext, $type_allow)) {
				throw new Exception('Extension tidak diizinkan');
			}

			// cek MIME cocok dengan extension
			if (isset($mime_allow_map[$ext])) {
				if (!in_array($mime, $mime_allow_map[$ext])) {
					throw new Exception('MIME type tidak valid / file palsu');
				}
			}
		}

		// nama file
		if ($unique_name) {
			$clean_name = preg_replace("/[^a-zA-Z0-9]/", "_", pathinfo($file['name'], PATHINFO_FILENAME));
			$filename = $clean_name . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
		} else {
			$filename = basename($custom_name ?? $file['name']);
		}

		$target_file = rtrim($target_upload, '/') . '/' . $filename;

		// overwrite check
		if (!$overwrite && file_exists($target_file)) {
			throw new Exception('File sudah ada');
		}

		// upload
		if (!move_uploaded_file($file['tmp_name'], $target_file)) {
			throw new Exception('Gagal menyimpan file');
		}

		if (is_array($params['resize']) && str_starts_with($mime, 'image/')) {
			$params['resize']['file'] = $target_file;
			$this->resizeAndWatermarkImage($params['resize']);
		}

		return [
			'path' => $target_file,
			'name' => $filename,
			'size' => $file['size'],
			'mime' => $mime,
			'ext'  => $ext
		];
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

	function table_attrib($data, $unshow = [], $prefix = '',$quote='"') {
		if (is_array($data)) {
			foreach($data as $k => $v) {
				if (in_array($k,$unshow)) continue;
				$v = str_replace(['"',"'"],"",$v);
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
	
	// time Y-m-d H:i:s to -> x hari/jam/menit/detik
	function timeShort($u) {
		$c = strtotime($u);
		$n = date("U");
		if ($n > $c) { $time = ($n - $c); }
		else { $time = ($c - $n); }
		return $this->timeToShort($time);
	}
	
	// Detik -> x hari/jam/menit/detik
	function timeToShort($time) {
		if ($time > (3600 * 24)) { $d = ceil($time / (3600 * 24)) ." hari"; }
		else if ($time > (3600)) { $d = ceil($time / 3600) ." jam"; }
		else if ($time > (60)) { $d = ceil($time / 60) ." menit"; }
		else if ($time > 15) { $d = $time ." detik"; }
		else { $d = $time.' detik'; }
		return $d;
	}

	// detik  -> jam:mnt:dtk
	function SecTimeStamp($time) {
		$h = '';
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
	
	// Hitung umur dari tanggal lahir
	function umur($tgl) {
		$tgl = date("Y-m-d",strtotime($tgl));
		list($y,$m,$d)=explode("-",$tgl);
		$umur = date("Y") - $y;
		if ($m > date("m")) { $umur = $umur - 1; }
		if ($m == date("m") and $d > date("d")) { $umur = $umur - 1; }
		return $umur;
	}
	
	// detik -> x Tahun x Bulan x Hari x Jam x Menit x Detik
	function formatWaktu($detik) {
		if ($detik < 0) return "Minus " . formatWaktu(abs($detik));
		if ($detik == 0) return "0 Detik";

		$satuan = array(
			'Tahun' => 365 * 24 * 60 * 60,
			'Bulan' => 30 * 24 * 60 * 60,
			'Hari'  => 24 * 60 * 60,
			'Jam'   => 60 * 60,
			'Menit' => 60,
			'Detik' => 1
		);

		$hasil = array();

		foreach ($satuan as $nama => $nilai_satuan) {
			if ($detik >= $nilai_satuan) {
				$jumlah = floor($detik / $nilai_satuan);
				$detik %= $nilai_satuan;
				$hasil[] = "$jumlah $nama";
			}
		}

		return implode(' ', $hasil);
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
			$options->scale = 20;
			// $options->outputType = QRCode::OUTPUT_IMAGE_PNG;
			$qrcode = (new QRCode($options))->render($data);
		} else {
			$options->scale = 20;
			// $options->outputType = QRCode::OUTPUT_IMAGE_PNG;
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

	function markdownToHtml($markdown) {
		// Penyimpanan placeholder
		$placeholders = [];
		$phIndex = 0;
		$makePh = function($html) use (&$placeholders, &$phIndex) {
			$key = "%%PH{$phIndex}%%";
			$placeholders[$key] = $html;
			$phIndex++;
			return $key;
		};

		// 1) EXTRACT CODE BLOCKS ```...``` (escape isi)
		$markdown = preg_replace_callback('/```(.*?)```/si', function($m) use ($makePh) {
			$inner = htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8');
			return $makePh("<pre><code>{$inner}</code></pre>");
		}, $markdown);

		// 2) EXTRACT INLINE CODE `...`
		$markdown = preg_replace_callback('/`([^`\n]+)`/i', function($m) use ($makePh) {
			$inner = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
			return $makePh("<code>{$inner}</code>");
		}, $markdown);

		// 3) [link=url]text[/link]  and [link=url] (prioritize)
		$markdown = preg_replace_callback('/\[link=(https?:\/\/[^\]\s]+)\](.*?)\[\/link\]/si', function($m) use ($makePh) {
			$url = $m[1];
			$label = $m[2] !== '' ? $m[2] : $m[1];
			return $makePh('<a href="javascript:void(0)" goto-link="'.htmlspecialchars($url, ENT_QUOTES).'">'.htmlspecialchars($label, ENT_QUOTES).'</a>');
		}, $markdown);

		$markdown = preg_replace_callback('/\[link=(https?:\/\/[^\]\s]+)\]/si', function($m) use ($makePh) {
			$url = $m[1];
			return $makePh('<a href="javascript:void(0)" goto-link="'.htmlspecialchars($url, ENT_QUOTES).'">'.htmlspecialchars($url, ENT_QUOTES).'</a>');
		}, $markdown);

		// 4) [wa=...] (support formats: 0812..., +62812..., 62812...)
		$markdown = preg_replace_callback('/\[wa=([^\]\s]+)\]/i', function($m) use ($makePh) {
			$raw = $m[1];
			// normalize: remove non-digits and leading +
			$digits = preg_replace('/\D+/', '', $raw);
			if (preg_match('/^0[0-9]+$/', $digits)) {
				$norm = '62' . substr($digits, 1);
			} elseif (preg_match('/^62[0-9]+$/', $digits)) {
				$norm = $digits;
			} else {
				// if starts with country code without +, keep
				$norm = $digits;
			}
			$label = htmlspecialchars($raw, ENT_QUOTES);
			return $makePh('<a href="javascript:void(0)" goto-link="https://wa.me/'.$norm.'">'.$label.'</a>');
		}, $markdown);

		// 5) [tel=...]
		$markdown = preg_replace_callback('/\[tel=([^\]\s]+)\]/i', function($m) use ($makePh) {
			$raw = $m[1];
			$clean = preg_replace('/\s+/', '', $raw);
			$label = htmlspecialchars($raw, ENT_QUOTES);
			return $makePh('<a href="javascript:void(0)" goto-link="tel:'.$clean.'">'.$label.'</a>');
		}, $markdown);

		// 6) [email=...]
		$markdown = preg_replace_callback('/\[email=([^\]\s]+)\]/i', function($m) use ($makePh) {
			$raw = $m[1];
			$label = htmlspecialchars($raw, ENT_QUOTES);
			return $makePh('<a href="javascript:void(0)" goto-link="mailto:'.$raw.'">'.$label.'</a>');
		}, $markdown);

		// 7) IMAGE ![alt](url)
		$markdown = preg_replace_callback('/!\[(.*?)\]\((https?:\/\/[^\)]+)\)/i', function($m) use ($makePh) {
			$alt = htmlspecialchars($m[1], ENT_QUOTES);
			$url = htmlspecialchars($m[2], ENT_QUOTES);
			return $makePh('<img src="'.$url.'" alt="'.$alt.'" style="max-width:100%;">');
		}, $markdown);

		// 8) AUTO LINKS https?://...
		$markdown = preg_replace_callback('/(?<!="|goto-link=")(https?:\/\/[^\s<>\)\]]+)/i', function($m) use ($makePh) {
			$url = $m[1];
			return $makePh('<a href="javascript:void(0)" goto-link="'.htmlspecialchars($url, ENT_QUOTES).'">'.htmlspecialchars($url, ENT_QUOTES).'</a>');
		}, $markdown);

		// 9) AUTO EMAILS (only those not inside placeholders)
		$markdown = preg_replace_callback('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', function($m) use ($makePh) {
			$email = $m[1];
			return $makePh('<a href="javascript:void(0)" goto-link="mailto:'.htmlspecialchars($email, ENT_QUOTES).'">'.htmlspecialchars($email, ENT_QUOTES).'</a>');
		}, $markdown);

		// 10) AUTO PHONE / WA detection for Indonesian numbers
		// pattern matches +62xxxx or 0xxxx (7..15 digits)
		$markdown = preg_replace_callback('/(?<![\\w\%\>])(\+?62[0-9]{7,15}|0[0-9]{7,15})(?!\w)/', function($m) use ($makePh) {
			$raw = $m[1];
			// keep display original, normalize for wa.me
			$digits = preg_replace('/\D+/', '', $raw);
			if (preg_match('/^0[0-9]+$/', $digits)) {
				$norm = '62'.substr($digits,1);
			} else {
				$norm = preg_replace('/^\+/', '', $digits);
			}
			// prefer WA link (as requested)
			$label = htmlspecialchars($raw, ENT_QUOTES);
			return $makePh('<a href="javascript:void(0)" goto-link="https://wa.me/'.$norm.'">'.$label.'</a>');
		}, $markdown);

		// At this point, bracketed forms and auto forms have been tokenized + safe.

		// 11) TABLES - detect contiguous lines that look like table (|...|)
		$markdown = preg_replace_callback('/((?:\|.*\|\s*\n)+)/', function($m) use ($makePh) {
			$block = trim($m[1]);
			$rows = array_filter(array_map('rtrim', explode("\n", trim($block))));
			if (count($rows) === 0) return $m[0];

			$html = "<table border=\"1\" cellspacing=\"0\" cellpadding=\"6\">";
			$theadDone = false;

			// Find header (first row) and separator (second row with ---)
			if (count($rows) >= 2 && preg_match('/^\s*\|?[\s:-]+\|?[\s:-\|]*$/', $rows[1])) {
				// header present
				$headers = array_map('trim', explode('|', trim($rows[0], "| \t")));
				$html .= "<thead><tr>";
				foreach ($headers as $h) {
					$html .= "<th>".trim($h)."</th>";
				}
				$html .= "</tr></thead><tbody>";
				for ($i = 2; $i < count($rows); $i++) {
					$cols = array_map('trim', explode('|', trim($rows[$i], "| \t")));
					$html .= "<tr>";
					foreach ($cols as $c) $html .= "<td>{$c}</td>";
					$html .= "</tr>";
				}
				$html .= "</tbody></table>";
				return $makePh($html);
			} else {
				// simple table without header
				foreach ($rows as $r) {
					$cols = array_map('trim', explode('|', trim($r, "| \t")));
					if ($r === '') continue;
					$html .= "<tr>";
					foreach ($cols as $c) $html .= "<td>{$c}</td>";
					$html .= "</tr>";
				}
				$html .= "</table>";
				return $makePh($html);
			}
		}, $markdown);

		// 12) Block-level parsing: lines -> headings, lists, blockquote, hr, paragraphs
		$lines = preg_split("/\r\n|\n|\r/", $markdown);
		$out = "";
		$inUL = false; $inOL = false;

		foreach ($lines as $line) {
			$trim = rtrim($line);

			// Heading
			if (preg_match('/^######\s+(.*)$/', $trim, $m)) { $out .= "<h6>{$m[1]}</h6>\n"; continue; }
			if (preg_match('/^#####\s+(.*)$/', $trim, $m))  { $out .= "<h5>{$m[1]}</h5>\n"; continue; }
			if (preg_match('/^####\s+(.*)$/', $trim, $m))   { $out .= "<h4>{$m[1]}</h4>\n"; continue; }
			if (preg_match('/^###\s+(.*)$/', $trim, $m))    { $out .= "<h3>{$m[1]}</h3>\n"; continue; }
			if (preg_match('/^##\s+(.*)$/', $trim, $m))     { $out .= "<h2>{$m[1]}</h2>\n"; continue; }
			if (preg_match('/^#\s+(.*)$/', $trim, $m))      { $out .= "<h1>{$m[1]}</h1>\n"; continue; }

			// Horizontal rule
			if (preg_match('/^\s*(\-\-\-|\*\*\*|___)\s*$/', $trim)) {
				// close lists if open
				if ($inUL) { $out .= "</ul>\n"; $inUL = false; }
				if ($inOL) { $out .= "</ol>\n"; $inOL = false; }
				$out .= "<hr />\n"; continue;
			}

			// Blockquote
			if (preg_match('/^\>\s?(.*)$/', $trim, $m)) {
				if ($inUL) { $out .= "</ul>\n"; $inUL = false; }
				if ($inOL) { $out .= "</ol>\n"; $inOL = false; }
				$out .= "<blockquote>{$m[1]}</blockquote>\n"; continue;
			}

			// UL
			if (preg_match('/^[\-\+\*]\s+(.*)$/', $trim, $m)) {
				if ($inOL) { $out .= "</ol>\n"; $inOL = false; }
				if (!$inUL) { $out .= "<ul>\n"; $inUL = true; }
				$out .= "<li>{$m[1]}</li>\n"; continue;
			}

			// OL
			if (preg_match('/^\d+\.\s+(.*)$/', $trim, $m)) {
				if ($inUL) { $out .= "</ul>\n"; $inUL = false; }
				if (!$inOL) { $out .= "<ol>\n"; $inOL = true; }
				$out .= "<li>{$m[1]}</li>\n"; continue;
			}

			// empty line closes lists
			if (trim($trim) === '') {
				if ($inUL) { $out .= "</ul>\n"; $inUL = false; }
				if ($inOL) { $out .= "</ol>\n"; $inOL = false; }
				$out .= "\n";
				continue;
			}

			// normal paragraph (line may contain placeholders)
			$out .= "<p>{$trim}</p>\n";
		}

		// close open lists
		if ($inUL) $out .= "</ul>\n";
		if ($inOL) $out .= "</ol>\n";

		// 13) Restore placeholders
		if (!empty($placeholders)) {
			// replace keys by values
			$out = str_replace(array_keys($placeholders), array_values($placeholders), $out);
		}

		return $out;
	}

	public function table_exists($table) {
		$g = $this->query("show tables like '{$table}'");
		$t = $this->fetch($g,'row');
		return $t[0] > 0 ? true : false;
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

	public function dropbox_save($option) {
		$HOME_DIR = $option['dir'] ?? $this->config['dropbox']['home_dir'];
		$HOME_DIR = $HOME_DIR ? $HOME_DIR.'/' : '';
		$remove_file = $option['remove_file'] ?? true;
		try {
			if (!isset($this->config['dropbox']['access_token'])) {
				throw new Exception("Dropbox Access Token not set");
			}
			if (!is_array($option)) { throw new \Exception("Invalid Arguments"); }
			if (!isset($option['file'])) { throw new \Exception("Invalid Arguments File location"); }
			if (!file_exists($option['file'])) { throw new \Exception("File not found"); }
			if (!is_file($option['file'])) { throw new \Exception("File invalid format"); }

			$DBX = new Dropbox($this->config['dropbox']['access_token']);
			$DBX->upload($option['file'],'overwrite','/'.$HOME_DIR);

			// Create or Get Shared Link
			$d = $DBX->create_shared_link($HOME_DIR.basename($option['file']));
			if ($d['url']) {
				$url = str_replace("dl=0","raw=1",$d['url']);
				if ($remove_file == true) { if (file_exists($option['file'])) { unlink($option['file']); } }
				return $url;
			} else {
				if (preg_match("#already_exists#si",$d['error_summary'])) {
					$d = $DBX->get_shared_link($HOME_DIR.basename($option['file']));
					$url = str_replace("dl=0","raw=1",$d['links'][0]['url']);
					if ($remove_file == true) { if (file_exists($option['file'])) { unlink($option['file']); } }
					return $url;
				} else {
					throw new \Exception($d['error_summary']);
				}
			}

		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			$this->debug($this->error,__FILE__,__LINE__);
			return false;
		}
	}

	public function dropbox_delete($option) {
		$HOME_DIR = $option['dir'] ?? $this->config['dropbox']['home_dir'];
		$HOME_DIR = $HOME_DIR ? $HOME_DIR.'/' : '';
		try {
			if (!isset($this->config['dropbox']['access_token'])) {
				throw new Exception("Dropbox Access Token not set");
			}
			if (!is_array($option)) { throw new \Exception("Invalid Arguments"); }
			if (!isset($option['url'])) { throw new \Exception("Invalid Arguments File URL"); }

			$DBX = new Dropbox($this->config['dropbox']['access_token']);
			$d = $DBX->get_shared_link_file($option['url']);
			if ($d['name']) { 
				return $DBX->delete($HOME_DIR.$d['name']);
			} else {
				throw new \Exception($d['error_summary']);
			}
		} catch (\Exception $e) {
			$this->error = $e->getMessage();
			$this->debug($this->error,__FILE__,__LINE__);
			return false;
		}
	}
	
	function numberShort($num, $lan = 'ID', $decnum = 1, $tipe = 'SHORT', $currency = '') {
		$is_negative = $num < 0;
		$num = abs($num);
		
		// Tentukan prefix (Simbol Mata Uang + Tanda Negatif)
		$symbol = $currency !== '' ? $currency . ' ' : '';
		$prefix = $is_negative ? '-' . $symbol : $symbol;

		$units = [
			[1000000000000000000, 'ID' => ['Ki', ' Kuintiliun'], 'EN' => ['Qi', ' Quintillion']],
			[1000000000000000,    'ID' => ['Kd', ' Kuadriliun'], 'EN' => ['Q', ' Quadrillion']],
			[1000000000000,       'ID' => ['Tr', ' Triliun'],    'EN' => ['T', ' Trillion']],
			[1000000000,          'ID' => ['Ml', ' Miliar'],     'EN' => ['B', ' Billion']],
			[1000000,             'ID' => ['Jt', ' Juta'],       'EN' => ['M', ' Million']],
			[1000,                'ID' => ['Rb', ' Ribu'],       'EN' => ['K', ' Thousand']],
		];

		foreach ($units as $unit) {
			$value = $unit[0];
			if ($num >= $value) {
				$names = $unit[$lan];
				$label = ($tipe == 'SHORT') ? $names[0] : $names[1];
				
				// Format angka dengan desimal yang ditentukan
				$formatted = number_format($num / $value, $decnum, '.', ',');
				
				// Bersihkan .00 jika tidak diperlukan (Opsional)
				$formatted = rtrim(rtrim($formatted, '0'), '.');

				return $prefix . $formatted . $label;
			}
		}

		// Jika angka di bawah 1000, tetap tampilkan desimal jika ada
		return $prefix . number_format($num, ($num == floor($num) ? 0 : $decnum), '.', ',');
	}


	private function penyebut($nilai) {
		$nilai = abs((float) $nilai);
		$huruf = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
		$temp = "";
		
		if ($nilai < 12) {
			$temp = " ". $huruf[$nilai];
		} else if ($nilai < 20) {
			$temp = $this->penyebut($nilai - 10). " belas";
		} else if ($nilai < 100) {
			$temp = $this->penyebut($nilai/10)." puluh". $this->penyebut($nilai % 10);
		} else if ($nilai < 200) {
			$temp = " seratus" . $this->penyebut($nilai - 100);
		} else if ($nilai < 1000) {
			$temp = $this->penyebut($nilai/100) . " ratus" . $this->penyebut($nilai % 100);
		} else if ($nilai < 2000) {
			$temp = " seribu" . $this->penyebut($nilai - 1000);
		} else if ($nilai < 1000000) {
			$temp = $this->penyebut($nilai/1000) . " ribu" . $this->penyebut($nilai % 1000);
		} else if ($nilai < 1000000000) {
			$temp = $this->penyebut($nilai/1000000) . " juta" . $this->penyebut($nilai % 1000000);
		} else if ($nilai < 1000000000000) {
			$temp = $this->penyebut($nilai/1000000000) . " miliar" . $this->penyebut($nilai % 1000000000);
		} else if ($nilai < 1000000000000000) {
			$temp = $this->penyebut($nilai/1000000000000) . " triliun" . $this->penyebut($nilai % 1000000000000);
		}     
		return $temp;
	}

	public function terbilang($nilai) {
		if($nilai < 0) {
			$hasil = "minus ". trim($this->penyebut($nilai));
		} else {
			$hasil = trim($this->penyebut($nilai));
		}
		
		// Logika Desimal (Koma)
		if (fmod($nilai, 1) !== 0.0) {
			$hasil .= " koma";
			
			// Ambil angka di belakang koma saja
			$str_nilai = (string)$nilai;
			$bagian_desimal = explode('.', $str_nilai)[1];
			$arr_desimal = str_split($bagian_desimal);
			$huruf_desimal = array("nol", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan");
			
			foreach ($arr_desimal as $digit) {
				$hasil .= " " . $huruf_desimal[$digit];
			}
		}
		
		return $hasil;
	}

	public function normalizePhoneNumber($number) {
		// hapus spasi, strip, dll
		$number = preg_replace('/[^0-9]/', '', $number);

		// jika diawali 08 -> ubah ke 628
		if (str_starts_with($number, '08')) {
			return '628' . substr($number, 2);
		}

		// jika diawali 8 (kadang user tulis tanpa 0) -> ubah ke 628
		if (str_starts_with($number, '8')) {
			return '628' . substr($number, 1);
		}

		// jika diawali 628 -> sudah benar
		if (str_starts_with($number, '628')) {
			return $number;
		}

		return null;
	}

	public function start_transaction() {
		$this->Database->start_transaction();
	}

	public function commit_transaction() {
		$this->Database->commit_transaction();
	}

	public function rollback_transaction() {
		$this->Database->rollback_transaction();
	}


	public function debug($m,$file='',$line='') {
			$text = "[".date("Y-m-d H:i:s")."][{$_SERVER['REMOTE_ADDR']}] {$m} ({$file} on line {$line})\n";
			if (!$this->table_exists('z_debug')) {
				$this->query("CREATE TABLE if not exists  `z_debug` (
					`id` bigint(15)NOT NULL AUTO_INCREMENT,
					`tanggal` datetime NULL DEFAULT current_timestamp(),
					`logtext` longtext  NULL DEFAULT NULL,
					`ipaddress` varchar(250) NULL DEFAULT NULL,
					`filename` varchar(250)  NULL DEFAULT NULL,
					`line` char(10)  NULL DEFAULT NULL,
					  PRIMARY KEY  (`id`)
				) Engine = MyISAM;");
			}
			if (trim($m)) {
				$this->save([
					'tanggal' => date("Y-m-d H:i:s"),
					'logtext' => $m,
					'filename' => $file,
					'ipaddress' => $_SERVER['REMOTE_ADDR'] ?? 'NULL',
					'line' => $line
				],'z_debug');

				if ($this->debugSaveToFile == true) {
					if ($this->debugPathFile and file_exists($this->debugPathFile)) {
						$debug = file_get_contents($this->debugPathFile);
						file_put_contents($this->debugPathFile,"{$text}{$debug}");
					} else {
						file_put_contents($this->debugPathFile,$text);
					}
				}

				if ($this->debugShow == true) {
					echo $text;
				}
			}
	}

	function __destruct() {

    }
}
?>
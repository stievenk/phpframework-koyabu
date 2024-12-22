<?php
namespace Koyabu\Webapi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class KoyabuAPI extends Form {

   public $config;
   public $Headers;
   public $token_expire = "+1 weeks";
   public $RELOAD_URL;
   public $SiteBarDefault = [];

   function __construct($config) {
      $this->config = $config;
      $this->SQLConnection($config);
      $this->Headers = getallheaders();
      if ($this->config['sidebar_default']) {
        $this->SiteBarDefault = $this->config['sidebar_default'];
    }
      // print_r($this->Headers);
   }

   function getConfig() {
        $g = $this->select("select * from z_config");
        while($t = $this->fetch($g)) {
            $this->config[trim($t['name'])] = trim($t['value']);
        }
    }

   public function loadPage($option=array()) {
      try {
         $page = $_GET['call'] ? $_GET['call'] : 'home';
         $module = $_GET['mod'] ? $_GET['mod'] : '';
         $subpage = $_GET['subpage'] ? $_GET['subpage'] : '';
         $modfile = $_GET['f'] ? $_GET['f'] : 'index';
         $this->MOD_URL = $MOD_URL = '?call='.$page.'&mod='.$module; //.'&f='.$modfile.'&subpage='.$subpage;
         $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
         $this->REF_URL = $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : '?call=home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
         $this->RELOAD_URL = $RELOAD_URL = '?'.$SERVER_REQUEST_STR['query'];
         // trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');

         if ($_GET['call'] == 'module' || $_GET['call'] == 'modules' || $_GET['call'] == 'mod') {
            if ($module) {
               if ($option['isUserLogin']) {
                  if ($this->isUserLogin($_POST['username'])) {
                     $include = $this->HOME_ROOT . 'modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR. basename($modfile) . '.php';
                  } else {
                     $include = $this->HOME_ROOT. 'call' . DIRECTORY_SEPARATOR . 'login.php';
                  }
               } else {
                  $include = $this->HOME_ROOT . 'modules' . DIRECTORY_SEPARATOR . $module. DIRECTORY_SEPARATOR. basename($modfile) . '.php';
               }
            } else {
               // if ($option['isUserLogin']) {
               //    if ($this->isUserLogin($_POST['username'])) {
               //       $include = $this->HOME_ROOT. 'call' . DIRECTORY_SEPARATOR . 'home.php';
               //    } else {
               //       $include = $this->HOME_ROOT. 'call' . DIRECTORY_SEPARATOR . 'login.php';
               //    }
               // } else {
               //    $include = $this->HOME_ROOT. 'call' . DIRECTORY_SEPARATOR . 'home.php';
               // }
               throw new \Exception("Error Processing Request");
            }
         } else {
            $include = $this->HOME_ROOt . 'call' . DIRECTORY_SEPARATOR . basename($page) . '.php';
         }
        //  echo $include;
         if (file_exists($include)) {
            // echo $include;
            // FCM Token update
            if ($_POST['fcm_token']) {
                $this->isUserLogin($_POST['username']);
                if ($this->USER['id']) {
                    $this->save(array( 'id' => $this->USER['id'], 'fcm_token' => $_REQUEST['fcm_token']),'t_member');
                }
            }
            include_once $include;
            $this->getScriptBottom([]);
         } else {
            throw new \Exception("Invalid filename");  
         }
      } catch(\Exception $e) {
         $this->error = $e->getMessage();
         echo json_encode(['response' => $this->error ]); exit;
      }
   }

   public function getUserData($username = "") {
        $username = $username ? $username : $this->Params['username'];
        try {
            if ($username) {
                $g = $this->Database->query("select * from t_member where username='". $this->escape_string($username) ."'");
                $t = $this->Database->fetch_assoc($g);
                if ($t['id']) {
                    return $t;
                } else {
                    throw new \Exception("Error: Username not found", 1);
                }
            } else {
                throw new \Exception("Please login", 1);
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            //echo json_encode(array('done' => 0, 'response' => $this->error)); exit;
            return false;
        }
    }

    public function updateUserData($id, $token = '', $token_expired = '') {
        $this->Headers = getallheaders();
        $devparam = array_merge($_POST,$_GET);
        $devparam['app_version'] = $this->Headers['app_version'];
        $params = array(
            'id' => $id,
            'token' => $this->Headers['token'],
            'lastlogin' => date("Y-m-d H:i:s"),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'lat' => $_REQUEST['lat'],
            'lng' => $_REQUEST['lng'],
            'device_params' => json_encode($devparam)
        );
        if ($token) { $params['token'] = $token; }
        $params['token_expire'] = $token_expired ? $token_expired : date("Y-m-d H:i:s",strtotime("{$this->token_expire}"));
        if ($this->Headers['refresh_token']) {
            $params['device_uuid'] = $this->Headers['refresh_token'];
        }
        try {
            if (!$params['id']) {
               throw new Exception("Error Processing Request, Invalid user ID", 1);
            }
            if ($id = $this->save($params,'t_member')) {
               return $id;
            } else {
               throw new \Exception($this->error);
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

   public function login() {
      try {
         if (trim($_POST['uname']) and trim($_POST['passwd'])) {
                  $g = $this->select("select * from t_member where `username`='". $this->escape_string($_POST['uname']) ."' or `email`='". $this->escape_string($_POST['uname']) ."'");
                  $t = $this->fetch($g);
               if ($t['id']) {
                  if ($t['tipe'] == 'BANNED') {
                     throw new \Exception("Your account is BANNED", 1);
                  }
                  if (md5(trim($_POST['passwd'])) == $t['password']) {
                     $t['user_token'] = md5(uniqid().$t['id'].$_SERVER['REMOTE_ADDR']);
                     if ($this->updateUserData($t['id'],$t['user_token'],date("Y-m-d H:i:s",strtotime("+1 week")))) {
                        unset($t['password']);
                        return $t;
                     } else {
                        return false;
                     }
                     
                  } else {
                        throw new \Exception("Password tidak tepat!");
                     return false;
                  }
               } else {
                  throw new \Exception("Username tidak terdaftar", 1);
               }
         } else {
               throw new \Exception("Username atau password belum diisi", 1);
         }
      } catch( \Exception $e) {
         $this->error = $e->getMessage();
         return false;
      }
      
   }

   public function isUserLogin($username) {
        try {
            if ($t = $this->getUserData($username)) {
                
                if ($t['tipe'] == 'BANNED') {
                    $this->banned = true; 
                    throw new \Exception("Your account is BANNED", 1);
                }
                if ($t['token'] == $this->Headers['token']) {
                    $token_expire = strtotime($t['token_expire']);
                    if (date("U") > $token_expire) {
                        throw new \Exception("Token expired, silahkan login kembali", 1);
                    }
                    $this->updateUserData($t['id']);
                    $this->USER = $t;
                    return true;
                } else {
                    throw new \Exception("Login expired, silahkan login kembali", 1);
                }
            }
        }  catch (\Exception $e) {
            $this->error = $e->getMessage();
            //echo json_encode(array('done' => 0, 'response' => $this->error)); exit;
            return false;
        }
    }

    public function setTokenExpire($expire = "1+ weeks") {
        $this->token_expire = $expire;
    }

   public function route($cmd) {
      $function = $cmd;
      $m = method_exists($this,$function);
      if ($m) {
         // echo $function;
         $this->$function();
      } else {
         // echo 'not found '.$cmd;
      }
   }

   function formValidation(array $data,string $ignoreName = "", int $minChar = 3):bool {
        $err = 0;
        $errmsg = array();
        if (is_array($data)) {
            foreach($data as $k => $v) {
                if ($ignoreName) { 
                    if (preg_match($ignoreName,$k)) continue;
                }
                if (trim($v) and strlen(trim($v)) >= $minChar) {
                    //
                } else {
                    $r = array('usr','passwd','eml');
                    $s = array('Username','Password','Email');
                    $kk = str_replace($r,$s,strtolower($k));
                    $errmsg[]=ucwords(strtolower(str_replace("_"," ",$kk)));
                    $err++; 
                }
            }
        }

        if ($err > 0) { 
            $this->error = "Lengkapi ".implode(", ",$errmsg);
            return false; 
        } else {
            return true;
        }
    }

   function returnData($data = array()) {
        $data = $data ? $data :  array('done' => 0, 'response' => '');
        echo json_encode($data); exit;
    }
   
   public function getLoginPage() {
      include_once $this->HOME_ROOT . 'call/login.php';
   }

   public function getFooterButton($option=array()) {
      include_once $this->HOME_ROOT . 'html/footer_button.php';
   }

   public function getScriptBottom($option=array()) {
      include_once $this->HOME_ROOT . 'html/script_bottom.php';
   }

   public function getNavBar($option=array()) {
      include_once $this->HOME_ROOT . 'html/navbar.php';
      if ($option['sidebar'] == true and $option['sidebar_auto'] == true) {
        $this->getSideBar([]);
      }
   }

   public function getSideBar($option=array()) {
      include_once $this->HOME_ROOT . 'html/sidebar.php';
   }

   function googleServicesJSON() {
        $google_json = $this->HOME_ROOT.'data/google-services.json';
        if (file_exists($google_json)) {
            $json = json_decode(file_get_contents($google_json),true);
            if (is_array($json['client'] )) {
                foreach($json['client'] as $v) {
                    $apk = $v['client_info']['android_client_info']['package_name'];
                    
                    if ($apk == $this->config['APK'][0]) {
                        $data = $v;
                    }
                }
            }
            return $data;
        } else {
            return false;
        }
    }

   function sendMail($option) {
        
        $mail = new PHPMailer(true);
        try {
            //Server settings
            // $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = $this->config['smtp_host'];                     //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = $this->config['smtp_user'];                     //SMTP username
            $mail->Password   = $this->config['smtp_pass'];                               //SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            
            if (is_array($option['to'])) {
                foreach($option['to'] as $v) {
                    $mail->addAddress($v); 
                }
            }
            else { $mail->addAddress($option['to']); }

            if ($option['from']) {
                $mail->setFrom($option['from']['email'], $option['from']['name']);
            }
            if ($option['replyTo']) {
                $mail->addReplyTo($option['replyTo']['email'], $option['replyTo']['name']);
            }
            if ($option['bcc']) {
                $mail->addBCC($option['bcc']);
            }
            if ($option['cc']) {
                $mail->addCC($option['cc']);
            }
            if ($option['attachment']) {
                $mail->addAttachment($option['attachment']); 
            }
            $mail->isHTML($option['isHTML'] ? $option['isHTML'] : false);                                 //Set email format to HTML
            $mail->Subject = $option['subject'];
            $mail->Body    = $option['body'];
            // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            //echo 'Message has been sent';
            return true;
        } catch (Exception $e) {
            $this->error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            return false;
        }
    }

    function notif($m,$tipe='ALL',$cmd = 'send',$data=array('notif' => '1'),$pfx=NULL) {
		global $config;
		$PREFIX = $pfx ? $pfx : $this->config['APPS_NAME'];
		if ($tipe == 'ALL') { $topic = 'notif_'.md5($PREFIX); }
		else {
			$topic = strtolower($tipe).'_'.md5($PREFIX);
		}
		if (is_array($m)) {
			/**/
			if ($cmd == 'send') {
				return $this->fcm($m,$topic,'topic',$data);
			} else {
				return $topic;
			}
			/**/
		} else {
			return $topic;
		}
	}

    function fcm($msg,$topicToken = 'test',$tipe ='topic',$param = array('hwm' => '1')) {
        $data = array('done' => 0, 'response' => '');
        $project = $this->config['fcm_project_id'];
        foreach($param as $k => $v) {
            $params[$k] = (string) $v;
        }
        $param = $params;
        try {
            if (!is_array($msg)) {
                throw new \Exception("\$msg invalid arguments", 1);
            }
            if (!$project) { throw new \Exception("FCM Project ID not set", 1); }

            if (!file_exists($this->config['HOME_DIR'].'data/credentials.json')) {
                throw new \Exception("Credentials not found", 1);
            }
            $client = new \Google_Client();
		    putenv('GOOGLE_APPLICATION_CREDENTIALS='.$this->config['HOME_DIR'].'data/credentials.json');
		    $client->setAuthConfig($this->config['HOME_DIR'].'data/credentials.json');
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
		    $httpClient = $client->authorize();

            if ($tipe == 'topic') {
                // Creates a notification for subscribers to the debug topic
                $message = array(
                    "message" => array(
                        "topic" => $topicToken,
                        "notification" => $msg,
                        "data" => $param
                    )
                );
            } else {
                $message = array(
                    "message" => array(
                        "token" => $topicToken,
                        "notification" => $msg,
                        "data" => $param
                    )
                );
            }
            $response = $httpClient->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", array('json' => $message));
		    // echo $response;
            return $response;
        } catch(\Exception $e) {
            $data['response'] = $e->getMessage();
            $this->error = $e->getMessage();
            // echo json_encode($data); exit;
        }
    }

}
?>
<?php
namespace Koyabu\Webapi;
/** 
 * Koyabu API 
 * version: 8.1.0
 * require: PHP 8.1+
*/
class Koyabu extends Form {

    public $config;
    public $Params = array();
    public $token_expire = "+1 weeks";
    public $Headers;
    public $HOME_ROOT = '';
    public $USER = array();
    public $banned = false;

    function __construct($config) {
        $this->config = $config;
        $this->SQLConnection($config);
        $this->Headers = getallheaders();
        $this->Params = $_POST;
        $this->HOME_ROOT = $this->config['HOME_DIR'] ? $this->config['HOME_DIR'] : '';
        // print_r($this->Headers); exit;
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
                        $this->updateUserData($t['id'],$t['user_token'],date("Y-m-d H:i:s",strtotime("+1 week")));
                        return $t;
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
                    // throw new \Exception("Your account is BANNED", 1);
                }
                // print_r($this->Headers);
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

    public function updateUserData($id, $token = '', $token_expired = '') {
        $params = array(
            'id' => $id,
            'token' => $this->Headers['token'],
            'lastlogin' => date("Y-m-d H:i:s"),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'lat' => $_REQUEST['lat'],
            'lng' => $_REQUEST['lng']
        );
        if ($token) { $params['token'] = $token; }
        $params['token_expire'] = $token_expired ? $token_expired : date("Y-m-d H:i:s",strtotime("{$this->token_expire}"));
        if ($this->Headers['refresh_token']) {
            $params['device_uuid'] = $this->Headers['refresh_token'];
        }
        try {
            $id = $this->save($params,'t_member');
            return $id;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function setTokenExpire($expire = "1+ weeks") {
        $this->token_expire = $expire;
    }

    public function loadPage() {
        $error = array('done' => 0, 'response' => '');
        $_GET['call'] = $_GET['call'] ? $_GET['call'] : 'home';
        $_GET['mod'] = $_GET['mod'] ? $_GET['mod'] : $_GET['m'];
        $_GET['m'] = $_GET['m'] ? $_GET['m'] : $_GET['mod'];
        $MOD_URL = $_GET['call']."&mod=".$_GET['mod'];
        $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
        $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : 'home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
        $RELOAD_URL = trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');
        $buttonHome = true;
        try {
            // var_dump($this->isUserLogin($this->Params['username']));
            if ($this->isUserLogin($this->Params['username'])) {
                // FCM Token update
                if ($_REQUEST['fcm_token']) {
                    $this->save(array( 'id' => $this->USER['id'], 'fcm_token' => $_REQUEST['fcm_token']),'t_member');
                }
                // echo "Logged";
                if ($_GET['call'] == 'module' || $_GET['call'] == 'modules' || $_GET['call'] == 'mod') {
                    $mod = $_GET['mod'] ? $_GET['mod'] : $_GET['m'];
                    if ($this->USER['id']) {
                        $CALL = $this->HOME_ROOT . 'modules' . DIRECTORY_SEPARATOR . $mod . DIRECTORY_SEPARATOR;
                        if ($_GET['f']) { $CALL = $CALL . basename($_GET['f']) . '.php'; }
                        else { $CALL = $CALL . 'index.php'; }
                    }
                } else {
                    $CALL = $this->HOME_ROOT . 'call/' . basename($_GET['call']) . '.php';
                }
                if (file_exists($CALL)) {
                     include_once $CALL;
                } else {
                    throw new \Exception(basename($CALL)." not found", 1);
                }
            } else {
                if (!$_GET['call']) {
                    // echo 'login';
                    include_once $this->HOME_ROOT . 'call/login.php';
                } else {
                    if (file_exists($this->HOME_ROOT.'call/'.basename($_GET['call']).'.php')) {
                         include_once $this->HOME_ROOT.'call/'.basename($_GET['call']).'.php';
                    } else {
                        throw new \Exception("{$_GET['call']} not found", 1);
                    }
                }
            }
            if ($buttonHome and $BottomControllerHidden != true and $ControllerHidden != true and $HideController != true) { 
                if (file_exists($this->HOME_ROOT.'include/bottom-controler.php')) {
                    include_once $this->HOME_ROOT.'include/bottom-controler.php'; 
                }
            }
            if ($_GET['call'] == 'home' and !$_REQUEST['fcm_token']) {
                if (file_exists($this->HOME_ROOT.'include/firebase-controler.php')) {
                    include_once $this->HOME_ROOT.'include/firebase-controler.php'; 
                }
            }
            //$this->loadFooter();
        } catch(\Exception $e) {
            $error['response'] = $e->getMessage();
            //echo json_encode($error); exit;
            echo '<div class="p-3">
            <p>Error: '. $error['response'] .'</p>
            <a href="index.html" class="btn btn-default">Back <i class="fa fa-home"></i></a>
            </div>';
        }
    }

    public function loadFooter() {
        $fileinclude = $this->HOME_ROOT.'include/html-footer.php';
        if (file_exists($fileinclude)) {
            include_once $fileinclude;
       }
    }

    public function bodyHeader($MOD_URL,$TITLE, $ICON = '') {
        $fileinclude = $this->HOME_ROOT.'include/body-header.php';
        if (file_exists($fileinclude)) {
            include_once $fileinclude;
       }
    }

    /*
    $msg = array(
        'title' => '',
        'body' => '',
        'image' => ''
    )
    */

    function notif($m,$tipe='ALL',$cmd = 'send',$data=NULL,$pfx=NULL) {
		global $config;
		$PREFIX = $pfx ? $pfx : $this->config['APPS_NAME'];
		if ($tipe == 'ALL') { $topic = 'notif_'.md5($PREFIX); }
		else {
			$topic = strtolower($tipe).'_'.md5($PREFIX);
		}
		if (is_array($m)) {
			/**/
			if ($cmd == 'send') {
				return $this->fcm($m,$topic,'topic');
			} else {
				return $topic;
			}
			/**/
		} else {
			return $topic;
		}
	}

    function fcm($msg,$topicToken = 'test',$tipe ='topic') {
        //echo $this->config['HOME_DIR'];
        $data = array('done' => 0, 'response' => '');
        $project = $this->config['fcm_project_id'];
        // echo $project; exit;
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
                        "notification" => $msg
                    )
                );
            } else {
                $message = array(
                    "message" => array(
                        "token" => $topicToken,
                        "notification" => $msg
                    )
                );
            }
            $response = $httpClient->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", array('json' => $message));
		    return $response;
        } catch(\Exception $e) {
            $data['response'] = $e->getMessage();
            echo json_encode($data); exit;
        }
    }

    function __destruct() {

    }
}
<?php
namespace Koyabu\Webapi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
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
        //print_r($this->Headers); exit;
        $this->cekPHPversion();
    }

    /** 
     * Check PHP Version must in 8.1.x
    */
    function cekPHPversion() {
        // echo version_compare(PHP_VERSION, '8.0');
        if (version_compare(PHP_VERSION, '8.2') > 0) { 
            throw new \Exception(PHP_VERSION." PHP version not compatible, please use PHP 8.1.x", 1);
            exit;
        }
        if (version_compare(PHP_VERSION, '8.0') < 0) { 
            throw new \Exception(PHP_VERSION." PHP version not compatible, please use PHP 8.1.x", 1);
            exit;
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
                    $gg = explode("\\",trim($CALL,".php"));
                    throw new \Exception(implode("::",$gg)." not found", 1);
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
            if ($buttonHome and $BottomControllerHidden != true and $ButtonControllerHidden != true and $ControllerHidden != true and $HideController != true) { 
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

    function notif_to_inbox($m,$tipe,$pengirim='SYSTEM') {
        $params = $m;
        $params['message'] = $m;
        $g = $this->select("INSERT INTO t_member_inbox (tanggal, id_member, pengirim, pesan, params) 
        SELECT '".date("Y-m-d H:i:s")."', id, '{$pengirim}', '".$m['title']."', '". json_encode($params) ."' from t_member where tipe='{$tipe}'");
    }

    function save_to_inbox($msg,$id_member,$pengirim='SYSTEM') {
        $this->save(array(
            'tanggal' => date("Y-m-d H:i:s"),
            'id_member' => $id_member,
            'pengirim' => $pengirim,
            'pesan' => $msg['title'],
            'params' => json_encode($msg)
        ),'t_member_inbox');
    }

    function googleServicesJSON() {
        $google_json = $this->config['HOME_DIR'].'data/google-services.json';
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
            // echo json_encode($data); exit;
        }
    }

    function returnData($data = array()) {
        $data = $data ? $data :  array('done' => 0, 'response' => '');
        echo json_encode($data); exit;
    }

    function sendMail($option) {
        global $config;
    
        $mail = new PHPMailer(true);
        try {
            //Server settings
            // $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = $config['smtp_host'];                     //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = $config['smtp_user'];                     //SMTP username
            $mail->Password   = $config['smtp_pass'];                               //SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            // $mail->setFrom('info@example.com', 'Mailer');
            // $mail->addAddress('joe@example.net', 'Joe User');     //Add a recipient
            // $mail->addAddress('ellen@example.com');               //Name is optional
            // $mail->addReplyTo('info@example.com', 'Information');
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');

            //Attachments
            // $mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            // $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            //Content
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

    function __destruct() {

    }
}
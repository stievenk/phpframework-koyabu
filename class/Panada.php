<?php
namespace Koyabu\Webapi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Panada extends Form {

    public $USER = array();
    public $default_userTipeAllow = 'ALL';
    public $sesname = 'panada';

    function getConfig() {
        $g = $this->select("select * from z_config");
        while($t = $this->fetch($g)) {
            $this->config[trim($t['name'])] = trim($t['value']);
        }
    }

    function setSessionName($name) {
        $this->sesname = $name;
    }

    function setUserTipeAllow($data) {
        $this->setUserTipeAllow = $data;
    }

    function getUser($id = '') {
        $id = $id ? $id : $_SESSION[$this->sesname]['id'];
        $t = $this->get($id,'t_member');
        return $t;
    }

    function updateUser($id = '') {
        $id = $id ? $id : $_SESSION[$this->sesname]['id'];
        $params = array(
            'id' => $id,
            'lastlogin' => date("Y-m-d H:i:s"),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'lat' => $_REQUEST['lat'],
            'lng' => $_REQUEST['lng']
        );
        try {
            if ($id) {
                $id = $this->save($params,'t_member');
            }
            return $id;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    function loadPage() {
        if ($_REQUEST['logout']) {
            $this->getLogout();
        }
        $MOD_URL = "module&mod=".$_GET['mod'];
        $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
        $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : 'home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
        $RELOAD_URL = trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');
        if (!$_SESSION[$this->sesname]) {
            // echo $_GET['cmd'];
            $this->loginPage();
            // $this->loadLayout('home');
        } else {
            if ($_GET['call'] || $_GET['mod']) {
                $this->USER = $this->getUser();
                $this->updateUser();
                $_GET['mod'] = $_GET['mod'] ? $_GET['mod'] : $_GET['m'];
                $CALL = '';
                if ($_GET['mod']) {
                    $mod = $_GET['mod'];
                    $CALL = $this->HOME_ROOT . 'modules' . DIRECTORY_SEPARATOR . $mod . DIRECTORY_SEPARATOR;
                    if ($_GET['f']) { $CALL = $CALL . basename($_GET['f']) . '.php'; }
                    else { $CALL = $CALL . 'index.php'; }
                } else if ($_GET['call']) {
                    $CALL = $this->HOME_ROOT . 'call' . DIRECTORY_SEPARATOR . $_GET['call'] . '.php';
                }

                try {
                    if ($CALL != '') {
                        if (file_exists($CALL)) {
                            include_once $CALL;
                        } else {
                            throw new \Exception("{$CALL} not found", 1);
                        }
                    } else { 
                        $page = $_GET['page'] ? $_GET['page'] : 'home';
                        $this->loadLayout($page); 
                    }

                } catch(\Exception $e) {
                    $error['response'] = $e->getMessage();
                    //echo json_encode($error); exit;
                    // echo __FILE__ .' '.__LINE__;
                    echo '<div class="p-3">
                    <p>Error: '. $error['response'] .'</p>
                    <a href="./" class="btn btn-default">Back <i class="fa fa-home"></i></a>
                    </div>';    
                }
            } else {
                if ($_GET['page']) {
                    $this->loadLayout($_GET['page'],true);
                } else {
                    $this->loadLayout('home',false);
                }
                
            }
        }

    }

    // function loadLayout($include='') {
    //     $MOD_URL = "mod=".$_GET['mod'];
    //     $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
    //     $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : 'home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
    //     $RELOAD_URL = trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');
    //     $this->pageHeader();
    //     echo '<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">';
    //     echo '<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>';
    //     echo '<div class="app-wrapper">';
    //     include 'html/navbar.php';
    //     include 'html/sidebar.php';
    //     echo '<main class="app-main d-print-block" id="layout" style="margin-top:50px">';
    //     if ($include) { 
    //         include 'call/'.$include.'.php'; 
    //     }
    //     echo '</main>';
    //     echo '</div>';
    //     $this->pageBottom();
    // }

    function loadLayout($include='',$mod = false) {
        if ($mod == true) {
            $MOD_URL = "mod=".$_GET['mod'];
        } else {
            $MOD_URL = "module&mod=".$include;
        }
        $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
        $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : 'home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
        $RELOAD_URL = trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');
        $this->pageHeader();
        echo '<body class="layout-fixed sidebar-expand-lg bg-body-tertiary sidebar-collapse">';
        // echo '<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">';
        // echo '<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>';
        echo '<div class="app-wrapper">';
        include 'html/navbar.php';
        include 'html/sidebar.php';
        echo '<main class="app-main d-print-block" id="layout" style="margin-top:50px">';
        if ($include) { 
            if ($mod == true) {
                $f = $_GET['f'] ? $_GET['f'] : 'index';
                $fileinclude = 'modules/'.$include.'/'.$f.'.php';
                if (file_exists($fileinclude)) { include_once $fileinclude; } 
                else {
                    echo '<div class="p-3">
                <p>Error: '. $fileinclude.' not found</p>
                <a href="./" class="btn btn-default">Back <i class="fa fa-home"></i></a>
                </div>';   
                }
            } else {
                include_once 'call/'.$include.'.php'; 
            }
        }
        echo '</main>';
        echo '</div>';
        include 'html/footer.php';
        $this->pageBottom();
        // $d = file_get_contents('cache/a.txt');
        // file_put_contents('cache/a.txt',$d."\n".date("Y-m-d H:i:s"));
        
    }

    function loginPage() {
        if ($_GET['cmd'] == 'getLogin') {
            $this->getLogin();
        }
        $this->pageHeader();
        echo '<body class="login-page bg-body-secondary">';
        include 'html/login.php';
        $this->pageBottom();
    }

    function pageHeader() {
        include 'html/header.php';
    }

    function pageBottom() {
        include 'html/bottom.php';
    }

    function getLogin() {
        try {
            if (!$_POST['uname']) { throw new \Exception("Please enter your username", 1); }
            if (!$_POST['passwd']) { throw new \Exception("Please enter your password", 1); }
            $g = $this->select("select * from t_member where `username`='". $this->escape_string($_POST['uname']) ."' or `email`='". $this->escape_string($_POST['uname']) ."'");
            $t = $this->fetch($g);
            if ($t['id']) {
                if ($t['tipe'] == 'BANNED') {
                    throw new \Exception("Your account is BANNED", 1);
                }
                if (md5(trim($_POST['passwd'])) == $t['password']) {
                    $t['user_token'] = md5(uniqid().$t['id'].$_SERVER['REMOTE_ADDR']);
                    // $this->updateUserData($t['id'],$t['user_token'],date("Y-m-d H:i:s",strtotime("+1 week")));
                    $this->config['userTipeAllow'] = $this->config['userTipeAllow'] ? $this->config['userTipeAllow'] : $this->default_userTipeAllow;
                    $allowUser = explode("|",$this->config['userTipeAllow']);
                    if (in_array($t['tipe'],$allowUser) or $this->config['userTipeAllow'] == 'ALL') {
                        $data = $t;
                        $data['done'] = 1;
                        unset($t['password']);
                        $_SESSION[$this->sesname] = $t;
                    } else {
                        throw new \Exception("Anda tidak bisa mengakses fitur ini");
                    }
                } else {
                        throw new \Exception("Password tidak tepat!");
                    return false;
                }
            } else {
                throw new \Exception("Username tidak terdaftar", 1);
            }
        } catch (\Exception $e) {
            $data['response'] = $e->getMessage();
        }
        $this->returnData($data);
    }

    function logoutAndReload() {
        echo '<script>window.location.assign("?logout=1");</script>'; exit;
    }

    function getLogout() {
        session_destroy();
        header("location: ./");
        exit;
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
            return $data;
            // echo json_encode($data); exit;
        }
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
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            // $mail->Port       = 465;    
            if (preg_match("#gmail\.com#si",$config['smtp_host'])) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            }

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

    function returnData($data = array()) {
        $data = $data ? $data :  array('done' => 0, 'response' => '');
        echo json_encode($data); exit;
    }
}
?>
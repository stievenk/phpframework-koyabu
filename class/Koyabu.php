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

    function __construct($config) {
        $this->config = $config;
        $this->SQLConnection($config);
        $this->Headers = getallheaders();
        $this->Params = $_POST;
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
                throw new \Exception("Error: Invalid Username", 1);
            }
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            //echo json_encode(array('done' => 0, 'response' => $this->error)); exit;
            return false;
        }
    }

    public function isUserLogin($username) {
        try {
            if ($t = $this->getUserData($username)) {
                if ($t['tipe'] == 'BANNED') { 
                    throw new \Exception("Your account is BANNED", 1);
                }

                if ($t['token'] == $this->Headers['token']) {
                    $token_expire = strtotime($t['token_expire']);
                    if (date("U") < $token_expire) {
                        throw new \Exception("Token expired, silahkan login kembali", 1);
                    }
                    $this->updateUserData($t['id']);
                    return $t;
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

    /*
    $msg = array(
        'title' => '',
        'body' => '',
        'image' => ''
    )
    */
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
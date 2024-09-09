<?php
namespace Koyabu\Webapi;

class Panada extends Form {

    public $USER = array();

    function getConfig() {
        $g = $this->select("select * from z_config");
        while($t = $this->fetch($g)) {
            $this->config[trim($t['name'])] = trim($t['value']);
        }
    }

    function getUser($id = '') {
        $id = $id ? $id : $_SESSION['panada']['id'];
        $t = $this->get($id,'t_member');
        return $t;
    }

    function updateUser($id = '') {
        $id = $id ? $id : $_SESSION['panada']['id'];
        $params = array(
            'id' => $id,
            'lastlogin' => date("Y-m-d H:i:s"),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'lat' => $_REQUEST['lat'],
            'lng' => $_REQUEST['lng']
        );
        try {
            $id = $this->save($params,'t_member');
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
        if (!$_SESSION['panada']) {
            // echo $_GET['cmd'];
            $this->loginPage();
            // $this->loadLayout('home');
        } else {
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
                } else { $this->loadLayout('home'); }

            } catch(\Exception $e) {
                $error['response'] = $e->getMessage();
                //echo json_encode($error); exit;
                // echo __FILE__ .' '.__LINE__;
                echo '<div class="p-3">
                <p>Error: '. $error['response'] .'</p>
                <a href="./" class="btn btn-default">Back <i class="fa fa-home"></i></a>
                </div>';    
            }
           
        }

    }

    function loadLayout($include='') {
        $MOD_URL = "mod=".$_GET['mod'];
        $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
        $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : 'home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
        $RELOAD_URL = trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');
        $this->pageHeader();
        echo '<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">';
        echo '<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>';
        echo '<div class="app-wrapper">';
        include 'html/navbar.php';
        include 'html/sidebar.php';
        echo '<main class="app-main d-print-block" id="layout" style="margin-top:50px">';
        if ($include) { include 'call/'.$include.'.php'; }
        echo '</main>';
        echo '</div>';
        $this->pageBottom();
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
                    $data = $t;
                    $data['done'] = 1;
                    unset($t['password']);
                    $_SESSION['panada'] = $t;
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

    function returnData($data = array()) {
        $data = $data ? $data :  array('done' => 0, 'response' => '');
        echo json_encode($data); exit;
    }
}
?>
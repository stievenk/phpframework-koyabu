<?php
namespace Koyabu\Webapi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class KoyabuWeb extends Panada {

    function loadPage() {
        $this->getConfig();
        if ($_REQUEST['logout']) {
            $this->getLogout();
        }
        $MOD_URL = "module&mod=".$_GET['mod'];
        $SERVER_REQUEST_STR = parse_url($_SERVER['REQUEST_URI']);
        $REF_URL = $_REQUEST['ref'] ? $_REQUEST['ref'].'&ref='.$_REQUEST['call'] : 'home&'.str_replace('&ref='.$_REQUEST['call'],'',str_replace('call','ref',$SERVER_REQUEST_STR['query']));
        $RELOAD_URL = trim(preg_replace(array('#call=#','#app_version=(.+?)&#'),'',$SERVER_REQUEST_STR['query']),'&');

        if ($_GET['call'] || $_GET['mod']) {
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
        } else {
            if ($_GET['page']) {
                $this->loadLayout($_GET['page'],true);
            } else {
                $this->loadLayout('home',false);
            }
            
        }
        
        
    }

    
}

?>
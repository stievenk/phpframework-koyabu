<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';

$Client = new TarsiusClient($config);
// $Client->send('ServerInfo',['tes' => 'OK','tes2' => 'OK2']);
// $res = $Client->send('getClientInfo',['app_id' => 'girsa', 'app_secret' => 'I3xvpFlBve4xplTLzwxB73Yq2BT1X6V9XRlEi6Xo97V4nvno\/IGWh2OtQHhzdY63oZhGUwrI5W9BY2iIjNlU\/ws22IzcwrusgYE3v\/iAxmJqSU57Vqh6y0nO8ggN9Rcr']);
$params = ['app_id' => 'girsa', 'app_secret' => 'I3xvpFlBve4xplTLzwxB73Yq2BT1X6V9XRlEi6Xo97V4nvno\/IGWh2OtQHhzdY63oZhGUwrI5W9BY2iIjNlU\/ws22IzcwrusgYE3v\/iAxmJqSU57Vqh6y0nO8ggN9Rcr'];
$Headers = [];
$res = $Client->getServerInfo($Client->server_url,$Headers,[ 'command' => 'getClientInfo', 'params' => $params],'json');
print_r($res);
if ($res['status'] != 'ON') {
   echo 'Please contact your Administrator ('.$res['response'].')';
   exit;
}
// $Client->send('getSecretKey',['app_id' => 'girsa', 'password' => 'yohanes316']);
exit;

?>
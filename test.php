<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';

$API = new Koyabu($config);
// $t = $API->get(1,'t_member');
// $g = $Database->query("select * from t_member limit 1");
// $t = $Database->fetch_assoc($g);
//$t = $API->save(array('name' => 'tes', 'value' => date("U")),'z_config','INSERT');
// $t = $API->save(array('name' => 'tes', 'value' => date("U")),'z_config','UPDATE','primary_key');
// $t = $API->save(array('name' => 'tes', 'value' => date("U")),'z_config','REPLACE');
// $t = $API->delete(array('name' => 'tes'),'z_config',$data); 
// print_r($t);
// print_r($data);
// $g = $API->query("select * from t_member order by id desc limit 3");
// while($t = $API->fetch($g)) {
//     print_r($t);
// }
// print_r($API->getUserData('stievens'));
// $login = $API->isUserLogin('stieven');
// $update = $API->updateUserData(1);
// var_dump($update);


// fcm
// $msg = array(
//     'title' => 'test',
//     'body' => 'body test',
//     'image' => ''
// );
// var_dump($API->fcm($msg,'globalNotif'));

// Dropbox
// var_dump($API->save_dbx('test.txt'));


echo $API->error;
?>
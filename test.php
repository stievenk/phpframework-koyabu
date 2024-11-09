<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';

$API = new Koyabu($config);
// $r = $API->G2FA_genQRcode('HWM','stieven');
// $r = $API->G2FA_getCurrentOTP('23K74EPHPTVGZMPE');
// print_r($r);


$codes = array();
$start = 100;
$end = 200;
echo '<h3>QRCode</h3>';
    for($i=$start;$i<$end;$i++) {
        $code = $codes[$i] ? $codes[$i] : 'HWM'.date("ymd").str_pad($i,4,'0',STR_PAD_LEFT);
        $codes[$i] = $code;
        echo '<div style="float:left; width:2cm; margin:2px;">';
        echo '<img src="'.$API->QRcode($code,true).'" width="100%">';
        echo '<div style="font-family:arial; font-size:8px; text-align:center">'.$code.'</div>';
        echo '</div>';
        // echo '<div style="float:left; width:2cm; margin:2px;">';
        // echo '<img src="'.$API->QRcode($code,true).'" width="100%">';
        // echo '<div style="font-family:arial; font-size:8px; text-align:center">'.$code.'</div>';
        // echo '</div>';
    }


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


// $prov = json_decode(file_get_contents('province.txt'),true);
// $city = json_decode(file_get_contents('city.txt'),true);

// print_r($city['rajaongkir']['results']);

// foreach($city['rajaongkir']['results'] as $v) {
    
    // $API->save(array(
    //     'id' => $v['province_id'],
    //     'nama' => $v['province']
    // ),'ro_province');
    // $API->query("insert into ro_province set id='{$v['province_id']}', nama='{$v['province']}'");
    // $API->query("insert into ro_city set id='{$v['city_id']}', nama='{$v['city_name']}', tipe='{$v['type']}', id_provinsi='{$v['province_id']}', kodepos='{$v['postal_code']}'");
    // print_r($v);
// }
echo $API->error;


// $ONGKIR = new RajaOngkir($config);
// // print_r($ONGKIR->getProvince());
// // print_r($ONGKIR->getCity());
// $origin=501;
// $destination=114;
// $weight=17000;
// $courier='jne';
// print_r($ONGKIR->getCost($origin,$destination,$weight,$courier));

$SSL = new SSLEncrypt();
$SSL->data_encode('test');
?>
<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';
$google2fa = new \PragmaRX\Google2FA\Google2FA();

$API = new Koyabu($config);

// $user = [];
// $user['google2fa_secret'] = $google2fa->generateSecretKey();
// $user['email'] = 'stieven.kalengkian@gmail.com';

// 23K74EPHPTVGZMPE
// print_r($user);
// $g2faUrl = $google2fa->getQRCodeUrl(
//     'HWM',
//     $user['email'],
//     $user['google2fa_secret']
// );
// echo $g2faUrl;
// $writer = new Writer(
//     new ImageRenderer(
//         new RendererStyle(400),
//         new ImagickImageBackEnd()
//     )
// );
// $qrcode_image = $API->QRcode($g2faUrl);
$currentOTP = $google2fa->getCurrentOtp('23K74EPHPTVGZMPE');
echo $currentOTP;
?>
<!-- <img src="<?php echo $qrcode_image; ?> " width="300" /> -->
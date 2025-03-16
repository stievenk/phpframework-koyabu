<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';

$config['smtp_host'] = '';
$config['smtp_port'] = '465';
$config['smtp_user'] = '';
$config['smtp_pass'] = '';
$config['SITE_NAME'] = 'Test Email';

$API = new KoyabuAPI($config);
$option = [
   'to' => 'stieven.kalengkian@gmail.com',
   'from' => ['email' => $config['smtp_user'] , 'name' => $config['SITE_NAME']],
   'subject' => 'Email percobaan',
   'body' => 'Terima Kasih'
];
if (!$API->sendMail($option)) {
   echo $API->error;
}
?>
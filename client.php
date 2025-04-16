<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';

$Client = new TarsiusClient($config);
$Client->setServerURL('https://app.photoboothmanado.com/');
exit;

?>
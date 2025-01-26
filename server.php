<?php
namespace Koyabu\Webapi;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
require_once  $MASTER_PATH . 'autoload.php';

$Server = new TarsiusServer($config);

exit;

?>
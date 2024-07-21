<?php
$MASTER_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'vendor/';

$config = [];
$config['APPS_NAME'] = 'KOYABU PHP FRAMEWORK';
$config['HOME_DIR'] = __DIR__ . DIRECTORY_SEPARATOR;

$config['mysql'] = array(
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'data' => 'test'
);

// FCM - Firebase Message
$config['fcm_project_id'] = '';

// Dropbox
$config['dropbox']['app_key'] = '';
$config['dropbox']['app_secret'] = '';
$config['dropbox']['refresh_token'] = '';
$config['dropbox']['access_token'] = ''; 
$config['dropbox']['home_dir'] = str_replace(" ","_",$config['APPS_NAME']);

// Midtrans
/* MidTrans Config */
$config['midtrans']['production'] = false;
$config['midtrans']['merchantID'] = '';

if ($config['midtrans']['production']) {
	$config['midtrans']['url'] = 'https://app.midtrans.com/';
	$config['midtrans']['clientKey'] = '';
	$config['midtrans']['serverKey'] = '';
} else {
	$config['midtrans']['url'] = 'https://app.sandbox.midtrans.com/';
	$config['midtrans']['clientKey'] = '';
	$config['midtrans']['serverKey'] = '';
}
?>
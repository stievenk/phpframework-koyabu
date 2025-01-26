<?php
namespace Koyabu\Webapi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
// use GuzzleHttp\Exception\RequestException;
// use GuzzleHttp\Exception\ClientException;
// use GuzzleHttp\Exception\ServerException;
// use GuzzleHttp\Exception\ConnectException;

class TarsiusClient extends Form {

   public $server_url = 'http://localhost/projects/Android-Apps/Android_12-13/CS_CONTROLMANAGER/web/api/';

   function __construct($config) {
      parent::__construct($config);
   }
   function setServerURL($url) {
      $this->server_url = $url;
   } 

   function send($command,$params=[]) {
      $client = new Client();
      $res = $client->request('POST',$this->server_url,array(
         'form_params' => array('command' => $command, 'params' => $params)
      ));
      $data = $res->getBody();
      return json_decode($data,true);
      // print_r($data);
   }

}
?>
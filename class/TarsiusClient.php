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

class TarsiusClient {

   public $server_url = 'http://localhost/projects/Android-Apps/Android_12-13/CS_CONTROLMANAGER/web/api/';
   private $URL = 'https://app.photoboothmanado.com/';  

   function __construct(public $config) {
      // parent::__construct($config);
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

   public function getStatus() {
      if (!$this->config['application']) {
         echo 'Invalid Application Config!';
         exit;
      }
      $params = ['command' => 'getStatus', 'data' => $this->config['application']];
      $res = $this->run($this->URL,$params);
      // echo $res;
      $s = json_decode($res,true);


      if ($s['status'] == 'ON') {
          return true;
      } else {
         $this->errorInfo($s);
         return false;
      }
   }

   public function requestKey() {
      $params = ['command' => 'requestKey', 'data' => $this->config['application']];
      return json_decode($this->run($this->URL.'',$params),true);
   }

   public function run($URL,$data) {
      $client = new \GuzzleHttp\Client();
      $response = $client->request('POST', $URL,[ \GuzzleHttp\RequestOptions::JSON => $data , 'timeout' => 30 ]);
      return $response->getBody();
   }

   public function errorInfo($s) {
     echo '<div style="padding:15px"><h1>Server Error!</h1><p>Mohon maaf layanan aplikasi tidak bisa digunakan silahkan hubungi Administrator anda ('.$s['response'].')</p></div>';
     exit;
   }

}
?>
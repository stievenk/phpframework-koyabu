<?php
namespace Koyabu\Webapi;
use GuzzleHttp\Client;

class TarsiusClient {
   private $URL = 'https://app.photoboothmanado.com/';  //'http://localhost/projects/CENTERSTAGE/web/api/'; 

   public function __construct(public $config) {
      
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
         $this->error = $s['response'];
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
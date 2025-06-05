<?php
namespace Koyabu\Webapi;
//this class using for https://github.com/stievenk/wa-gateway
class WAgateway {

   public $URL = 'http://localhost';
   public $PORT = 5001;
   public $WebURI = '';

   function __construct($URL = '',$PORT = 5001) {
      if (isset($URL)) { $this->URL = $URL; }
      if (isset($PORT)) { $this->PORT = $PORT; }
      $this->WebURI = $this->URL . ':' . $this->PORT;
   }

   function startSession($session_name) {
      $URL = $this->WebURI . '/session/start?session='.$session_name;
      return $this->run($URL);
   }

   function send($session_name,$text,$to,$isGroup = false) {
      $URL = $this->WebURI . '/message/send-text';
      $data = [
         'session' => $session_name,
         'text' => $text,
         'to' => $to,
         'is_group' => $isGroup
      ];
      return $this->runPost($URL,$data);
   }

   function sendImage($session_name,$text,$to,$image_url,$isGroup = false) {
      $URL = $this->WebURI . '/message/send-image';
      $data = [
         'session' => $session_name,
         'text' => $text,
         'to' => $to,
         'image_url' => $image_url,
         'is_group' => $isGroup
      ];
      return $this->runPost($URL,$data);
   }

   function sendDocument($session_name,$text,$to,$document_url,$document_name='file.pdf',$isGroup = false) {
      $URL = $this->WebURI . '/message/send-document';
      $data = [
         'session' => $session_name,
         'text' => $text,
         'to' => $to,
         'document_url' => $document_url,
         'document_name' => $document_name,
         'is_group' => $isGroup
      ];
      return $this->runPost($URL,$data);
   }  

   function run($URL) {
      $client = new \GuzzleHttp\Client();
      $response = $client->request('GET', $URL, [ 'timeout' => 30]);
      return $response->getBody()->getContents();
   }

   function runPost($URL,$data) {
      $client = new \GuzzleHttp\Client();
      $response = $client->request('POST', $URL,[ \GuzzleHttp\RequestOptions::JSON => $data , 'timeout' => 30 ]);
      return $response->getBody()->getContents();
   }
}
?>
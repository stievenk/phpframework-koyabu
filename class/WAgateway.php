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
      $URL = $this->WebURI . '/message/send-text?session='.$session_name;
      $URL .= '&text='.urlencode($text);
      $URL .= '&to='.urlencode($to);
      if ($isGroup == true) {
         $URL .= '&is_group=true';
      }
      return $this->run($URL);
   }

   function sendImage($session_name,$text,$to,$image_url,$isGroup = false) {
      $URL = $this->WebURI . '/message/send-image?session='.$session_name;
      $URL .= '&text='.urlencode($text);
      $URL .= '&to='.urlencode($to);
      $URL .= '&image_url='.urlencode($image_url);
      if ($isGroup == true) {
         $URL .= '&is_group=true';
      }
      return $this->run($URL);
   }

   function run($URL) {
      $client = new \GuzzleHttp\Client();
      $response = $client->request('GET', $URL);
      return $response->getBody();
   }
}
?>
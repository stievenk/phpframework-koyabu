<?php
namespace Koyabu\Webapi;
class TarsiusServer extends Form {

   private $FIRSTKEY = 'Tarsius';
   private $SECONDKEY = 'Manado';
 
   function __construct($config) {
      parent::__construct($config);
      // print_r($_SERVER['REQUEST_METHOD']);
      // print_r($_POST);
      try {
         if (method_exists($this,$_POST['command'])) { 
               $this->{$_POST['command']}($_POST['params']);
         } else {
            throw new \Exception('Command not found');
         }
      }  catch (\Exception $e) {
               echo 'Caught exception: ',  $e->getMessage(), "\n";
      }
   }

   function returnData ($data) {
      echo json_encode($data);
   }
   
   function ServerInfo($params=[]) {
      // print_r($_SERVER);
      // print_r($params);
      echo json_encode($_SERVER);
   } 

   function getSecretKey($params) {
      try {
         if (md5($params['password']) !== md5('yohanes316')) {
            throw new \Exception('Invalid password ');
         }
         $t  = $this->get($params['app_id'],'cs_client','app_id');
         if (!$t['id']) {
            throw new \Exception('App ID not found');
         }
         $SSL = new SSLEncrypt($this->FIRSTKEY,$this->SECONDKEY);
         $r = $SSL->data_encode($t['app_id'].$t['id']);
         $this->returnData(['app_secret' => $r, 'done' => 1]); 
      } catch (\Exception $e) {
         $this->returnData([ 'error' => $e->getMessage(), 'done' => 0]);
      }
      
   }

   function validateClient($params) {
      $t  = $this->get($params['app_id'],'cs_client','app_id');
      $SSL = new SSLEncrypt($this->FIRSTKEY,$this->SECONDKEY);
      $t['decode'] = $SSL->data_decode($params['app_secret']);
      // $t['app_secret'] = $SSL->data_encode($t['app_id'].$t['id']);
      // return $t;
      try {
         if (!$t['id']) {
            throw new \Exception("APP ID not found!");
         }
         if ($t['decode'] == $t['app_id'].$t['id']) {
            return $t;
         } else {
            throw new \Exception('Invalid App secret');
         }
      } catch (\Exception $e) {
         // echo 'Caught exception: ',  $e->getMessage(), "\n";
         return [ 'error' => $e->getMessage(), 'done' => 0];
      }
   }

   function getClientInfo($params=[]) {
      // $t  = $this->get($params['app_id'],'cs_client','app_id');
      $t = $this->validateClient($params);
      switch($t['status']) {
         case 'ON' : $t['response'] = 'Client authorized'; break;
         case 'WAIT' : $t['response'] = 'Client waiting for approval'; break;
         case 'OFF' : $t['response'] = 'Client not authorized'; break;
         case 'SUSPEND' : $t['response'] = 'Client suspended'; break;
         case 'BLOCK' : $t['response'] = 'Client blocked'; break;
         case 'CANCEL' : $t['response'] = 'Client canceled'; break;
         default : $t['response'] = 'Client not found'; break;
      }
      $t['done'] = 1;
      echo json_encode($t);
   }

   function billingProcess($client_id) {

   }
}
?>
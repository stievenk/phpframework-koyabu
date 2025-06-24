<?php
namespace Koyabu\Webapi;
use GuzzleHttp\Client;

class RajaOngkir {

   public $URL = 'https://rajaongkir.komerce.id/api/v1/';
   public $sandbox_URL = 'https://api-sandbox.collaborator.komerce.id/';
   public $prod_URL = 'https://api.collaborator.komerce.id/';
   public $Komship;

   public function __construct(public $api_key, public $production = false) {
      $this->Komship = $production == true ? $this->prod_URL : $this->sandbox_URL;
   }

   public function komship_searchDest($keyword) {
      $endpoint = 'tariff/api/v1/destination/search?keyword='.$keyword;
      $result = $this->httpClient_komship($this->Komship . $endpoint,'GET',$params);
      echo $result;
   }

   public function calcDomesticCost($params,$int = 0) {
      try {
         $endpoint = $int == 1 ? 'calculate/international-cost' : 'calculate/domestic-cost';
         $result = $this->httpClient($this->URL . $endpoint,'POST',$params);
      } catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return json_decode($result,true);
   }

   public function trackingBill($params) {
      try {
         $endpoint = 'track/waybill';
         $result = $this->httpClient($this->URL . $endpoint,'POST',null,$params);
      } catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return json_decode($result,true);
   }

   public function searchDest($dest,$start = 0,$int = 0) {
      try {
         $endpoint = $int == 1 ? 'destination/international-destination' : 'destination/domestic-destination';
         $result = $this->httpClient($this->URL . $endpoint,'GET',null,['search' => $dest, 'limit' => 1000, 'offset' => $start]);
         if (!is_string($result)) {
            print_r($result);
            throw new \Exception("Invalid data");
         }
         // echo $result;
      }  catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return json_decode($result,true);
   }

   public function httpClient($url,$method = 'GET',$data = '', $query = '') {
      try {
         $s = [
            'headers' => [
            'content-type' => 'application/x-www-form-urlencoded',
            'key' => $this->api_key,
            ],
            'timeout' => 30
         ];
         if (is_array($data)) {
            $s['form_params'] = $data;
         }
         if (is_array($query)) {
            $s['query'] = $query;
         }

         $client = new \GuzzleHttp\Client();
         $response = $client->request($method, $url, $s);
         // echo $response->getBody()->getContents();
         return $response->getBody()->getContents();
      } catch (\Exception $e) {
         return json_encode([ 'response' => $e->getMessage(), 'status' => 400 ]); 
      }
   }

   public function httpClient_komship($url,$method = 'GET',$data = '', $query = '') {
      try {
         $s = [
            'headers' => [
            // 'content-type' => 'application/x-www-form-urlencoded',
            'x-api-key' => $this->api_key,
            ],
            'timeout' => 30
         ];
         if (is_array($data)) {
            $s['form_params'] = $data;
         }
         if (is_array($query)) {
            $s['query'] = $query;
         }

         $client = new \GuzzleHttp\Client();
         $response = $client->request($method, $url, $s);
         // echo $response->getBody()->getContents();
         return $response->getBody()->getContents();
      } catch (\Exception $e) {
         return json_encode([ 'response' => $e->getMessage(), 'status' => 400 ]); 
      }
   }

}
?>
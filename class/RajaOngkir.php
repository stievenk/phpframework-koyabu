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

   public function calcShipCost($data) {
      try {
         $endpoint = 'tariff/api/v1/calculate';
         $params = [
            'shipper_destination_id' => $data['id_pengirim'],
            'receiver_destination_id' => $data['id_penerima'],
            'weight' => (float) $data['berat'] ?? 1,
            'item_value' => $data['harga'],
            'cod' => ($data['cod'] ?? 'no')
         ];
         if ($params['koord_pengirim']) { $params['origin_pin_point'] = $params['koord_pengirim']; }
         if ($params['koord_penerima']) { $params['destination_pin_point'] = $params['koord_penerima']; }
         print_r($params);
         $result = json_decode($this->httpClient_komship($this->Komship . $endpoint,'GET',$params),true);
      } catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return $result;
   }

   public function komship_searchDest($keyword) {
      try {
         $endpoint = 'tariff/api/v1/destination/search?keyword='.$keyword;
         $result = json_decode($this->httpClient_komship($this->Komship . $endpoint,'GET',$params),true);
      } catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return $result;
   }

   public function calcCost($data,$int = 0) {
      try {
         $endpoint = $int == 1 ? 'calculate/international-cost' : 'calculate/domestic-cost';
         $params = [
            'origin' => $data['id_pengirim'],
            'destination' => $data['id_penerima'],
            'weight' => (float) $data['berat'] ?? 1,
            'courier' => $data['kurir'] ?? 'jne:jnt',
            'price' => 'lowest'
         ];
         //jne:sicepat:ide:sap:jnt:ninja:tiki:lion:anteraja:pos:ncs:rex:rpx:sentral:star:wahana:dse
         $result = json_decode($this->httpClient($this->URL . $endpoint,'POST',$params),true);
      } catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return $result;
   }

   public function trackingBill($params) {
      try {
         if (!isset($params['awb'])) { throw new \Exception("AWB not found"); }
         if (!isset($params['courier'])) { throw new \Exception("Courier not found"); }
         $endpoint = 'track/waybill';
         $result = json_decode($this->httpClient($this->URL . $endpoint,'POST',null,$params),true);
      } catch (\Exception $e) {
         $result = [
            'status' => 400,
            'message' => $e->getMessage()
         ];
      }
      return $result;
   }

   public function searchDest($dest,$start = 0,$int = 0) {
      try {
         $endpoint = $int == 1 ? 'destination/international-destination' : 'destination/domestic-destination';
         $result = json_decode($this->httpClient($this->URL . $endpoint,'GET',null,['search' => $dest, 'limit' => 1000, 'offset' => $start]),true);
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
      return $result;
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
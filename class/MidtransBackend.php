<?php
namespace Koyabu\Webapi;

class MidtransBackend {

    public $api_url;
    public $server_key;
    public $client;

    function __construct() {
        $this->client = new \GuzzleHttp\Client();
    }

    public function setApiURL($api_url) {
        $this->api_url = $api_url;
    }

    public function setServerKey($server_key) {
        $this->server_key = $server_key;
    }

    public function getOrderStatus($order_id) {
        $response = $this->client->request('GET', $this->api_url.'v2/'.$order_id.'/status', [
            'headers' => [
                'accept' => 'application/json',
                'authorization' => 'Basic '. base64_encode($this->server_key)
            ],
            ]);
        $result = json_decode($response->getBody(),true);
        return $result;
    }

}
?>
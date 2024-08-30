<?php
namespace Koyabu\Webapi;

class MidtransBackend {

    public $api_url;
    public $server_key;
    public $client;
    public $config;

    function __construct($config='') {
        $this->client = new \GuzzleHttp\Client();
        $this->config = $config;
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

    public function signature_key(array $data) : string {
        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serverKey = $this->config['midtrans']['serverKey'];
        $input = $orderId.$statusCode.$grossAmount.$serverKey;
        $signature = openssl_digest($input, 'sha512');
        return $signature;
    }

}
?>
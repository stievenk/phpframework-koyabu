<?php
namespace Koyabu\Webapi;

class RajaOngkir {

    public $config;
    public $Params = array();
    public $token_expire = "+1 weeks";
    public $Headers;
    public $HOME_ROOT = '';
    public $USER = array();
    public $base_uri = 'https://api.rajaongkir.com/starter';

    function __construct($config) {
        $this->config = $config;
        $this->Params = $_POST;
        $this->HOME_ROOT = $this->config['HOME_DIR'] ? $this->config['HOME_DIR'] : '';
        // print_r($this->Headers); exit;
    }


    function getProvince($id='') {
        $url = $this->base_uri . '/province';
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET',$url,array(
            'headers' => array('key' => $this->config['rajaongkir']['key']),
            'query' => array( 'id' => $id)
        ));
        // echo $res->getBody();
        $result = $res->getBody();
        // file_put_contents('province.txt',$result);
        return json_decode($result,true);
    }

    function getCity($province = '',$id='') {
        $url = $this->base_uri . '/city';
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET',$url,array(
            'headers' => array('key' => $this->config['rajaongkir']['key']),
            'query' => array( 'id' => $id, 'province' => $province)
        ));
        ///echo $res->getBody();
        $result = $res->getBody();
        // file_put_contents('city.txt',$result);
        return json_decode($result,true);
    }

    function getCost($origin,$destination,$weight,$courier) {
        $url = $this->base_uri . '/cost';
        $client = new \GuzzleHttp\Client();
        $res = $client->request('POST',$url,array(
            'headers' => array(
                'key' => $this->config['rajaongkir']['key'],
                'content-type' => 'application/x-www-form-urlencoded'),
            'form_params' => array( 'origin' => $origin, 'destination' => $destination, 'weight' => $weight, 'courier' => $courier)
        ));
        $result = $res->getBody();
        return json_decode($result,true);
    }

    function __destruct() {

    }
}
?>
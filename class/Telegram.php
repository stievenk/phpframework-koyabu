<?php
namespace Koyabu\Webapi;

class Telegram {
    public  $ch;
    public  $token = '';
    public  $botname = '';
    public  $headers = array();

    function __construct($token,$botname) {
        $this->token = $token;
        $this->botname = $botname;
    }

    public function init($url) {
		$this->ch = curl_init($url);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
	}
	
	public function post() {
		//curl_setopt($ch, CURLOPT_VERBOSE, 1); // debug
        $response = curl_exec($this->ch);
        $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        curl_close($this->ch);
		$data = json_decode($response,true);
		$data['http_code'] = $http_code;
		return $data;
	}

    public function send($msg,$contact,$parse_mode = 'Markdown') {
        $url  = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $this->init($url);
        $data = array(
            'chat_id' => $contact,
            'text' => $msg,
            'parse_mode' => $parse_mode
        );
        curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
        return $this->post();
    }

    public function getUpdates($offset=0,$limit = 100) {
        $url  = "https://api.telegram.org/bot{$this->token}/getUpdates";
        $this->init($url);
        $data = array(
            'offset' => $offset,
            'limit' => $limit
        );
        curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
        return $this->post();
    }

    public function setWebhook($urls) {
        $url  = "https://api.telegram.org/bot{$this->token}/setWebhook?url=".$urls;
        $this->init($url);
        //curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
        return $this->post();
    }

    public function deleteWebhook($urls) {
        $url  = "https://api.telegram.org/bot{$this->token}/deleteWebhook";
        $this->init($url);
        $data = array(
            'url' => $urls
        );
        curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
        return $this->post();
    }

    public function getWebhookInfo($urls='') {
        $url  = "https://api.telegram.org/bot{$this->token}/getWebhookInfo?url=".$urls;
        $this->init($url);
        //curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
        return $this->post();
    }

    public function getMethod($method,$data = array()) {
        $url  = "https://api.telegram.org/bot{$this->token}/".$method;
        $this->init($url);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
        
    }

   public function sendPhoto($chat_id,$filename,$caption='',$parse_mode = 'Markdown') {
        $url  = "https://api.telegram.org/bot{$this->token}/sendPhoto?chat_id=".$chat_id;

        if (preg_match("#^http#si",$filename)) {
            $filename = $filename;
        } else { 
            $filename = new CURLFile(realpath($filename));
            $this->headers = array(
                "Content-Type:multipart/form-data"
            );
        }
        $post_fields = array(
            'chat_id'   => $chat_id,
            'photo'     => $filename,
            'caption' => $caption,
            'parse_mode' => $parse_mode
        );

        $this->init($url);
        
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields); 
        return $this->post();
   }
}
?>
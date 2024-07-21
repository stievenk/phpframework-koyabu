<?php
namespace Koyabu\Webapi;

class Dropbox {
	var $token = '';
	var $ch;
	var $headers = array();
	var $home_dir = '/';
	var $rToken;
	
	function __construct($token='',$rToken='') {
		global $config;
		if ($token != '') {
			$this->token = $token;
		}
		if ($rToken) {
			$d = $this->refreshToken($rToken);
			$this->token = $d['access_token'];
		} else if ($config['dropbox']['refresh_token']) {
			$d = $this->refreshToken($config['dropbox']['refresh_token']);
			$this->token = $d['access_token'];
		}
		//$this->token = 'sl.BSt1pu4_BUhG9kMNcTnpAVrr0SY_7XayRIbzsHBzvw044mwzPKBcgEhXxPbJBtcK5a5wjQDXoKPgHAGu9uiycghfZL-gKccoBX-vvGoSh929CbZ56JOPtenY2WCi1Kd6lv60GUo';
	}
	
	function setRefreshToken($token) {
		$this->rToken = $token;
		$d = $this->refreshToken($this->rToken);
		$this->token = $d['access_token'];
	}
	
	function set_home_dir($dir) { $this->home_dir = $dir; }
	
	function init($url) {
		$this->ch = curl_init($url);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
	}
	
	function post() {
		//curl_setopt($ch, CURLOPT_VERBOSE, 1); // debug
        $response = curl_exec($this->ch);
        $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        curl_close($this->ch);
		$data = json_decode($response,true);
		$data['http_code'] = $http_code;
		return $data;
	}
	
	function delete($filename) {
		$data = array("path" => $this->home_dir. $filename);
		$this->headers = array(
			'Authorization: Bearer '. $this->token,
            'Content-Type: application/json'
			);
		$this->init('https://api.dropboxapi.com/2/files/delete_v2');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,json_encode($data)); 
		return $this->post();
	}
	
	function upload($filename,$mode='add',$location='') {
		$path = $filename;
		$location = $location ? $location : $this->home_dir;
        $fp = fopen($path, 'rb');
        $filesize = filesize($path);
		//file_put_contents('cache/d.txt',$this->token);
		//echo $this->token; exit;
		$this->headers = array(
			'Authorization: Bearer '. $this->token,
            'Content-Type: application/octet-stream',
			'Dropbox-API-Arg: '.
            json_encode(
                array(
                    "path"=> $location . basename($filename),
                    "mode" => $mode,
                    "autorename" => true,
                    "mute" => true
                )
            )
			);
		$this->init('https://content.dropboxapi.com/2/files/upload');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, fread($fp, $filesize));
		fclose($fp);
        return $this->post();
	}
	
	function create_shared_link($filename) {
		$data = array(
						"path" => $this->home_dir. $filename,
						'settings' => array( 'requested_visibility' => 'public' )
					);
		$this->headers = array(
			'Authorization: Bearer '. $this->token,
            'Content-Type: application/json'
			);
		$this->init('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,json_encode($data)); 
		return $this->post();
	}
	
	function get_shared_link($filename) {
		$data = array('path' => $this->home_dir . $filename);
		$this->headers = array(
			'Authorization: Bearer '. $this->token,
			'Content-Type: application/json'
			);
		$this->init('https://api.dropboxapi.com/2/sharing/list_shared_links');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,json_encode($data)); 
		return $this->post();
	}
	
	function get_shared_link_file($url) {
		$data = array( 'url' => $url );
		$this->headers = array(
			'Authorization: Bearer '. $this->token,
			'Content-Type: application/json'
			);
		$this->init('https://api.dropboxapi.com/2/sharing/get_shared_link_metadata');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,json_encode($data));
		return $this->post();
	}
	
	function getAccessToken($code='') {
		global $config;
		$APP_KEY = $config['dropbox']['app_key'];
		$url = 'https://www.dropbox.com/oauth2/authorize?client_id='.$APP_KEY.'&token_access_type=offline&response_type=code&scope=files.metadata.write files.content.write sharing.write file_requests.write';
		if ($code == '') {
			return "Please go to: <a href=\"".$url."\">{$url}</a>";
		} else {
			$this->init('https://api.dropboxapi.com/oauth2/token');
			curl_setopt($this->ch, CURLOPT_USERPWD, "{$config['dropbox']['app_key']}:{$config['dropbox']['app_secret']}");
			$data = array('code' => $code, 
						'grant_type' => 'authorization_code'
					);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
			return $this->post();
		}
	}

	function refreshToken($refresh_token) {
		global $config;
		
		$this->init('https://api.dropboxapi.com/oauth2/token');
		curl_setopt($this->ch, CURLOPT_USERPWD, "{$config['dropbox']['app_key']}:{$config['dropbox']['app_secret']}");
		$data = array(
			'grant_type' => 'refresh_token',
			'refresh_token' => $refresh_token
		);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,http_build_query($data));
		return $this->post();
	}
}
?>
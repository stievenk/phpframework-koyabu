<?php
namespace Koyabu\Webapi;
class RSSparser {
	
	var $HTML = '';
	var $url = '';
	var $cache = 0;
	var $cacheTime = 1; // in hour
	private $HOME_DIR = '';
	
	function __construct($URL,$cache=0,$config=[]) {
		$this->url = $URL;
		$this->cache = $cache;
		$this->config = $config;
		$this->HOME_DIR = $this->config['HOME_DIR'] . DIRECTORY_SEPARATOR;
	}
	
	function get() {
		if ($this->cache == 1) {
			$fn = $this->HOME_DIR.'cache/rss_'.md5($this->url).'.xml';
			if (!file_exists(dirname($fn))) { 
				@mkdir($this->HOME_DIR.'cache/'); 
			}
			$mt = date("U") - filemtime($fn);
			if (!file_exists($fn) || $mt >= (3600 * $this->cacheTime) || filesize($fn) <= 1) {
				$this->HTML = @file_get_contents($this->url);
				@file_put_contents($fn,$this->HTML);
			} else {
				$this->HTML = @file_get_contents($fn);
			}
		} else {
			$this->HTML = @file_get_contents($this->url);
		}
		
		//$this->HTML = @file_get_contents($this->url);
		return $this->HTML;
	}
	
	
	function parse($datas='') {
		$datas = $datas ? $datas : $this->HTML;
		preg_match_all("#<item>(.+?)<\/item>#si",$datas,$r);
		for($i=0;$i<count($r[1]);$i++) {
			if(preg_match_all("#<(title|link|description|pubDate|image)>(.+?)<\/(title|link|description|pubDate|image)>#si",$r[1][$i],$x)) {
				for($j=0;$j<count($x[1]);$j++) {
					$x[2][$j] = str_replace(array('<![CDATA[',']]>'),"",$x[2][$j]);
					$data[$i][$x[1][$j]]=htmlspecialchars_decode($x[2][$j]);
				}
			} 
			if (preg_match("#<guid.+?>(.+?)<\/guid>#si",$r[1][$i],$x)) {
				$data[$i]['guid']=$x[1];				
			}
			if (preg_match("#<geo:lat>(.+?)<\/geo:lat>#si",$r[1][$i],$x)) {
				$data[$i]['geolat']=$x[1];				
			}
			if (preg_match("#<geo:long>(.+?)<\/geo:long>#si",$r[1][$i],$x)) {
				$data[$i]['geolong']=$x[1];				
			}
			if (preg_match("#<content:encoded>(.+?)<\/content:encoded>#si",$r[1][$i],$x)) {
				preg_match("#<!\[CDATA\[(.+?)\]\]>#si",$x[1],$gg);
				$data[$i]['content']=$gg[1];
			}
			$data[$i]['content'] = $data[$i]['content'] ? $data[$i]['content'] : $data[$i]['description'];
		}
		//print_r($data);
		return $data;
	}
	
	function show($limit=10) {
		$items = $this->parse($this->get());
		for($x=0;$x<$limit;$x++) {
			$items[$x]['pubDate'] = $items[$x]['pubDate'] ? $items[$x]['pubDate'] : date("Y-m-d H:i:s");
			$date = date("U",strtotime($items[$x]['pubDate']));
			$news[$date] = array(
				'date' => date("d M Y H:i",$date),
				'title' => $items[$x]['title'],
				'link' => $items[$x]['link'],
				'description' => $items[$x]['description'],
				'content' => $items[$x]['content']
			);
		}
		return $news;
	}
	
	function __destruct() {
	}
}
?>
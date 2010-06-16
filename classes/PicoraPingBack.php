<?php

//@@PicoraView PicoraView::addMethod('pingbackHeader',array('PicoraPingBack','viewHelper'));
//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraPingBack',$path.'/classes/PicoraPingBack.php');
//@@PicoraXMLRPC PicoraXMLRPC::addMethod(array('PicoraPingBack','respondToPingRequest'),'pingback.ping',array('string','string','string'),'Registers a pingback, returning a fault code or the string "PingBack recorded."');
//@@PicoraXMLRPC PicoraXMLRPC::addMethod(array('PicoraPingBack','respondToGetPingsRequest'),'pingback.extensions.getPingbacks',array('array','string'),'Returns an array of urls that have pinged the given url, or a faultcode if the url does not exist or is not a valid pingback target.');
//@@MySQL CREATE TABLE `pings` (`id` INT(8) NOT NULL auto_increment, `name` VARCHAR(255), `local` VARCHAR(255), `remote` VARCHAR(255), `created` DATETIME, PRIMARY KEY (`id`))
//@@SQLite CREATE TABLE pings (id INTEGER PRIMARY KEY,name,local,remote,created)

/**
 * @introduction Represents a recorded Pingback.
 */
class PicoraPing extends PicoraActiveRecord {
	const TABLE_NAME = 'pings';
	
	public function beforeCreate(){
		$this->created = date('Y-m-d H:i:s',time());
	}
	
	static public function findAllByLocal($local){
		return PicoraActiveRecord::findAll('PicoraPing',array('where'=>'local = \''.PicoraActiveRecord::escape($local).'\' OR local = \''.PicoraActiveRecord::escape($local).'/\''));
	}
}

/**
 * @introduction An implementation of the Pingback specification.
 */
class PicoraPingBack {
	static public function viewHelper(){
		return '<link rel="pingback" href="'.PicoraDispatcher::getUrl(array('PicoraXMLRPC','respondToXMLRPC')).'" />';
	}
	
	static public function respondToGetPingsRequest($local){
		if(!self::getURL(addslashes($local)))
			return new IXR_Error(33,'Target does not exist on this server.');
		$list = array();
		foreach(PicoraPing::findAllByLocal($local) as $ping)
			$list[] = $ping->remote;
		return $list;
	}
	
	static public function respondToPingRequest($remote,$local){
		$remote = addslashes($remote);
		$local = addslashes($local);
		if(strpos(addslashes($local),preg_replace('|https?://|','',BASE_URL)) === false)
			return new IXR_Error(33,'Target does not exist on this server.');
		if($remote == $local)
			return new IXR_Error(33,'Remote and local must be distinct urls.');
		if(!self::getURL(addslashes($local)))
			return new IXR_Error(33,'Target does not exist on this server.');
		$ping = PicoraActiveRecord::find('PicoraPing',array(
			'where' => array(
				'local' => $local,
				'remote' => $remote
			)
		));
		if($ping)
			return new IXR_Error(48,'The pingback is already registered.');
		sleep(1);		
		$remote_content = self::getURL($remote);
		if(!$remote_content)
			return new IXR_Error(16,'The source URL could not be found.');
		if(!in_array($local,self::scrape($remote_content)))
			return new IXR_Error(17,'The source URL does not contain a link to the target URI.');
		PicoraActiveRecord::create('PicoraPing',array(
			'name' => (preg_match('/<title>([^<]*?)<\/title>/is',$remote_content,$m) ? $m[1] : $remote),
			'local' => $local,
			'remote' => $remote
		));
	 	return "Ping recorded.";
	}
	
	/**
	 * Find the XMLRPC endpoint of a given PingBack resource.
	 * @param string $url Any given web page.
	 * @return mixed Returns the URL of the XMLRPC endpoint or false if it could not be found, or null if the URL could not be opened.
	 */
	static public function discover($url){
		$contents = self::getURL($url,true,8192);
		if(!$contents)
			return null;
		if(preg_match('/X-Pingback: (.+)/',$contents,$match))
			return $match[1];
     	if(preg_match('/<link rel="pingback" href="(.+?)"/',$contents,$match))
     		return urldecode(html_entity_decode($match[1]));
		return false;
	}
	
	/**
	 * Send a PingBack ping.
	 * @param string $server XMLRPC endpoint
	 * @param string $from URL PingBack from
	 * @param string $to URL PingBack from
	 * @return array (bool success,string response)
	 */
	static public function ping($server,$from,$to){
		return PicoraXMLRPC::call($server,'pingback.ping',$from,$to);
	}
	
	/**
	 * Scrapes a given URL for links and sends PingBack pings from that URL to each of the links found.
	 * @param string $from URL to scan
	 * @param string $text Manually specify the page contents of the $from URL
	 * @return array of array(bool success,string response) for each ping sent
	 */
	static public function autoPing($from,$text = false){
		if(!$text && !($text = self::getURL($from)))
			return false;
		return self::autoPingCallback($from,$text);
	}
	
	static protected function autoPingCallback($from,$text){
		$links = self::scrape($text);
		$response = array();
		foreach($links as $link)
			$response[] = (($server = self::discover($link)))
				? self::ping($server,$from,$link)
				: array(false,'Could not autodiscover server from "'.$link.'"')
			;	
		return $response;
	}
	
	static protected function getURL($url,$headers = false,$limit = false,$timeout = 30){
		$url_parsed = parse_url($url);
		$host = (isset($url_parsed["host"])) ? $url_parsed["host"] : '';
		$port = (isset($url_parsed["port"])) ? $url_parsed["port"] : 80;
		if($port == 0)
			$port = 80;
		$path = $url_parsed["path"];
		if(isset($url_parsed["query"]) && $url_parsed["query"] != "")
			$path .= "?".$url_parsed["query"];
		$out = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";
		$fp = @fsockopen($host,$port,$errno,$errstr,$timeout);
		if(!$fp)
			return false;
		fwrite($fp,$out);
		$body = false;
		$out = '';
		$headers = '';
		while(!feof($fp)){
			$s = fgets($fp,1024);
			if($body)
				$out .= $s;
			if($s == "\r\n"){
				if($headers){
					$response = $headers.($limit ? fgets($fp,$limit) : '');
					fclose($fp);
					return $response;
				}
				$body = true;
			}
		}
		fclose($fp);
		return $out;
	}

	static protected function scrape($str){
		preg_match_all("/href=('http:\/\/.+?'|\"http:\/\/.+?\"|http:\/\/.+?)[\s>]/",$str,$matches,PREG_PATTERN_ORDER);
		return array_map(array('PicoraPingBack','arrayMapCallback'),$matches[1]); 
	}
	
	static protected function arrayMapCallback($str){
		return html_entity_decode(trim($str," \"'"));
	}
}

?>
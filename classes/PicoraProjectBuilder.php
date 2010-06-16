<?php
class PicoraProjectBuilder {
	static public $types = array(
		'core' => array(
			'PicoraAutoLoader',
			'PicoraController',
			'PicoraDispatcher',
			'PicoraEvent',
			'PicoraSupport',
			'PicoraView'
		),
		'recommended' => array(
			'PicoraActiveRecord',
			'PicoraDateTime',
			'PicoraInput',
			'PicoraJSON',
			'PicoraSearchQuery',
			'PicoraUser',
		),
		'extras' => array(
			'PicoraCalendar',
			'PicoraDocumentation',
			'PicoraImage',
			'PicoraMarkdown',
			'PicoraPingBack',
			'PicoraProjectBuilder',
			'PicoraTar',
			'PicoraTest',
			'PicoraTextile',
			'PicoraXMLRPC'
		)
	);
	static public $libraries = array(
		'PicoraJSON' => 'http://pear.php.net/pepr/pepr-proposal-show.php?id=198',
		'PicoraMarkdown' => 'http://www.michelf.com/projects/php-markdown/',
		'PicoraTar' => 'http://www.phpclasses.org/browse/package/529.html',
		'PicoraTextile' => 'http://jimandlissa.com/project/textilephp',
		'PicoraXMLRPC' => 'http://scripts.incutio.com/xmlrpc/'
	);
	static public $dependencies = array(
		'PicoraUser' => array('PicoraActiveRecord'),
		'PicoraPingBack' => array('PicoraXMLRPC','PicoraActiveRecord')
	);
	static protected $config = array();
	static protected $schema = array();
	static public function build($params){
		$params['classes'] = array_merge($params['classes'],self::$types['core']);
		$config_output = '';
		self::$config = array(
			'config' => array(
				'define(\'SECRET_KEY\',\''.md5(uniqid(rand(),true)).'\');',
				'define(\'BASE_URL\',\''.$params['url'].'\');'
			),
			'PicoraDispatcher' => array(
				'PicoraDispatcher::addRoute(\'/\',array(\'Application\',\'welcome\'));'
			)
		);
		self::$schema = array();
		if(!mkdir($params['target']))
			throw new Exception('Could not make directory: '.$params['target']);
		foreach(array(
			'classes',
			'controllers',
			'models',
			'scripts',
			'scratch',
			'styles',
			'views'
		) as $folder_name)
			mkdir($params['target'].'/'.$folder_name);
		copy($params['source'].'/functions.php',$params['target'].'/functions.php');
		copy($params['source'].'/index.php',$params['target'].'/index.php');
		copy($params['source'].'/error.html',$params['target'].'/error.html');
		copy($params['source'].'/.htaccess',$params['target'].'/.htaccess');
		copy($params['source'].'/controllers/ApplicationController.php',$params['target'].'/controllers/ApplicationController.php');
		copy($params['source'].'/views/layout.php',$params['target'].'/views/layout.php');
		copy($params['source'].'/views/error.php',$params['target'].'/views/error.php');
		copy($params['source'].'/views/welcome.php',$params['target'].'/views/welcome.php');
		foreach($params['classes'] as $name){
			if(in_array($name,array_merge(self::$types['core'],self::$types['recommended'],self::$types['extras']))){
				copy($params['source'].'/classes/'.$name.'.php',$params['target'].'/classes/'.$name.'.php');
				self::scanFlags(array_slice(file($params['source'].'/classes/'.$name.'.php'),0,100));
			}
		}
		foreach(array(
			'config',
			'PicoraAutoLoader',
			'PicoraDispatcher',
			'PicoraView',
			'PicoraXMLRPC',
			'PicoraActiveRecord',
			'PicoraEvent'
		) as $type)
			if(isset(self::$config[$type]))
				$config_output .= implode(chr(10),self::$config[$type]).chr(10).chr(10);
		file_put_contents($params['target'].'/config.php','<?php'.chr(10).chr(10).$config_output.'?>');
		if(in_array('PicoraActiveRecord',$params['classes'])){
			$schema_output = '<?php'.chr(10).chr(10).'$path = dirname(__FILE__);'.chr(10);
			foreach(self::$types['core'] as $class)
				$schema_output .= 'require_once $path.\'/classes/'.$class.'.php\';'.chr(10);
			$schema_output .= 'require_once $path.\'/config.php\';'.chr(10);
			$schema_output .= 'require_once $path.\'/functions.php\';'.chr(10);
			$schema_output .= 'PicoraActiveRecord::connect(CONNECTION_STRING);'.chr(10).chr(10);
			foreach(array('MySQL','SQLite') as $type){
				if(isset(self::$schema[$type])){
					$schema_output .= 'if(PicoraActiveRecord::getCurrentConnectionType() == \''.strtolower($type).'\'){'.chr(10);
					foreach(self::$schema[$type] as $item)
						$schema_output .= chr(9).'PicoraActiveRecord::executeQuery(\''.addslashes($item).'\');'.chr(10);
					$schema_output .= '}'.chr(10).chr(10);
				}
			}
			$schema_output .= 'exit(\'The schema file has been executed, if no errors were emitted you should now delete it for security purposes.\');'.chr(10).chr(10);
			file_put_contents($params['target'].'/schema.php',$schema_output.'?>');
		}
	}
	
	static protected function scanFlags($lines){
		foreach($lines as $line){
			if(strpos($line,'//@@') === 0){
				preg_match('/^\/\/\@\@([A-Za-z0-9]+)\s(.+)$/',$line,$matches);
				if($matches[1] == 'MySQL' || $matches[1] == 'SQLite'){
					if(!isset(self::$schema[$matches[1]]))
						self::$schema[$matches[1]] = array();
					self::$schema[$matches[1]][] = $matches[2];
				}else{
					if(!isset(self::$config[$matches[1]]))
						self::$config[$matches[1]] = array();
					self::$config[$matches[1]][] = $matches[2];
				}
			}
		}
	}
}

?>
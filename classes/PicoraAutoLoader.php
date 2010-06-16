<?php

/**
 * The AutoLoader class is an object oriented hook into PHP's \_\_autoload functionality.
 * 
 * <pre class="highlighted"><code class="php">//single files
 * PicoraAutoLoader::addFile('PageController','controllers/PageController.php');
 * //multiple files
 * PicoraAutoLoader::addFile(array('class'=>'file','class'=>'file'));
 * //whole folders
 * PicoraAutoLoader::addFolder('path');</code></pre>
 * 
 * When adding a whole folder each file should contain one class named the same as the file sans ".php" (PageController => PageController.php)
 * 
 * \_\_autoload is defined in the Picora.php file.
 * 
 * @introduction Class wrapper around PHP's built in __autoload function.
 * @event bool PicoraAutoLoader.load(string class_name) Callback must return true if a file was included or required.
 */
final class PicoraAutoLoader {
	static protected $files = array();
	static protected $folders = array();
	
	/**
	 * <pre class="highlighted"><code class="php">PicoraAutoLoader::addFile('Controller','/path/to/Controller.php');
	 * PicoraAutoLoader::addFile(array('Controller'=>'/path/to/Controller.php','View'=>'/path/to/View.php'));</code></pre>
	 * @param mixed $class_name string class name, or array of class name => file path pairs.
	 * @param mixed $file Full path to the file that contains $class_name.
	 * @return void
	 */
	static public function addFile($class_name,$file = false){
		if(!$file && is_array($class_name))
			foreach($class_name as $key => $value)
				self::addFile($key,$value);
		else
			self::$files[$class_name] = $file;
	}
	
	/**
	 * <pre class="highlighted"><code class="php">PicoraAutoLoader::addFolder('/path/to/my_classes/');
	 * PicoraAutoLoader::addFolder(array('/path/to/my_classes/','/more_classes/over/here/'));</code></pre>
	 * @param mixed $folder string, full path to a folder containing class files, or array of paths.
	 * @return void
	 */
	static public function addFolder($folder){
		if(is_array($folder))
			foreach($folder as $f)
				self::addFolder($f);
		else
			self::$folders[] = $folder;
	}
	
	static public function load($class_name){
		foreach(self::$files as $name => $file){
			if($class_name == $name){
				require_once($file);
				return true;
			}
		}
		foreach(self::$folders as $folder){
			if(substr(0,-1) != DIRECTORY_SEPARATOR)
				$folder .= DIRECTORY_SEPARATOR;
			if(file_exists($folder.$class_name.'.php')){
				require_once($folder.$class_name.'.php');
				return true;
			}
		}
		foreach(PicoraEvent::getObserverList('PicoraAutoLoader.load') as $callback)
			if(call_user_func($callback,$class_name) === true)
				return true;
		return false;
	}
}

if(!function_exists('__autoload')){
	/**
	 * Defines the hook for the AutoLoader class to run.
	 */
	function __autoload($class_name){
		return PicoraAutoLoader::load($class_name);
	}
}

?>
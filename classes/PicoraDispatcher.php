<?php

/**
 * **OUT OF DATE DOCS**
 * 
 * The Dispatcher class is responsible for mapping urls / routes to Controller methods. Each route that has the same number of directory components as the current requested url is tried, and the first method that returns a response with a non false / non null value will be returned via the Dispatcher::dispatch() method.
 * 
 * A route string can be a literal url such as '/pages/about' or contain named variables '/blog/:post_id'. Since these route strings can contain ":", they must always be enclosed by single quotes. In use the variables in the route string are collected in the order they appear and are passed as the arguments to the corresponding controller method.
 * 
 * <pre class="highlighted"><code class="php">PicoraDispatcher::addRoute(array(
 * 	'/' => array('Page','index'),
 * 	'/about/' => array('Page','about'),
 * 	'/blog/:post_id' => array('Blog','post'),
 * 	'/blog/:post_id/comment/:comment_id/delete' => array('Blog','deleteComment')
 * ));</code></pre>
 * 
 * Visiting /about/ would call PageController::about(),  
 * visiting /blog/5 would call BlogController::post(5)  
 * visiting /blog/5/comment/42/delete would call BlogController::post(5,42)  
 * 
 * To link to BlogController::deleteComment(5,42) we would call Dispatcher::getUrl(array('Blog','post'),array('post\_id'=>5,'comment\_id'=>42))
 * 
 * The dispatcher is used by calling Dispatcher::addRoute() to setup the route(s), and Dispatcher::dispatch() to handle the current request and get a response.
 * 
 * @introduction Maps URLs to PicoraController methods.
 * @event void PicoraDispatcher.beforeDispatch(string requested_url)
 * @event void PicoraDispatcher.afterDispatch(mixed response)
 */
final class PicoraDispatcher {
	const DEFAULT_ROUTE_PARAMETER_NAME = '__route__';
	static protected $routes = array();
	static protected $status = array(
		'request_url' => '',
		'current_route' => '',
		'current_arguments' => array(),
		'current_controller' => '',
		'current_method' => '',
		'current_parameters' => array(),
		'flash_values' => array(),
		'dispatcher_dir' => '',
		'base_url' => ''
	);
	static protected $error_handler = array('ApplicationController','error');
	static protected $layout_handler = array('ApplicationController','layout');
	static protected $current_controller = false;
	static protected $default_view_dir = false;
	
	/**
	 * @return string
	 */
	static public function getDefaultViewDirectory(){
		return (self::$default_view_dir) ? self::$default_view_dir : self::$status['dispatcher_dir'].'views/';
	}
	
	/**
	 * @param string $dir
	 * @return void
	 */
	static public function setDefaultViewDirectory($dir){
		self::$default_view_dir = $dir;
	}
		
	//used internally by Dispatcher to create a new PicoraController instance and call the requested method
	static public function call($class_and_method,$parameters,$arguments = false){
		$arguments = ($arguments) ? $arguments : array();
		if(!class_exists($class_and_method[0]))
			throw new Exception($class_and_method[0].' class was not found.');
		$instance = new $class_and_method[0];
		self::$current_controller = $instance;
		$instance->params = array_merge($parameters,$arguments);
		foreach(PicoraEvent::getObserverList($class_and_method[0].'.beforeCall') as $callback)
			if(call_user_func($callback,$instance,$class_and_method[1]) === false)
				return false;
		if($instance->beforeCall($class_and_method[1]) === false)
			return false;
		$callback = array($instance,$class_and_method[1]);
		if(!is_callable($callback))
			throw new Exception(get_class($callback[0]).'->'.$callback[1].'() is not callable.');
		$response = call_user_func($callback);
		if($response !== false){
			foreach(PicoraEvent::getObserverList($class_and_method[0].'.afterCall') as $callback){
				$callback_response = call_user_func($callback,$instance,$response,$class_and_method[1]);
				if(!is_null($callback_response))
					$response = $callback_response;
			}
			$callback_response = $instance->afterCall($response,$class_and_method[1]);
			if(!is_null($callback_response))
				$response = $callback_response;
		}
		return $response;
	}
	
	//internally called by Dispatcher::dispatch()
	static protected function load(){
		session_start();
		if(!isset($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]))
			$_SESSION[PicoraController::FLASH_SESSION_KEY_NAME] = array('values' => array(),'gc' => array());
		register_shutdown_function(array('PicoraDispatcher','flashGarbageCollection'));
	}
	
	//called on shutdown to clear out stale flash values
	static public function flashGarbageCollection(){
		foreach($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['gc'] as $key => $value)
			--$_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['gc'][$key];
		foreach($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['gc'] as $key => $value){
			if($value < 0){
				unset($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['gc'][$key]);
				unset($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['values'][$key]);
			}
		}
	}
	
	/**
	 * Returns the last PicoraController instance created by PicoraDispatcher::call()
	 * @return object
	 */
	static public function getCurrentController(){
		return self::$current_controller;
	}
	
	/**
	 * @return string "get","post" or "ajax", depending on a request type. An empty POST request will resolve as a GET request.
	 */
	static public function getRequestMethod(){
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
			? 'ajax'
			: (count($_POST) === 0 ? 'get' : 'post')
		;
	}
	
	/**
	 * Putting the word "Controller" at the end of each controller name is optional.
	 * <pre class="highlighted"><code class="php">Dispatcher::addRoute('/index',array('PageController','index'));
	 * Dispatcher::addRoute(array(
	 * 	'/blog/'=>array('Blog','index'),
	 * 	'/blog/:post_id'=>array('Blog','post')
	 * ));</code></pre>
	 * @param mixed $route String route or array of route => controller and method pairs.
	 * @param mixed $controller_and_method array('MyController','myMethod').
	 * @return void
	 */
	static public function addRoute($route,$controller_and_method = false){
		if(!$controller_and_method && is_array($route))
			foreach($route as $key => $value)
				self::addRoute($key,$value);
		else{
			if(!is_array($controller_and_method) || !isset($controller_and_method[0],$controller_and_method[1]))
				throw new Exception('Argument 2 to PicoraDispatcher::addRoute() must be an array with a Controller name and method name.');
			if(substr($controller_and_method[0],-10) != 'Controller')
				$controller_and_method[0] .= 'Controller';
			self::$routes[$route] = $controller_and_method;
		}
	}
	
	/**
	 * <pre class="highlighted"><code class="php">Dispatcher::getRouteByClassAndMethod('BlogController','post'); //outputs: "/blog/:post_id"
	 * Dispatcher::getRouteByClassAndMethod(array('BlogController','post')); //outputs: "/blog/:post_id"</code></pre>
	 * @param mixed $class_name String class name or array(class_name,method_name).
	 * @param mixed $method_name String method name.
	 * @return mixed Returns the route string that matches the class name and method name
	 */
	static protected function getRouteByClassAndMethod($class_name,$method_name = false){
		if(!$method_name){
			$method_name = $class_name[1];
			$class_name = $class_name[0];
		}
		foreach(self::$routes as $route => $class_and_method)
			if($class_name == $class_and_method[0] && $method_name == $class_and_method[1])
				return $route;
		return false;
	}
	
	/**
	 * Putting the word "Controller" at the end of each controller name is optional.
	 *
	 * <pre class="highlighted"><code class="php">Dispatcher::getUrl('post',array('post_id'=>5)); //outputs "http://application_url/blog/5"
	 * Dispatcher::getUrl('post',array('post_id'=>5),'?offset=1'); //outputs "http://application_url/blog/5?offset=1"
	 * Dispatcher::getUrl('post',array('post_id'=>5),array('offset'=>1)); //outputs "http://application_url/blog/5?offset=1"
	 * Dispatcher::getUrl('post',array('post_id'=>5),false); //outputs "/blog/5"
	 * Dispatcher::getUrl(array('BlogController','post'),array('post_id'=>5)); //outputs "http://application_url/blog/5"
	 * Dispatcher::getUrl(array('Blog','post'),array('post_id'=>5)); //outputs "http://application_url/blog/5"</code></pre>
	 * @param mixed $class_and_method String method_name if reffering to a method in the Controller class that is currently responding, or array('ControllerName,'methodName') if referring to a method in another Controller.
	 * @param mixed $arguments Optional arguments to resolve the url.
	 * @param mixed $include_base_url If true, includes the base_url, if false then not, if a string, then the base_url is included, and the string is appended to the end, if array base_url is included, and http_build_query is called with that array an appended
	 * @return mixed String url or boolean false if the url could not be resolved.
	 */
	static public function getUrl($class_and_method,$arguments = false,$include_base_url = true){
		if(!$arguments)
			$arguments = array();
		if(!is_array($arguments) && !is_object($arguments))
			throw new Exception('PicoraDispatcher::getUrl second argument must be an array or object.');
		$arguments = array_merge(self::getCurrentController()->params,get_object_vars(self::getCurrentController()),(array)$arguments);
		if(is_string($class_and_method))
			$class_and_method = array(self::$status['current_controller'].'Controller',$class_and_method);
		if(substr($class_and_method[0],-10) != 'Controller')
			$class_and_method[0] .= 'Controller';		
		$route_string = PicoraSupport::formatPropertyString(self::getRouteByClassAndMethod($class_and_method[0],$class_and_method[1]),$arguments);
		if($route_string && !preg_match('/\:[^\/]/',$route_string))
			return ($include_base_url ? substr(self::$status['base_url'],0,-1) : '').$route_string.(is_string($include_base_url) || is_array($include_base_url) ? (is_string($include_base_url) ? $include_base_url : http_build_query($include_base_url,null,'&')) : '');
		throw new Exception('Could not resolve URL');
	}
	
	/**
	 * @param mixed $class_and_method String method_name if reffering to a method in the Controller class that is currently responding, or array('ControllerName,'methodName') if referring to a method in another Controller.
	 * @param mixed $arguments Optional arguments to resolve the url.
	 * @return boolean Wether or not the given class, method and arguments match the current dispatched ones.
	 */
	static public function isCurrent($class_and_method,$arguments = false){
		return (self::getUrl($class_and_method,$arguments,false) == self::$status['request_url']);
	}
	
	//used to call a controller and keep track of what class, method and route we are calling
	static protected function tryRoute($route,$class_and_method,$arguments = array()){
		self::$status['current_route'] = $route;
		self::$status['current_controller'] = substr($class_and_method[0],0,-10);
		self::$status['current_method'] = $class_and_method[1];
		self::$status['current_arguments'] = $arguments;
		return self::call($class_and_method,self::$status['current_parameters'],$arguments);
	}

	/**
	 * @param string $dispatcher_dir The directory that the application is running in.
	 * @param string $base_url The base url that the application is running at.
	 * @param string $requested_url The url that is being requested relative to the base url.
	 * @return string Returns the response from a Controller that responded to the requested url.
	 */
	static public function dispatch($dispatcher_dir,$base_url,$requested_url,$try_with_trailing_slash = true){
		if($try_with_trailing_slash)
			self::load();
		PicoraEvent::notify('PicoraDispatcher.beforeDispatch',$requested_url);
		self::$status['current_parameters'] = array_merge($_POST,$_GET);
		unset(self::$status['current_parameters'][self::DEFAULT_ROUTE_PARAMETER_NAME]);
		self::$status['dispatcher_dir'] = $dispatcher_dir.'/';
		self::$status['base_url'] = $base_url;
		self::$status['request_url'] = $requested_url;
		self::$status['flash_values'] =& $_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['values'];
		foreach(self::$routes as $route => $class_and_method){
			if($requested_url == $route && ($response = self::tryRoute($route,$class_and_method)) !== false)
				return self::generateResponse($response);
			if(preg_replace('{([^/]+)}','*',$route) == preg_replace('{([^/]+)}','*',$requested_url)){
				preg_match_all('{([^/]+)?}',$route,$route_components);
				preg_match_all('{([^/]+)?}',$requested_url,$requested_url_components);
				$arguments = array();
				foreach($requested_url_components[0] as $key => $requested_url_component){
					if($requested_url_component == '')
						continue;
					elseif(strpos($route_components[0][$key],':') !== false)
						$arguments[substr($route_components[0][$key],1)] = $requested_url_component;
					elseif($route_components[0][$key] != $requested_url_component)
						continue(2);
				}
				if(($response = self::tryRoute($route,$class_and_method,$arguments)) !== false)
					return self::generateResponse($response);
			}
		}
		if($try_with_trailing_slash && strlen($requested_url) > 1 && substr($requested_url,0,-1) != '/' && self::dispatch($dispatcher_dir,$base_url,$requested_url.'/',false)){
			header('HTTP/1.1 301 Moved Permanently');
			PicoraController::redirect('http'.($_SERVER['SERVER_PORT'] == 443 ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'/');
		}
		if($try_with_trailing_slash){
			header('HTTP/1.1 404 Not Found');
			$response = self::call(self::$error_handler,array(),array());
			foreach(PicoraEvent::getObserverList('PicoraDispatcher.afterDispatch') as $callback)
				call_user_func($callback,$response);
			return self::generateResponse($response);
		}
	}
	
	static protected function generateResponse($response){
		if($response === null){
			$method_name = strtolower(preg_replace('/([a-z])([A-Z])/e',"'\\1_'.strtolower('\\2')",self::$status['current_method']));
			$controller_name = strtolower(preg_replace('/([a-z])([A-Z])/e',"'\\1_'.strtolower('\\2')",self::$status['current_controller']));
			$try_one = self::getDefaultViewDirectory().$controller_name.'/'.$method_name.'.php';
			$try_two = self::getDefaultViewDirectory().PicoraSupport::pluralize($controller_name).'/'.$method_name.'.php';
			if(file_exists($try_one))
				$response = PicoraController::render($try_one);
			elseif(file_exists($try_two))
				$response = PicoraController::render($try_two);
			else
				throw new Exception(self::$status['current_controller'].'Controller->'.self::$status['current_method'].'() returned null, and no matching view was found.');
		}
		return (self::getRequestMethod() == 'ajax')
			? $response
			: call_user_func(self::$layout_handler,($response instanceof PicoraView ? $response->display() : $response),self::getCurrentController())
		;
	}
	
	/**
	 * Key name can be any of the following:
	 * 
	 * - 'dispatcher\_dir' string Path to the directory where the file that handled the request is located. For example: "/Library/WebServer/Documents/my\_app/"
	 * - 'base\_url' string The base URL that the application is located at. For example: "http://localhost/my\_app/"
	 * - 'request\_url' string The URL that was requested relative to the base URL. For example: "/blog/5"
	 * - 'current\_route' string The route string that matched the requested url. For example: "/blog/:post\_id"
	 * - 'current\_controller' string The name of the Controller that responded to the requested URL. For example: "Blog". same as current_class sans the word "Controller"
	 * - 'current\_method' string The name of the method that responded to the requested URL. For example: "post"
	 * - 'current\_parameters' array Merged POST and GET arrays.
	 * - 'current\_arguments' mixed Array arguments passed to the called method, or bool false.
	 * @param mixed $key Boolean false or string key name.
	 * @return mixed If $key is specified, the value of the key will be returned, else array all key => value pairs.
	 */	
	static public function getStatus($key = false){
		return (!$key)
			? self::$status
			: (isset(self::$status[$key]) ? self::$status[$key] : false)
		;
	}
	
	/**
	 * @param callback $callback The callback function that will be called (with the requested url as the only parameter) if no Controller responds to the requested URL.
	 * @return void
	 */
	static public function setErrorHandler($callback){
		self::$error_handler = $callback;
	}
	
	/**
	 * If you need more granular control over this process, use the afterCall callback function of the PicoraController.
	 * @param callback $callback The callback function that will be called when a GET or POST request renders a response.
	 * @return void
	 */
	static public function setLayoutHandler($callback){
		self::$layout_handler = $callback;
	}
}

?>
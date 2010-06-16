<?php

/**
 * The PicoraController class should be the parent class of all of your PicoraController sub classes that contain the business logic of your application (render a blog post, log a user in, delete something and redirect, etc).
 * 
 * In the Dispatcher class you can define what urls / routes map to what Controllers and methods. Each method must do any of the following:
 * 
 * - return a string response
 * - redirect to another method
 * - execute any code and exit()
 * 
 * If the method returns null or false, the PicoraDispatcher will keep looking for another method that returns a response or redirects, calling the error callback if no method responds to the requested URL.
 * 
 * Each controller can have a beforeCall() and afterCall() method which will be called before a method inside the Controller is called (even if no method returns a valid response), and after a method inside the PicoraController is called (only if the method returns a valid response).
 * 
 * Additionally, you can flash() variables that will appear as local variables in a View object if one is rendered on the next request.
 * 
 * @introduction Container and support for your business logic.
 * @event bool ControllerName.beforeCall(PicoraController object,string method_name) applies to any PicoraController subclass.
 * @event mixed ControllerName.afterCall(PicoraController object,mixed response,string method_name) applies to any PicoraController subclass.
 * @event void PicoraController.afterRender(PicoraView object) Called after the PicoraView object is created, but before display() is called.
 */
abstract class PicoraController {
	const FLASH_SESSION_KEY_NAME = 'PicoraController.flash';
	
	/**
	 * This is a callback function that will be called each time any method of the controller is called. This method may be called multiple times while the dispatcher is searching for a method that returns a response. It is designed to be overriden by a subclass and does nothing by default. 
	 * @param string $method_name
	 */
	public function beforeCall($method_name){}
	
	/**
	 * After a method sucessfully responds to a requested url, this method is called. It is designed to be overriden by a subclass and does nothing by default.
	 * @param string $response The response that was returned from $method_name.
	 * @param string $method_name
	 * @return mixed Method should return null to leave $response untouched, otherwise the return value from the method becomes the response.
	 */
	public function afterCall($response,$method_name){}
		
	/**
	 * self::render('views/blog/post.php',array('post'=>$post));
	 * @param string $file Path to the file to render. Path will be relative to the file that handles the request.
	 * @param mixed $local_variables Key => value pairs to pass to the View object that is rendered.
	 */
	final static public function render($file,$local_variables = false){
		$view_instance = new PicoraView($file,$local_variables);
		foreach(PicoraEvent::getObserverList('PicoraController.afterRender') as $callback)
			call_user_func($callback,$view_instance);
		return $view_instance;
	}
	
	/**
	 * Renders $data_to_encode as JSON with the appropriate headers, outputs this to the browser and terminates the current request.
	 * @param mixed $data_to_encode
	 */
	final static protected function renderJSON($data_to_encode,$use_standard = false){
		if(function_exists('json_encode'))
			$output = json_encode($data_to_encode);
		elseif(class_exists('PicoraJSON'))
			$output = PicoraJSON::encode($data_to_encode);
		else
			throw new Exception('No function or class found to render JSON data.');
		if($use_standard){
			header('X-JSON: ('.$output.')');
			print ' ';
		}else
			print '('.$output.')';
		exit;
	}
	
	/**
	 * Renders an RSS feed from an array of data. Note that this is not an all in one cure all solution for RSS, it is merely a way to quickly create an RSS feed that fits most basic uses. $feed should contain the following keys:
	 *
	 * - string "title"
	 * - string "link"
	 * - timestamp "date"
	 * - optional string "description"
	 * - optional string "language"
	 * - array "items"
	 * 
	 * Each item in "items" should contain:
	 *
	 * - string "title"
	 * - string "link"
	 * - optional string "description"
	 * - optional timestamp "date"
	 * - optional string "guid"
	 *
	 * @param string $feed Array of RSS data.
	 * @param boolean $print Defaults to true. Print the feed complete with the appropriate header.
	 * @return string RSS feed, or null if $print is true.
	 */
	final static protected function renderRSS($feed,$print = true){
		$tab = chr(9);
		$nl = chr(10);
		if(!isset($feed['description']))
			$feed['description'] = '';
		$output = '<?xml version="1.0" encoding="UTF-8"?>'.$nl;
		$output .= '<rss version="2.0">'.$nl.
			$tab.'<channel>'.$nl.
			$tab.$tab.PicoraView::tag('title',$feed['title']).$nl.
			$tab.$tab.PicoraView::tag('link',$feed['link']).$nl.
			$tab.$tab.PicoraView::tag('lastBuildDate',gmdate(DATE_RFC822,$feed['date'])).$nl.
			$tab.$tab.PicoraView::tag('description',$feed['description']).$nl.
			(isset($feed['language']) ? $tab.$tab.PicoraView::tag('language',$feed['language']).$nl : '')
		;
		foreach($feed['items'] as $item){
			if(!isset($item['guid']))
				$item['guid'] = md5($item['title'].$item['link']);
			$output .= $tab.'<item>'.$nl.
				$tab.$tab.$tab.PicoraView::tag('title',$item['title']).$nl.
				$tab.$tab.$tab.PicoraView::tag('link',$item['link']).$nl.
				(isset($item['description']) ? $tab.$tab.$tab.PicoraView::tag('description','<![CDATA['.$item['description'].']]>').$nl : '').
				(isset($item['date']) ? $tab.$tab.$tab.PicoraView::tag('pubDate',gmdate(DATE_RFC822,$item['date'])).$nl : '').
				$tab.$tab.$tab.'<guid isPermaLink="false">'.$item['guid'].'</guid>'.$nl.
			$tab.$tab.'</item>'.$nl;
		}
		$output .= $tab.'</channel>'.$nl.'</rss>';
		if($print){
			header('Content-type: application/rss+xml');
			print $output;
		}else
			return $output;
	}
	
	/**
	 * Redirects to another method, and terminates the current request.
	 *
	 * Putting the word "Controller" at the end of each controller name is optional.
	 *
	 * self::redirect('about');
	 * self::redirect(array('BlogController','post'),array('post_id'=>5));
	 * self::redirect(array('Blog','post'),array('post_id'=>5));
	 * self::redirect('http://google.com/');
	 * @param mixed $controller_and_method String method name if redirecting to a method in the current controller or array('ControllerName','methodName') if redirecting to a method in another controller.
	 * @param mixed $arguments Array arguments to resolve the route,or boolean false.
	 */
	final static public function redirect($controller_and_method,$arguments = false,$include_base_url = true){
		header('Location: '.(is_string($controller_and_method) && (strpos($controller_and_method,'http://') === 0 || strpos($controller_and_method,'https://' === 0)) ? $controller_and_method : PicoraDispatcher::getUrl($controller_and_method,$arguments,$include_base_url)));
		exit;
	}
	
	/**
	 * Serializes $value and makes it available as a local variable with $key name on the next request in the rendered view.
	 * self::flash('message','Your post has been saved.');
	 * self::redirect('post',array('post_id'=>$post_id));
	 * //in the view file that the method post renders... 
	 * <?php if(isset($message)):?><p class="message"><?php print $message;?></p><?php endif;?>
	 * @param string $key
	 * @param mixed $value Can be any data type or object that is serializable.
	 * @param boolean $now Flash the value during the current request only? Defaults to false (so it will be available on this request, and the next).
	 */
	final static public function flash($key,$value,$now = false){
		$_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['values'][$key] = serialize($value);
		$_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['gc'][$key] = ($now ? 0 : 1);
	}
	
	/**
	 * @return array Flash values.
	 */
	final static public function getFlash(){
		return (isset($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME])) ? $_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['values'] : false;
	}
	
	/**
	 * @param string $file Local file name
	 * @param mixed $download_as_filename Filename that the browser will save the file as. Defaults to the specified file name.
	 */
	final static protected function sendFile($file,$download_as_filename = false){
		if(!file_exists($file))
			return false;
		$size = filesize($file);
		header('Content-Type: '.(preg_match('/Opera/',$_SERVER['HTTP_USER_AGENT'] || preg_match('/MSIE/',$_SERVER['HTTP_USER_AGENT'])) ? 'application/octetstream' : 'application/octet-stream'));
		header('Content-Disposition: attachment; filename="'.($download_as_filename ? $download_as_filename : array_pop(explode('/',$file))).'"');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header("Cache-control: private");
		header('Pragma: private');
		header('Content-Length: '.$size);
		readfile($file);
	}	
}

?>
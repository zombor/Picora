<?php

/**
 * PicoraView is best described by a simple use case:
 * 
 * - create a PicoraView object with a path to a valid PHP file as the first argument
 * - assign variables to this object
 * - when display() or print is called on the object, the variables assigned to the object become available as local variables inside the PHP file and the output is returned
 * 
 * <pre class="highlighted"><code class="php">$v = new PicoraView('views/my_template.php');
 * $v->title = 'My Title';
 * $v->body = 'My body';
 * print $v;
 * //or
 * print $v->display();
 * //or the same thing in one command
 * print new PicoraView('views/my_template.php',array(
 * 	'title' => 'My Title',
 * 	'body' => 'My body'
 * ));</code></pre>
 * 
 * my_template.php might look like this: 
 * 
 * <pre class="highlighted"><code class="html">&lt;html&gt;
 * 	&lt;head&gt;
 * 		&lt;title&gt;&lt;?php print $title;?&gt;&lt/title&gt;
 * 	&lt;/head&gt;
 * 	&lt;body&gt;
 * 		&lt;h1&gt;&lt;?php print $title;?&gt;&lt;/h1&gt;
 * 		&lt;p&gt;&lt;?php print $body;?&gt;&lt;/p&gt;
 * 	&lt;/body&gt;
 * &lt;/html&gt;</code></pre>
 * 
 * <h3>Using view helpers</h3>
 * 
 * <pre class="highlighted"><code class="php">$v->addHelperMethod('tag',array('MyHelperClass','myTagGenerator'));</code></pre>
 * 
 * In your template you can now call: 
 * 
 * <pre class="highlighted"><code class="php">&lt;?php print $this->tag('a',array('href'=>'http://mysite.com/'),'My Link Text');?&gt;</code></pre>
 * 
 * Sometimes it is useful to set content to be available outside of the current template.
 * 
 * <pre class="highlighted"><code class="php">&lt;?php $this->beginSection('head');?&gt;
 * 	This content will be available to all subsequent PicoraView objects in the variable $head.
 * &lt;?php $this->endSection('head');?&gt;</code></pre>
 * 
 * This is useful in implementing layouts, so that templates may add to different sections. Your layout View must be rendered after the view declaring these sections is rendered for the variables to be available. The variables will be overwritten by any manually defined variables.
 * 
 * <h3>Callbacks</h3>
 * 
 * If you extend PicoraView, you can define a beforeDisplay() and afterDisplay(&$contents) method, that will be called before and after display() is called. You can use beforeDisplay() to set or unset variables, or helpers in every view instance that you wish, and use afterDisplay() to modify the $contents that will be returned from display. Note that $contents is passed in as reference. Neither function needs a return value;
 * 
 * <h3>Rendering from a string instead of a file</h3>
 * 
 * Just pass in any PHP code (not beginning and ending with PHP tags), in place of a filename. 
 * 
 * <pre class="highlighted"><code class="php">$v = new View(false,'
 * 	&lt;h1&gt;&lt;?php print $title;?&gt;&lt;/h1&gt;
 * ',array('title'=>'My Title'));</code></pre>
 * 
 * The only requirement is that this string MUST contain at least one newline character.
 * 
 * @introduction Native PHP template engine.
 * @event void PicoraView.beforeDisplay(View object)
 * @event void PicoraView.afterDisplay(string output)
 */
final class PicoraView {	
	protected $__is_string__ = false;
	protected $__file__ = false;
	static protected $__methods__ = array();
	static protected $__sections__ = array();
	static protected $__last_section_name__ = false;
	
	/**
	 * @param file_name $file Path to template file.
	 * @param mixed $params Optional array of key value pairs.
	 * @return View object
	 */
	public function __construct($file = false,$params = false){
		$this->__is_file__ = (strpos($file,chr(10)) !== false);
		$this->__file__ = $file;
		if($params)
			foreach($params as $key => $value)
				$this->{$key} = $value;
	}
	
	/**
	 * Renders the current template, returning the result as a string.
	 * @return string
	 */
	public function display(){
		foreach(PicoraEvent::getObserverList('PicoraView.beforeDisplay') as $callback)
			call_user_func($callback,$this);
		//extract and unserialize all flash values and extract content blocks
		if(isset($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]))
			foreach($_SESSION[PicoraController::FLASH_SESSION_KEY_NAME]['values'] as $__key__ => $__value__)
				$$__key__ = unserialize($__value__);
		//bring controller instance variables, content blocks, and view instance variables into scope
		extract(array_merge((PicoraDispatcher::getCurrentController() ? array_merge(PicoraDispatcher::getCurrentController()->params,get_object_vars(PicoraDispatcher::getCurrentController())) : array()),self::$__sections__,get_object_vars($this)),EXTR_REFS);
		//include file and return output
		ob_start();
			if($this->__is_string__)
				eval(' ?>'.$this->__file__.'<?php ');
			else
				include($this->__file__);
		$output = ob_get_clean();
		foreach(PicoraEvent::getObserverList('PicoraView.afterDisplay') as $callback)
			call_user_func($callback,$output);
		return $output;
	}
	
	public function __toString(){
		try{
			return $this->display();
		}catch(Exception $e){
			PicoraSupport::exceptionHandler($e);
			exit;
		}
	}
	
	/**
	 * @param mixed String method name, or array of method name => callback pairs
	 * @param callback Callback function
	 */
	static public function addMethod($method,$callback = false){
		if(is_array($method))
			foreach($method as $_method => $_callback)
				self::addMethod($_method,$_callback);
		else
			self::$__methods__[$method] = $callback;
	}
	
	public function __call($method,$args){
		if(isset(self::$__methods__[$method]))
			return call_user_func_array(self::$__methods__[$method],$args);
		else
			throw new Exception('The method '.get_class($this).'->'.$method.'() is not callable.');
	}
	
	//default view helpers begin here
	
	/**
	 * Default view helper.
	 * @param string $section_name
	 */	
	static protected function beginSection($section_name){
		self::$__last_section_name__ = $section_name;
		ob_start();
	}
	
	/**
	 * Default view helper.
	 * @param string $section_name
	 */	
	static protected function endSection($section_name){
		if(isset(self::$__sections__[self::$__last_section_name__]))
			self::$__sections__[self::$__last_section_name__] .= ob_get_clean();
		else
			self::$__sections__[self::$__last_section_name__] = ob_get_clean();
	}
	
	/**
	 * 	#php
	 * 	print self::cycle('even','odd');
	 */
	static public function cycle(){
		static $cycles;
		if(!isset($cycles))
			$cycles = array();
		$args = func_get_args();
		$key = 'cycle.'.md5(implode($args));
		$cycles[$key] = (!isset($cycles[$key])) ? 0 : ($cycles[$key] < count($args) - 1 ? $cycles[$key] + 1 : 0);
		return $args[$cycles[$key]];
	}
	
	/**
	 * Default view helper.
	 * 	#php
	 * 	print self::tag('a',array('href'=>'some_url'),'Link Contents');
	 * 	print self::tag('a','Link Contents');
	 * @param string $tag_name
	 * @param array $attributes
	 * @param string $content
	 * @param boolean $encode runs htmlspecialchars($value,ENT_COMPAT,'utf-8') on attributes
	 */
	static public function tag($tag_name,$attributes = false,$content = false,$encode = true){
		if(is_string($attributes)){
			$content = $attributes;
			$attributes = false;
		}
		$attributes_output = '';
		if($attributes){
			$attributes_output = ' ';
			foreach($attributes as $key => $value)
				$attributes_output .= $key.'="'.($encode ? htmlspecialchars($value,ENT_COMPAT,'utf-8') : $value).'" ';
			$attributes_output = substr($attributes_output,0,-1);
		}
		return '<'.$tag_name.$attributes_output.($content ? '>'.$content.'</'.$tag_name.'>' : '/>');
	}
	
	/**
	 * Default view helper. Takes the same parameters as PicoraDispatcher::getUrl()
	 */
	static public function url($controller_and_method,$arguments = false,$include_base_url = true){
		return PicoraDispatcher::getUrl($controller_and_method,$arguments,$include_base_url);
	}
	
	
	/**
	 * Default view helper. Takes the same parameters as PicoraDispatcher::getUrl(), with text prepended.
	 * 	#php
	 * 	print self::link('Link Text',array('Blog','index'));
	 */
	static public function link($text,$controller_and_method,$arguments = false,$include_base_url = true){
		return self::tag('a',array(
			'href' => PicoraDispatcher::getUrl($controller_and_method,$arguments,$include_base_url)
		),$text);
	}
	
	/**
	 * Default view helper. Takes the same parameters as PicoraController::render().
	 */
	final static public function render($file,$local_variables = false){
		return PicoraController::render($file,$local_variables);
	}
}

?>
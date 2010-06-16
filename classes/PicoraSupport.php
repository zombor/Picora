<?php

/**
 * This class contains only static methods and should not be instantiated. These methods provide functionality necessary for core the Picora classes to operate.
 * @introduction Support functionality for the Picora core.
 */
final class PicoraSupport {
	/**
	 * Replaces properties in a given string (denoted with a colon) with properties in a given array. This function is used to format the route strings in the PicoraDispatcher and to format the where conditions in PicoraActiveRecord.
	 * 
	 * <pre class="highlighted"><code class="php">print format_property_string('one :two three',array('two'=>2));
	 * //one 2 three</code></pre>
	 *
	 * If the properties argument is an object is instead of an array, it must implement ArrayAccess. If the property does not exist in that object a camel cased getter method is searched for to get the result instead. If that method exists, it will be called, and the result used.
	 * 
	 * <pre class="highlighted"><code class="php">class Test extends ArrayObject {
	 * 	public $third_proprerty = 3;
	 * 	public function getSecondProperty(){
	 * 		return 2;
	 * 	}
	 * }
	 * $t = new Test();
	 * print format_property_string('one :second_property :third_proprerty',$t);
	 * //one 2 3</code></pre>
	 *
	 * If properties can't be resolved they are left in the string with thier colons.
	 *
	 * Any property named "id" that is set to false will be replaced with the string "new".
	 * @param string $property_string
	 * @param mixed $properties
	 * @return string
	 */
	static public function formatPropertyString($property_string,$properties){
		preg_match_all('/(?<!\\\\)(\:([^\/0-9][\w\_\-]*))/e',$property_string,$matches);
		foreach($matches[2] as $match){
			if($match == 'id' && isset($properties['id']) && $properties['id'] === false)
				$property_string = str_replace(':id','new',$property_string);
			elseif(isset($properties[$match]) && !is_null($properties[$match]))
				$property_string = str_replace(':'.$match,$properties[$match],$property_string);
			elseif(is_object($properties) && method_exists($properties,'get'.str_replace(' ','',ucwords(str_replace('_',' ',$match)))))
				$property_string = str_replace(':'.$match,$properties->{'get'.str_replace(' ','',ucwords(str_replace('_',' ',$match)))}(),$property_string);
		}
		return $property_string;
	}
	
	/**
	 * @param string $str word to get the singular form of.
	 * @return string singular form of given word.
	 */
	static public function singularize($str){
		//Singularize rules from Rails::ActiveSupport::inflections.rb
		//Copyright (c) 2005 David Heinemeier Hansson
		$uncountable = array('equipment','information','rice','money','species','series','fish','sheep');
		if(in_array(strtolower($str),$uncountable))
			return $str;
		$irregulars = array(
			'people'=>'person',
			'men'=>'man',
			'children'=>'child',
			'sexes'=>'sex',
			'moves'=>'move'
		);
		if(in_array(strtolower($str),array_keys($irregulars)))
			return $irregulars[$str];
		foreach(array(
			'/(quiz)zes$/i'=>'\1',
			'/(matr)ices$/i'=>'\1ix',
			'/(vert|ind)ices$/i'=>'\1ex',
			'/^(ox)en/i'=>'\1',
			'/(alias|status)es$/i'=>'\1',
			'/([octop|vir])i$/i'=>'\1us',
			'/(cris|ax|test)es$/i'=>'\1is',
			'/(shoe)s$/i'=>'\1',
			'/(o)es$/i'=>'\1',
			'/(bus)es$/i'=>'\1',
			'/([m|l])ice$/i'=>'\1ouse',
			'/(x|ch|ss|sh)es$/i'=>'\1',
			'/(m)ovies$/i'=>'\1ovie',
			'/(s)eries$/i'=>'\1eries',
			'/([^aeiouy]|qu)ies$/i'=>'\1y',
			'/([lr])ves$/i'=>'\1f',
			'/(tive)s$/i'=>'\1',
			'/(hive)s$/i'=>'\1',
			'/([^f])ves$/i'=>'\1fe',
			'/(^analy)ses$/i'=>'\1sis',
			'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'=>'\1\2sis',
			'/([ti])a$/i'=>'\1um',
			'/(n)ews$/i'=>'\1ews',
			'/s$/i'=>''
		) as $match => $replace)
			if(preg_match($match,$str))
				return preg_replace($match,$replace,$str);
		return $str;
	}
	
	/**
	 * @param string $str word to get the plural form of.
	 * @return string plural form of given word.
	 */		
	static public function pluralize($str){
		//Singularize rules from Rails::ActiveSupport::inflections.rb
		//Copyright (c) 2005 David Heinemeier Hansson
		$uncountable = array('equipment','information','rice','money','species','series','fish','sheep');
		if(in_array(strtolower($str),$uncountable))
			return $str;
		$irregulars = array(
			'person'=>'people',
			'man'=>'men',
			'child'=>'children',
			'sex'=>'sexes',
			'move'=>'moves'
		);
		if(in_array(strtolower($str),array_keys($irregulars)))
			return $irregulars[$str];
		foreach(array(
			'/(quiz)$/i'=>'\1zes',
			'/^(ox)$/i'=>'\1en',
			'/([m|l])ouse$/i'=>'\1ice',
			'/(matr|vert|ind)ix|ex$/i'=>'\1ices',
			'/(x|ch|ss|sh)$/i'=>'\1es',
			'/([^aeiouy]|qu)ies$/i'=>'\1y',
			'/([^aeiouy]|qu)y$/i'=>'\1ies',
			'/(hive)$/i'=>'\1s',
			'/(?:([^f])fe|([lr])f)$/i'=>'\1\2ves',
			'/sis$/i'=>'ses',
			'/([ti])um$/i'=>'\1a',
			'/(buffal|tomat)o$/i'=>'\1oes',
			'/(bu)s$/i'=>'\1ses',
			'/(alias|status)$/i'=>'\1es',
			'/(octop|vir)us$/i'=>'\1i',
			'/(ax|test)is$/i'=>'\1es',
			'/s$/i'=>'s',
			'/$/'=> 's'
		) as $match => $replace)
			if(preg_match($match,$str))
				return preg_replace($match,$replace,$str);
		return $str;
	}
	
	/**
	 * Provides a nicer print out of the stack trace when an exception is thrown. You can use this function in your own custom exception handler by using the return parameter.
	 *
	 * This function is automatically set as the default exception handler. If you set one before including Picora in your application, just call restore_exception_handler() right after including it.
	 * @param Exception $e Exception object.
	 * @param boolean $return Return the stack trace as a string, or print it out.
	 * @param boolean $render_html Wether to render the output for the command line, or a web browser.
	 * @return mixed
	 */
	static public function exceptionHandler($e,$return = false,$render_html = true){
		if($render_html){
			$s = '<style>ul,li,pre {font-family:\'Lucida Grande\',Verdana; color:#333;} pre {margin-left:25px;}</style>';
			$s .= '<p style=" font-family:\'Lucida Grande\',Verdana; color:#911; font-weight:bold; font-size:12px; line-height:28px; padding:0; margin:0 0 5px 10px; float:none; border:none; background-image:none;">Uncaught '.get_class($e).'</p>';
		}else
			$s = '# Uncaught '.get_class($e);
		if(method_exists($e,'getTitle')){
			$s .= (!$render_html) ? '### '.$e->getTitle().chr(10) : '<h1 style="color:#333; font-family:Verdana; font-size:24px; font-weight:bold; background-image:none; border:none; float:none; padding:0; margin:10px 0 15px 10px;">'.$e->getTitle().'</h1>';
			$s .= (!$render_html) ? '### '.$e->getMessage().chr(10) : '<p style=" font-family:\'Lucida Grande\',Verdana; color:#333; font-size:16px; line-height:28px; padding:0; margin:0 0 15px 10px; float:none; border:none; background-image:none;">'.$e->getMessage().'</p>';
		}else{
			if(!is_object($e)){
				ob_start(); var_dump($e); $r = ob_get_clean();
				die('Unknown Error: Exception handler was not passed an Exception object. Was passed : '.$r.'<br/>Stack Trace:<br/><pre>'.print_r(debug_backtrace(),true).'</pre>');
			}
			$s .= (!$render_html) ? '### '.$e->getMessage().chr(10) : '<h1 style="color:#333; font-family:Verdana; font-size:24px; font-weight:bold; background-image:none; border:none; float:none; padding:0; margin:10px 0 15px 10px;">'.$e->getMessage().'</h1>';
		}
		$max = 64;
		$traceArr = $e->getTrace();
		if(count($traceArr) == 1)
			$s.= (!$render_html) ? '### The exception was thrown on line '.$e->getLine().' in '.$e->getFile().chr(10) : '<p style="font-family:\'Lucida Grande\',Verdana; font-size:12px; color:#444; text-align:left; line-height:24px; margin:0 0 0 10px; padding:0;"><b>The exception was thrown on line '.$e->getLine().' in '.$e->getFile().'</b></p>';
		else {
			$s .= (!$render_html) ? '### Before the Exception was thrown, the script called the following functions in this order:'.chr(10) : '<p style="font-family:\'Lucida Grande\',Verdana; font-size:12px; color:#444; text-align:left; line-height:24px; margin:0 0 0 10px; padding:0;"><b>Before the Exception was thrown, the script called the following functions in this order:</b><br/>';
			$totalTabs = count($traceArr) - 1;
			$usedTabs = 0;
			foreach(array_reverse($traceArr) as $arr){
				++$usedTabs;
				$s.= (!$render_html) ? '    ' : '&nbsp;&nbsp;&nbsp;';
				if (isset($arr['class'])) $s .= (!$render_html) ? ''.$arr['class'].'>>' : '<b>'.$arr['class'].'</b>&rarr;';
				$args = array();
				if(!empty($arr['args'])) foreach($arr['args'] as $v){
					if (is_null($v)) $args[] = 'null';
					else if (is_array($v)) $args[] = 'Array['.count($v).']';
					else if (is_object($v)) $args[] = get_class($v).' Object';
					else if (is_bool($v)) $args[] = $v ? 'true' : 'false';
					else if (is_int($v)) $args[] = $v;
					else{
						$v = (string) @$v;
						$str = htmlspecialchars(substr($v,0,$max));
						if (strlen($v) > $max) $str .= '...';
						$args[] = "\"".$str."\"";
					}
				}
				$s .= (!$render_html) ? $arr['function'].'('.implode(', ',$args).')' : '<b>'.$arr['function'].'(</b>'.implode(', ',$args).'<b>)</b>';
				$Line = (isset($arr['line'])? $arr['line'] : "[PHP ENGINE]");
				$File = (isset($arr['file'])? $arr['file'] : "[PHP ENGINE]");
				$s .= (!$render_html) ? '   '.sprintf('called on line %d in %s',$Line,$File).chr(10).'### ' : sprintf("&nbsp;&nbsp;&nbsp;<span style=\"font-size:18px;\">&raquo;</span>&nbsp;&nbsp;called on line %d in %s",$Line,$File).'<br/>';
				$s .= (!$render_html) ? str_repeat('   ',$usedTabs) : str_repeat('&nbsp;&nbsp;&nbsp;',$usedTabs);
			}
			$s .= (!$render_html) ? '### The exception was thrown on line '.$e->getLine().' in '.$e->getFile() : '&nbsp;&nbsp;&nbsp;<b>The exception was thrown on line '.$e->getLine().' in '.$e->getFile().'</b></p>';
		}
		if($return)
			return $s;
		else
			print $s;
	}
}
set_exception_handler(array('PicoraSupport','exceptionHandler'));

?>
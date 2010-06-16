<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraTest',$path.'/classes/PicoraTest.php');

/**
 * This is a very simple unit tester that only provides the most basic assertions and reporting.
 * <pre class="highlighted"><code class="php">class TestPicoraActiveRecord extends PicoraTest {
 * 	public function testBasics(){
 * 		$a = PicoraActiveRecord::build('Album');
 * 		$this->assertFalse($a->id);
 * 	}
 * }
 *
 * print PicoraTest::run(array('TestPicoraActiveRecord'));</code></pre>
 * @introduction Simple unit test framework.
 */
class PicoraTest {
	static protected $classes = array();
	static protected $tests = 0;
	static protected $passes = array();
	static protected $fails = array();
	static protected $exceptions = array();
	static protected $failing_methods = array();
	
	/**
	 * @param array $classes
	 * @param bool $return_html
	 * @return string
	 */
	static public function run($classes,$return_html = true){
		self::$classes = $classes;
		self::$tests = 0;
		self::$passes = array();
		self::$fails = array();
		self::$exceptions = array();
		self::$failing_methods = array();
		foreach($classes as $class_name){
			++self::$tests;
			self::$passes[$class_name] = 0;
			self::$fails[$class_name] = 0;
			self::$exceptions[$class_name] = array();
			self::$failing_methods[$class_name] = array();
			$methods = get_class_methods($class_name);
			$test_case = new $class_name();
			$test_case->setup();
			foreach($methods as $method_name){
				if(substr($method_name,0,4) == 'test'){
					try{
						$test_case->{$method_name}();
					}catch(Exception $e){
						self::$exceptions[$class_name][$method_name] = $e;
					}
				}
			}
			$test_case->teardown();
		}
		return self::printer($return_html);
	}
	
	static protected function printer($return_html){
		$exception_output = '';
		$main_output = '';
		$failing_methods_output = '';
		if($return_html){
			$main_output = '<table class="unit_tests"><tr class="header"><td>Class Name</td><td>Passed</td><td>Failed</td><td>Exceptions</td></tr>';
			foreach(self::$classes as $class_name){
				$main_output .= '<tr class="'.(self::$fails[$class_name] > 0 || count(self::$exceptions[$class_name]) || 0 ? 'fail' : 'pass').'"><td class="class_name">'.$class_name.'</td><td>'.self::$passes[$class_name].'</td><td>'.self::$fails[$class_name].'</td><td>'.count(self::$exceptions[$class_name]).'</td></tr>';
				foreach(self::$exceptions[$class_name] as $method_name => $exception)
					$exception_output .= '<h2>'.$class_name.'::'.$method_name.' threw this exception:</h2><hr/>'.PicoraSupport::exceptionHandler($exception,true).'<hr/>';
			}
			$main_output .= '</table>';
			foreach(self::$failing_methods as $class_name => $fail_info)
				foreach($fail_info as $method_name => $fails)
					foreach($fails as $item)
						$failing_methods_output .= '<li>'.$class_name.'::'.$method_name.' '.$item['method'].' failed on line '.$item['line'].' in file '.$item['file'].'</li>';
			if($failing_methods_output != '')
				$failing_methods_output = '<ul>'.$failing_methods_output.'</ul>';
		}else{
			$main_output = 'PicoraTest::run()'.chr(10);
			foreach(self::$classes as $class_name){
				$main_output .= chr(9).$class_name.' '.self::$fails[$class_name].' passed, '.self::$fails[$class_name].' failed, '.count(self::$exceptions[$class_name]).' exceptions.'.chr(10);
				foreach(self::$exceptions[$class_name] as $method_name => $exception)
					$exception_output .= $class_name.'::'.$method_name.' threw this exception:'.chr(10).picora_exception_handler($exception,true,false).chr(10).chr(10);
				foreach(self::$failing_methods[$class_name] as $method_name => $fails)
					foreach($fails as $info)
						$failing_methods_output .= ' - '.$class_name.'::'.$method_name.' '.$info['method'].' failed on line '.$info['line'].' in file '.$info['file'].chr(10);
			}
		}
		return $main_output.$failing_methods_output.$exception_output;
	}
	
	/**
	 * @param mixed statement
	 * @return void
	 */
	final protected function assertTrue($statement){
		if($statement){
			++self::$passes[get_class($this)];
		}else{
			$debug = debug_backtrace();
			foreach($debug as $i => $trace){
				if($trace['class'] != 'PicoraTest'){
					if(!isset(self::$failing_methods[$trace['class']][$trace['function']]))
						self::$failing_methods[$trace['class']][$trace['function']] = array();
					self::$failing_methods[$trace['class']][$trace['function']][] = array(
						'method' => $debug[$i - 1]['function'],
						'file' => $debug[$i - 1]['file'],
						'line' => $debug[$i - 1]['line']
					);
					break;
				}
			}
			++self::$fails[get_class($this)];
		}
	}
	
	/**
	 * @param mixed statement
	 * @return void
	 */
	final protected function assertFalse($statement){
		$this->assertTrue((!($statement)));
	}
	
	/**
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	final protected function assertEqual($a,$b){
		$this->assertTrue(($a == $b));
	}
	
	/**
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	final protected function assertNotEqual($a,$b){
		$this->assertTrue(($a != $b));
	}
	
	/**
	 * @return void
	 */
	protected function setup(){}
	
	/**
	 * @return void
	 */
	protected function teardown(){}
}

?>
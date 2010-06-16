<?php

/**
 * The core concept behind PicoraEvent is often referred to as a plugin in other non object oriented systems. Any PHP file/program/subroutine can register an observer (subscriber) to a given event. When the event is called the observer is called and can modify the arguments or response.
 * 
 * <pre class="highlighted"><code class="php">function my_function(&$text){}
 * PicoraEvent::observe('event_name','my_function');
 * $text_to_modify = '';
 * foreach(PicoraEvent::getObserverList('event_name') as $callback)
 * 	call_user_func($callback,$text_to_modify);</code></pre>
 * @introduction A subscriber/dispatcher event system for PHP.
 */
final class PicoraEvent {
	static protected $events = array();
	
	/**
	 * @param string $event_name
	 * @param callback $callback
	 * @return void
	 */
	static public function observe($event_name,$callback){
		if(!isset(self::$events[$event_name]))
			self::$events[$event_name] = array();
		if(!in_array($callback,self::$events[$event_name]))
			self::$events[$event_name][] = $callback;
	}
	
	/**
	 * @param string $event_name
	 * @param callback $callback
	 * @return void
	 */
	static public function stopObserving($event_name,$callback){
		if(!isset(self::$events[$event_name]))
			foreach(self::$events[$event_name] as $i => $_callback)
				if($callback == $_callback)
					unset(self::$events[$event_name][$i]);
	}
	
	/**
	 * @param string $event_name
	 * @return void
	 */	
	static public function clearObservers($event_name){
		self::$events[$event_name] = array();
	}
	
	/**
	 * @param string $event_name
	 * @return array callbacks
	 */
	static public function getObserverList($event_name){
		return (isset(self::$events[$event_name])) ? self::$events[$event_name] : array();
	}
	
	/**
	 * If your event does not need to process the return values from any observers use this instead of getObserverList()
	 * @param string $event_name
	 * @return void
	 */
	static public function notify($event_name){
		$args = func_get_args();
		foreach(self::getObserverList($event_name) as $callback)
			call_user_func_array($callback,$args);
	}
}

?>
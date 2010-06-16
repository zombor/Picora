<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraDateTime',$path.'/classes/PicoraDateTime.php');

class PicoraDateTime {
	static public function dayNameFromNumber($i){
		static $days;
		if(!isset($days)){
			$days = array();
			for($n = 0,$t = 3 * 86400; $n < 7; $n++, $t += 86400)
				$days[$n + 1] = ucfirst(gmstrftime('%A',$t));
		}
		return $days[$i];
	}
	
	static public function monthNameFromNumber($i){
		static $months;
		if(!isset($months)){
			$months = array();
			for($n = 1; $n <= 12; ++$n)
				$months[$n] = gmstrftime('%B',gmmktime(0,0,0,$n,1,1970));
		}
		return $months[$i];
	}
	
	static public function dateTimeFromTimeStamp($timestamp = false){
	    if(!$timestamp)
	        $timestamp = time();
	    return date('Y-m-d H:i:s',$timestamp);
	}
	
	static public function timeStampFromDateTime($datetime){
	    $date_time = explode(" ",$datetime);
	    list($year,$month,$day) = explode("-",$date_time[0]);
	    list($hour,$minute,$second) = explode(":",$date_time[1]);
	    unset($date_time);
	    return mktime(intval($hour),intval($minute),intval($second),intval($month),intval($day),intval($year));
	}
	
	static public function arrayFromDateTime($datetime){
	    return array(
	        'year' => self::yearFromDateTime($datetime),
	        'month' => self::monthFromDateTime($datetime),
	        'day' => self::dayFromDateTime($datetime),
	        'hour' => self::hourFromDateTime($datetime),
	        'minute' => self::minuteFromDateTime($datetime),
	        'second' => self::secondFromDateTime($datetime)
	    );
	}
	
	static public function dateTimeFromArray($array){
	    return $array['year'].'-'.$array['month'].'-'.$array['day'].' '.$array['hour'].':'.$array['minute'].':'.$array['second'];
	}
	
	static public function validate(&$datetime){
		if(!$datetime)
			$datetime = dateTimeFromTimeStamp(time());
		if(!is_string($datetime))
			throw new Exception('DateTime input must be a string. '.gettype($datetime).' was passed.');
	}
	
	
	static public function yearFromDateTime($datetime = false){
	    self::validate($datetime);
	    return substr($datetime,0,4);
	}
	
	static public function monthFromDateTime($datetime = false){
	    self::validate($datetime);
	    return substr($datetime,5,2);
	}

	static public function dayFromDateTime($datetime = false){
	    self::validate($datetime);
	    return substr($datetime,8,2);
	}

	static public function hourFromDateTime($datetime = false){
	    self::validate($datetime);
	    return substr($datetime,11,2);
	}

	static public function minuteFromDateTime($datetime = false){
	    self::validate($datetime);
	    return substr($datetime,14,2);
	}

	static public function secondFromDateTime($datetime = false){
	    self::validate($datetime);
	    return substr($datetime,17,2);
	}
}

?>
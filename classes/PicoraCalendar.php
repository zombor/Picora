<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraCalendar',$path.'/classes/PicoraCalendar.php');
//@@PicoraView PicoraView::addMethod('calendar',array('PicoraCalendar','viewHelper'));

class PicoraCalendar {
	static protected $month_endings = array(31,28,31,30,31,30,31,31,30,31,30,31);
	
	final static public function dayNameFromNumber($i){
		static $days;
		if(!isset($days)){
			$days = array();
			for($n = 0,$t = 3 * 86400; $n < 7; $n++, $t += 86400)
				$days[$n + 1] = ucfirst(gmstrftime('%A',$t));
		}
		return $days[$i];
	}
	
	final static public function monthNameFromNumber($i){
		static $months;
		if(!isset($months)){
			$months = array();
			for($n = 1; $n <= 12; ++$n)
				$months[$n] = gmstrftime('%B',gmmktime(0,0,0,$n,1,1970));
		}
		return $months[$i];
	}
	
	final static protected function getMonthEnding($month,$year){
		if ($month < 1 || $month > 12)
			return 0;
		$day = self::$month_endings[$month - 1];
		if($month == 2 && ($year % 4) == 0)
			$day = (($year % 100) == 0) ? (($year % 400) == 0 ? 29 : 28) : 29;
		return $day;
	}
	
	final static protected function adjustDate($month,$year){
		$a = array();  
		$a[0] = $month;
		$a[1] = $year;
		while($a[0] > 12){
			$a[0] -= 12;
			$a[1]++;
		}
		while($a[0] <= 0){
			$a[0] += 12;
			$a[1]--;
		}
		return $a;
	}
	
	protected $params = array(
		'class_names' => array(
			'table' => 'calendar',
			'header' => 'calendar_header',
			'day_names' => 'calendar_day_names',
			'week' => 'calendar_week',
			'day' => 'calendar_day',
			'today' => 'calendar_today',
			'weekday' => 'calendar_weekday',
			'weekend' => 'calendar_weekend',
			'empty' => 'calendar_empty'
		),
		'header' => true,
		'day_names' => true,
		'footer' => true,
		'short_day_names' => false,
		'start_day' => 7,
		'day_callback' => false
	);
	protected $year = false;
	protected $month = false;
	
	final public function __construct($month = false,$year = false,$params = false){
		$current_date = getdate(time());
		$this->month = (!$month) ? $current_date['mon'] : $month;
		$this->year = (!$year) ? $current_date['year'] : $year;
		if(!$params)
			$params = array();
		foreach($params as $key => $value)
			$this->params[$key] = $value;
	}
	
	final public function getMonth(){
		return $this->month;
	}
	
	final public function getYear(){
		return $this->year;
	}
	
	final protected function buildLinkHtml($link,$day){
		if(is_array($link)){
			$output = '<a';
			foreach($link as $key => $value)
				$output .= ' '.$key.'="'.$value.'"';
			return $output.'>'.$day.'</a>';
		} else
			return '<a href="'.$link.'">'.$day.'</a>';
	}
		
	final public function display(){
		//setup
		$output = '';
		list($month,$year) = self::adjustDate($this->month,$this->year);
		$days_in_month = self::getMonthEnding($month,$year);
		$date = getdate(mktime(12,0,0,$month,1,$year));
		$first_day_number = $date['wday'];
		$month_name = self::monthNameFromNumber($month);
		$day_names = array();
		for($i = 0; $i < 7 ; ++$i)
			$day_names[] = self::dayNameFromNumber(((($this->params['start_day']) + $i) % 7) + 1);
		if($this->params['short_day_names'])
			foreach($day_names as $i => $day_name)
				$day_names[$i] = substr($day_name,0,1);
		
		//render header
		$output = '';
		$attributes_html = '';
		foreach(array('cellpadding','cellspacing','border','width','height') as $item)
			if(isset($this->params[$item]))
				$attributes_html = ' '.$item.'="'.$this->params[$item].'"';
		$output .= '<table'.$attributes_html.' class="'.$this->params['class_names']['table'].'">'.chr(10);
		if($this->params['header'])
			$output .= $this->renderHeader($month_name,$year);
		if($this->params['day_names'])
			$output .= $this->renderDayNames($day_names);
		
		//render days
		$day = $this->params['start_day'] + 1 - $first_day_number;
		while($day > 1)
			$day -= 7;
		while($day <= $days_in_month){
			$days = array();
			for($i = 0; $i < 7; $i++){
				$days[] = ($day > 0 && $day <= $days_in_month) ? new PicoraCalendarDay($year,$month,$day) : false;
				$day++;
			}
			$output .= $this->renderWeek($days);
		}

		//render footer
		if($this->params['footer'])
			$output .= $this->renderFooter();
		$output .= '</table>';
		return $output;
	}
	
	protected function renderHeader($month_name,$year){
		return chr(9).'<tr class="'.$this->params['class_names']['header'].'"><td colspan="7">'.$month_name.' '.$year.'</td></tr>'.chr(10);
	}
	
	protected function renderDayNames($day_names){
		$output = chr(9).'<tr class="'.$this->params['class_names']['day_names'].'">'.chr(10);
		foreach($day_names as $day_name)
			$output .= chr(9).chr(9).'<td>'.$day_name.'</td>'.chr(10);
		$output .= chr(9).'</tr>'.chr(10);
		return $output;
	}
	
	protected function renderWeek($days){
		$output = chr(9).'<tr class="'.$this->params['class_names']['week'].'">'.chr(10);
		foreach($days as $day)
			$output .= ($day instanceof PicoraCalendarDay) ? $this->renderDay($day) : chr(9).chr(9).'<td class="'.$this->params['class_names']['empty'].'">&nbsp;</td>'.chr(10);
		$output .= chr(9).'</tr>'.chr(10);
		return $output;
	}
	
	protected function renderDay(PicoraCalendarDay $day){
		$output = chr(9).chr(9).'<td class="'.$this->params['class_names']['day'].($day->isToday() ? ' '.$this->params['class_names']['today'] : '').' '.$this->params['class_names'][($day->isWeekDay() ? 'weekday' : 'weekend')].'">';
		$output .= ($this->params['day_callback']) ? call_user_func($this->params['day_callback'],$day,$this) : '<p>'.$day->getDay().'</p>';
		$output .= '</td>'.chr(10);
		return $output;
	}
	
	protected function renderFooter(){
		return '';
	}
	
	final public function __toString(){
		return $this->display();
	}
	
	static public function viewHelper($month = false,$year = false,$params = false){
		return new PicoraCalendar($month,$year,$params);
	}
}

class PicoraCalendarDay {
	static protected $today = false;
	protected $year = '0000';
	protected $month = '00';
	protected $day = '00';
	protected $date = array();
	public function __construct($year,$month,$day){
		$this->year = $year;
		$this->month = $month;
		$this->day = $day;
		$this->date = getdate(mktime(0,0,0,(int)$this->month,(int)$this->day,(int)$this->year));
		if(!self::$today)
			self::$today = getdate(time());
	}
	
	public function getYear(){
		return $this->year;
	}
	
	public function getMonth($pad = false){
		return ($pad ? str_pad($this->month,2,'0',STR_PAD_LEFT) : $this->month);
	}
	
	public function getDay($pad = false){
		return ($pad ? str_pad($this->day,2,'0',STR_PAD_LEFT) : $this->day);
	}
	
	public function isToday(){
		return ($this->year == self::$today['year'] && $this->month == self::$today['mon'] && $this->day == self::$today['mday']);
	}
	
	public function isWeekday(){
		return (!($this->getDayName() == PicoraCalendar::dayNameFromNumber(7) || $this->getDayName() == PicoraCalendar::dayNameFromNumber(1)));
	}
	
	public function getDayName(){
		return $this->date['weekday'];
	}
	
	public function getMonthName(){
		return $this->date['month'];
	}
}

?>
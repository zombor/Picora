<?php

class ApplicationController extends PicoraController {
	static public function layout($main){
		return self::render('views/layout.php',array('main'=>$main))->display();
	}
	
	public function error(){
		return self::render('views/error.php');
	}
	
	public function welcome(){
		return self::render('views/welcome.php');
	}
}

?>
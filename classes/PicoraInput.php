<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraInput',$path.'/classes/PicoraInput.php');
//@@PicoraView PicoraView::addMethod('input',array('PicoraInput','viewHelper'));

class PicoraInput {
	const DEFAULT_ERROR_CLASS = 'error';
	const DEFAULT_LABEL_CLASS = 'label';
	
	static public function viewHelper($name,$params = false,$print_label = false){
		return new PicoraInput($name,$params,$print_label);
	}
	
	static public function htmlAttributes($attributes,$except = false,$encode = true){
		if(!$except)
			$except = array();
		$attributes_output = '';
		if($attributes){
			$attributes_output = ' ';
			foreach($attributes as $key => $value)
				if(!in_array($key,$except))
					$attributes_output .= $key.'="'.($encode ? htmlspecialchars($value,ENT_COMPAT,'utf-8') : $value).'" ';
			$attributes_output = substr($attributes_output,0,-1);
		}
		return $attributes_output;
	}

	//static public function setFlash
		//return $flash[$this->params['name'].'.value'];
		//return $flash[$this->params['name'].'.errors'];
	
	
	protected $params = array();
	protected $record = false;
	protected $print_label = false;
	public function __construct($name,$params = false,$print_label = false){
		$this->printLabel = $print_label;
		foreach(PicoraEvent::getObserverList('PicoraInput.beforeCreate') as $callback)
			call_user_func($callback,$this);
		
		//setup params
		if(!$params)
			$params = array();
		foreach($params as $key => $value)
			$this->params[$key] = $value;
			
		//setup name and type
		if(!isset($this->params['type']))
			$this->params['type'] = 'text';
		if(is_array($name))
			list($this->params['name'],$this->record) = $name;
		else
			$this->params['name'] = $name;
		
		//if this is for a database record, automatically set the name and id params
		if($this->record){
			$record_id = ($this->record->id === false) ? 'new' : $this->record->id;
			$record_class = get_class($this->record);
			if(!isset($this->params['id']))
				$this->params['id'] = $record_class.'_'.$record_id.'_'.$this->params['name'];
			if(!isset($this->params['name']))
				$this->params['name'] = $record_class.'['.$record_id.']['.$this->params['name'].']';
		}
		
		//set default class names since IE can't detect the type with CSS
		if(!isset($this->params['class'])){
			switch($this->params['type']){
				case 'text': $this->params['class'] = 'text'; break;
				case 'checkbox': $this->params['class'] = 'checkbox'; break;
				case 'radio': $this->params['class'] = 'radio'; break;
				case 'password': $this->params['class'] = 'password'; break;
			}
		}
		
		//set this as an error if there is an error in the flash
		if(count($this->getErrorsInFlash())){
			if(isset($this->params['class']))
				$this->params['class'] .= ' '.self::DEFAULT_ERROR_CLASS;
			else
				$this->params['class'] = self::DEFAULT_ERROR_CLASS;
		}
		
		//set the value from the flash if we can
		$value = $this->getValueFromFlash();
		if($this->params['type'] != 'checkbox' && $this->params['type'] != 'radio' && $this->params['type'] != 'select'){	
			if($value !== null)
				$this->params['value'] = $value;
		}elseif($this->params['type'] == 'select'){
			if($value !== null)
				$this->params['selected'] = $value;
			if(isset($this->params['selected']) && $this->params['selected'])
				$this->params['selected'] = (isset($this->params['multiple']) && $this->params['multiple'] && is_array($this->params['selected'])) ? array_map('strval', array_values($this->params['selected'])) : $this->params['selected'];
			else
				$this->params['selected'] = false;
			if(!isset($params['selected']) && isset($this->params['value']))
				$this->params['selected'] = $this->params['value'];
			if(isset($this->params['multiple']) && $this->params['multiple'])
				$this->params['name'] .= '[]';
		}elseif($this->params['type'] == 'radio' || $this->params['type'] == 'checkbox'){
			if(isset($this->params['value']) && $value !== null && $this->params['value'] == $value)
				$this->params['checked'] = true;
			if(!isset($this->params['value']) && $this->record && $this->record->{$this->params['name']} === true)
				$this->params['checked'] = true;
		}
		
		//else make it an empty string
		if(!isset($this->params['value']))
			$this->params['value'] = '';
		
		foreach(PicoraEvent::getObserverList('PicoraInput.afterCreate') as $callback)
			call_user_func($callback,$this);
	}
	
	final protected function getErrorsInFlash(){
		if(!class_exists('PicoraController'))
			return array();
		$flash = PicoraController::getFlash();
		if($this->record){
			$key = $this->record->getKeyForFlash($this->params['name']).'.errors';
			if(isset($flash[$key]))
				return $flash[$key];
		}elseif(isset($flash[$this->params['name'].'.errors']))
			return $flash[$this->params['name'].'.errors'];
	}
	
	final protected function getValueFromFlash(){
		if(!class_exists('PicoraController'))
			return null;
		$flash = PicoraController::getFlash();
		if($this->record){
			$key = $this->record->getKeyForFlash($this->params['name']).'.value';
			if(isset($flash[$key]))
				return $flash[$key];
		}elseif(isset($flash[$this->params['name'].'.value']))
			return $flash[$this->params['name'].'.value'];
		return null;
	}
	
	final public function getParams(){
		return $this->params;
	}
	
	public function display(){
		foreach(PicoraEvent::getObserverList('PicoraInput.beforeDisplay') as $callback)
			call_user_func($callback,$this);
		$output = '';
		switch($this->params['type']){
			case 'text':
			case 'hidden':
			case 'button':
			case 'file':
			case 'image':
			case 'password':
			case 'reset':
			case 'submit':
				$output = PicoraView::tag('input',array_merge($this->params,array('value'=>htmlspecialchars($this->params['value'],ENT_COMPAT,'utf-8'))));
				break;
			case 'textarea':
				$output = '<textarea'.self::htmlAttributes($this->params,array('type','value')).'>'.htmlspecialchars($this->params['value'],ENT_COMPAT,'utf-8').'</textarea>';
				break;
			case 'radio':
				$output = '<input type="radio"'.self::htmlAttributes($this->params,array('checked','type')).(isset($this->params['checked']) && ($this->params['checked'] == true || $this->params['checked'] == 1 || $this->params['checked'] == 'true') ? ' checked="checked"' : '').'/>';
				break;
			case 'checkbox':
				$output = '<input type="hidden" name="'.$this->params['name'].'" value="0"/><input type="checkbox" value="1"'.self::htmlAttributes($this->params,array('checked','value')).(isset($this->params['checked']) && ($this->params['checked'] == true || $this->params['checked'] == 1 || $this->params['checked'] == 'true')? ' checked="checked"' : '').'/>';
				break;
			case 'select':
				$output = '<select';
				$output .= self::htmlAttributes($this->params,array('options','selected','value')).'>';
				if(isset($this->params['options']) && is_array($this->params['options'])){
					foreach($this->params['options'] as $value => $name){
						$outut .= '<option value="'.htmlspecialchars($value,ENT_COMPAT,'utf-8').'"';
						if(isset($this->params['selected']) && ((is_array($this->params['selected']) && in_array($value,$this->params['selected'])) || (!is_array($this->params['selected']) && $this->params['selected'] == $value)))
							$output .= ' selected="selected"';
						$output .='>'.htmlspecialchars($name,ENT_COMPAT,'utf-8').'</option>';
					}
				}
				$output .= '</select>';
				break;
		}
		foreach(PicoraEvent::getObserverList('PicoraInput.afterDisplay') as $callback)
			call_user_func($callback,$outut);
		return $output;
	}
	
	public function getLabel($text = null,$note = false,$params = false){
		if($text === null)
			$text = ucwords(str_replace('_',' ',$this->params['name']));
		if($text === null || $text == '' || !$text)
			return '';
		if(!$params)
			$params = array();
		if(!isset($params['tag']))
			$params['tag'] = 'p';
		$errors = $this->getErrorsInFlash();
		if(!isset($params['class'])){
			$params['class'] = self::DEFAULT_LABEL_CLASS;
			if(count($errors))
				$params['class'] .= ' '.self::DEFAULT_ERROR_CLASS;
		}
		return '<'.$params['tag'].self::htmlAttributes($params,array('tag')).'><label for="'.$this->params['name'].'">'.$text.'</label>'.($note ? '<span>'.$note.'</span>' : '').'</'.$params['tag'].'>';
	}
	
	
	final public function __toString(){
		return ($this->printLabel ? $this->getLabel($this->printLabel) : '').$this->display();
	}
	
	final public function __get($key){
		return $this->params[$key];
	}
	
	final public function __set($key,$value){
		$this->params[$key] = $value;
	}
}

?>
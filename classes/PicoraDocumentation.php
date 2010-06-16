<?php


//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraDocumentation',$path.'/classes/PicoraDocumentation.php');
//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraDocumentationFunction',$path.'/classes/PicoraDocumentation.php');
//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraDocumentationClass',$path.'/classes/PicoraDocumentation.php');
//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraDocumentationMethod',$path.'/classes/PicoraDocumentation.php');

/**
 * @introduction Provides support methods for other PicoraDocumentation* classes.
 */
class PicoraDocumentation {	
	/**
	 * Only functions that have a valid doc comment will be included in the list.
	 * @return array of PicoraDocumentationFunction objects for user defined functions
	 */
	static public function getFunctionList(){
		$functions = get_defined_functions();
		$list = array();
		foreach($functions['user'] as $name){
			$function = new PicoraDocumentationFunction($name);
			if($function['description'] == '' && !isset($function['introduction']) && !count($function['params']))
				continue;
			$list[$name] = $function;
		}
		ksort($list);
		return $list;
	}
	
	/**
	 * Only classes that have a valid doc comment will be included in the list.
	 * @return array of PicoraDocumentationClass objects for user defined classes
	 */
	static public function getClassList(){
		$list = array();
		foreach(get_declared_classes() as $name){
			$class = new PicoraDocumentationClass($name);
			if($class->isInternal())
				continue;
			if($class['description'] == '' && !isset($class['introduction']) && !count($class['params']))
				continue;
			$list[$name] = $class;
		}
		ksort($list);
		return $list;
	}
	
	/**
	 * Parses a doc comment string into an array of tokens (any comment line beginning with @), and a description.
	 * @param string $comment in PHP/JavaDoc format
	 * @return array tokens, array 'params' and string 'description' are always present
	 */
	static public function arrayFromDocComment($comment){
		$tolkens = array(
			'params' => array(),
			'description' => array(),
			'events' => array()
		);
		foreach(explode(chr(10),$comment) as $line){
			$line = preg_replace('/^\s+/','',$line);
			$line = preg_replace('/^\* /','',$line);
			if($line == '/**' || $line == '*/' || $line == '*' || preg_match('/^\*\s*$/',$line))
				continue;
			if(strpos($line,'@') === 0){
				if(preg_match('/^\@([^\s]+)\s+(.+)$/',$line,$matches)){
					if($matches[1] == 'param')
						$tolkens['params'][] = $matches[2];
					elseif($matches[1] == 'event')
						$tolkens['events'][] = $matches[2];
					else
						$tolkens[$matches[1]] = $matches[2];
				}
			}else
				$tolkens['description'][] = $line;
		}
		$tolkens['description'] = implode(chr(10),$tolkens['description']);
		return $tolkens;
	}
	
	/**
	 * @param ReflectionClass $class
	 */
	static public function getImplementedClassInterfaceList(ReflectionClass $class){
		$implements = array();
		foreach(get_declared_interfaces() as $interface)
			if($class->implementsInterface($interface))
				$implements[] = $interface;
		return $implements;
	}
	
	/**
	 * @param ReflectionClass $class
	 */
	static public function getClassParentList(ReflectionClass $class){
		$parents = array();
		while($parent = $class->getParentClass()){
			$parents[] = $parent->getName();
			$class = $parent;
		}
		return $parents;
	}
	
	/**
	 * @param object $r any Reflection class or PicoraDocumentation class
	 * @return array declaring lines of the given class, method or function
	 */
	static public function codeArrayFromReflectionObject($r){
		return array_slice(file($r->getFileName()),($r->getStartLine() - 1),(($r->getEndLine() - $r->getStartLine()) + 1),true);
	}
	
	/**
	 * Match a PicoraDocumentationFunction, PicoraDocumentaitonClass or PicoraDocumentationMethod against a query string.
	 * @param object $object
	 * @param string $search_terms
	 * @return bool
	 */
	static public function match($object,$search_terms){
		if(strlen($search_terms) <= 2)
			return false;
		$score = 0;
		if(strcasecmp($object->name,$search_terms) == 0 || stripos($object->name,$search_terms) !== false)
			$score += 1000;
		if(isset($object['introduction']) && stripos($object['introduction'],$search_terms) !== false)
			$score += 100;
		$params = $object['params'];
		foreach($params as $param)
			if(stripos($param,'$'.$search_terms) !== false || stripos($param,$search_terms) !== false)
				$score += 100;
		if(isset($object['description']) && stripos($object['description'],$search_terms) !== false)
			$score += 25;
		return $score;
		/*
			return true;
		if($object->name == $search_terms)
			return true;
		if(strpos(strtolower($object->name),$search_terms) !== false)
			return true;
		if(strlen($search_terms) <= 3)
			return false;
		foreach($object['params'] as $param)
			if(strpos(strtolower($param),'$'.$search_terms) !== false || strpos(strtolower($param),$search_terms) !== false)
				return true;
		//if(strpos(strtolower(array_pop(explode('/',$object['file']))),$search_terms) !== false)
		//	return true;
		return false;
		*/
	}
}

/**
 * Similar to a ReflectionFunction object, except that when used as an array, you can access all of the comment information.
 * 
 * <pre class="highlighted"><code class="php">$f = new PicoraDocumentationFunction('my_documented_function');
 * $f->name; //my_documented_function
 * $f['params']; //array of params (if any)
 * $f['description']; //string description in doc comment</code></pre>
 * @introduction Superset of ReflectionFunction
 */
class PicoraDocumentationFunction extends ReflectionFunction implements ArrayAccess {
	protected $comments = array();
	public function __construct($name){
		parent::__construct($name);
		$this->comments = PicoraDocumentation::arrayFromDocComment($this->getDocComment());
		$this->comments['name'] = $name;
		$this->comments['file'] = $this->getFileName();
	}
	
	public function offsetExists($key){return isset($this->comments[$key]);}
	public function offsetSet($key,$value){$this->comments[$key] = $value;}
	public function offsetGet($key){return $this->comments[$key];}
	public function offsetUnset($key){unset($this->comments[$key]);}
}

/**
 * Similar to a ReflectionClass object, except that when used as an array, you can access all of the comment information, and when calling getMethods() and getMethod() you get PicoraDocumentationMethod objects back instead of ReflectionMethod objects.
 *
 * <pre class="highlighted"><code class="php">$c = new PicoraDocumentationClass('MyDocumentedClass');
 * $c->name; //MyDocumentedClass
 * $c['description']; //string description in doc comment</code></pre>
 *
 * You also get the following extra properties:
 * 
 * - string file
 * - string visibility
 * - array extends
 * - array implements
 * 
 * Methods are sorted by the following scoring order:
 * 
 * - is static
 * - visibility
 * - alphabetical
 *
 * @introduction Superset of ReflectionClass
 */
class PicoraDocumentationClass extends ReflectionClass implements ArrayAccess {
	protected $comments = array();
	public function __construct($name){
		parent::__construct($name);
		$this->comments = PicoraDocumentation::arrayFromDocComment($this->getDocComment());
		$this->comments['file'] = $this->getFileName();
		$this->comments['visibility'] = '';
		if($this->isAbstract())
			$this->comments['visibility'] .= 'abstract ';
		if($this->isFinal())
			$this->comments['visibility'] .= 'final ';
		$this->comments['extends'] = PicoraDocumentation::getClassParentList($this);
		$this->comments['implements'] = PicoraDocumentation::getImplementedClassInterfaceList($this);
		$this->comments['name'] = $name;
	}
	
	public function getMethods(){
		$methods = array();
		foreach(parent::getMethods() as $method){
			$m = $this->getMethod($method->name);
			if($m->getDeclaringClass()->name == $this->name && (isset($m['return']) || count($m['params']) || $m['description'] != ''))
				$methods[] = $m;
		}
		usort($methods,array('PicoraDocumentationClass','sort'));
		return $methods;
	}

	static public function sort($a,$b){
		$a_score = self::scoreFromMethod($a);
		$b_score = self::scoreFromMethod($b);
		return ($a_score == $b_score) ? 0 : ($a_score < $b_score ? -1 : 1);
	}
	
	static protected function scoreFromMethod(PicoraDocumentationMethod $m){
		return array_sum(array(
			($m->isStatic() ? -100000 : 0),
			($m->isPublic() ? -10000 : 0),
			($m->isProtected() ? -1000 : 0),
			($m->isPrivate() ? -100 : 0),
			ord(substr($m->name,0,1))
		));
	}
	
	public function getMethod($name){
		return new PicoraDocumentationMethod($this->name,$name);
	}
	
	public function offsetExists($key){return isset($this->comments[$key]);}
	public function offsetSet($key,$value){$this->comments[$key] = $value;}
	public function offsetGet($key){return $this->comments[$key];}
	public function offsetUnset($key){unset($this->comments[$key]);}
}

/**
 * Similar to a ReflectionMethod object, except that when used as an array, you can access all of the comment information.
 * 
 * <pre class="highlighted"><code class="php">$c = new PicoraDocumentationMethod('MyDocumentedClass','myDocumentedMethod');
 * $c->name; //myDocumentedMethod
 * $c['description']; //string description in doc comment</code></pre>
 *
 * You also get the following extra properties:
 * 
 * - string file
 * - string class
 * - string visibility
 * @introduction Superset of ReflectionMethod
 */
class PicoraDocumentationMethod extends ReflectionMethod implements ArrayAccess {
	protected $comment = array();
	public function __construct($class,$name){
		parent::__construct($class,$name);
		$this->comments = PicoraDocumentation::arrayFromDocComment($this->getDocComment());
		$this->comments['name'] = $name;
		$this->comments['class'] = $class;
		$this->comments['file'] = $this->getFileName();
		$this->comments['visibility'] = '';
		foreach(array('abstract','final','static','public','protected','private') as $v)
			if($this->{'is'.ucfirst($v)}())
				$this->comments['visibility'] .= $v.' ';
	}
	
	public function offsetExists($key){return isset($this->comments[$key]);}
	public function offsetSet($key,$value){$this->comments[$key] = $value;}
	public function offsetGet($key){return $this->comments[$key];}
	public function offsetUnset($key){unset($this->comments[$key]);}
}

?>
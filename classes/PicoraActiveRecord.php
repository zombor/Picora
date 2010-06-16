<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraActiveRecord',$path.'/classes/PicoraActiveRecord.php');
//@@config define('CONNECTION_STRING','mysql://username:password@localhost/database_name');

/**
 * Documentation for this class is coming soon.
 * 
 * <!-- - Subclassing
 * - Connecting
 * - Constants
 * 	- TABLE_NAME
 * 	- PRIMARY_KEY
 * - Relationships
 * - Validation
 * - Lifecycle -->
 * 
 * @introduction Database independent object relational mapper.
 * @event void CLASS_NAME.beforeCreate(PicoraActiveRecord object)
 * @event void CLASS_NAME.afterCreate(PicoraActiveRecord object)
 * @event void CLASS_NAME.beforeSave(PicoraActiveRecord object)
 * @event void CLASS_NAME.afterSave(PicoraActiveRecord object)
 * @event void CLASS_NAME.beforeDelete(PicoraActiveRecord object)
 * @event void CLASS_NAME.afterDelete(PicoraActiveRecord object)
 * @event void CLASS_NAME.afterFind(PicoraActiveRecord object)
 */
class PicoraActiveRecord implements ArrayAccess {
	//class
	static protected $__connection__ = false;
	static protected $__connection_type__ = false;
	static protected $__table_names__ = array();
	static protected $__primary_keys__ = array();
	static protected $__relationships__ = array();
	static protected $__protected_field_names__ = array('__class_name__','__error_list__');
	static protected $__behaviors__ = array();
	
	/**
	 * Example connection strings:
	 * 
	 * <pre class="highlighted"><code class="php">PicoraActiveRecord::connect('sqlite://relative_path_to_sqlite_database');
	 * PicoraActiveRecord::connect('mysql://user:password@host/database_name');</code></pre>
	 *
	 * @param string $connection_string
	 * @return bool
	 */
	final static public function connect($connection_string,$username = false,$password = false,$driver_options = false){
		if(preg_match('|sqlite\://(.+)|',$connection_string)){
			self::$__connection_type__ = 'sqlite';
			if(!preg_match('|sqlite\://(.+)|',$connection_string,$match))
				throw new Exception('SQLite connection string should be in the following format: sqlite://path/to/file.db');
			$file = $match[1];
			$error = '';
			if(!is_writable($file) || !is_readable($file))
				throw new Exception($file." must be writable.");
			if(!is_writable(dirname($file)) || !is_readable(dirname($file)))
				throw new Exception(dirname($file)."/ must be writable.");
			self::$__connection__ = new SQLiteDatabase($file,0666,$error);
			if(!self::$__connection__)
				throw new Exception($error);
			self::$__connection__->busyTimeout(30000);
			self::$__connection__->query('PRAGMA short_column_names = 1;');
			return true;
		}elseif(preg_match('|mysql\://(.+)|',$connection_string)){
			self::$__connection_type__ = 'mysql';
			if(!preg_match('|mysql\://([^:]+):?([^@]*)@([^/]+)/(.+)|',$connection_string,$match))
				throw new Exception('MySql connection string should be in the following format: mysql://username:password@host/database_name');
			self::$__connection__ = mysql_connect($match[3],$match[1],$match[2],true);
			if(!self::$__connection__)
				throw new Exception('Could not connect to MySQL. Tried to connect to MySQL at '.$match[3].'. MySQL issued this error: "'.mysql_error().'"');
			if(!mysql_select_db($match[4],self::$__connection__))
				throw new Exception('Could not Select Database. Tried to select the database '.$match[4].'. MySQL issued this error: "'.mysql_error().'"');
			@mysql_query("SET NAMES 'utf8';",self::$__connection__);
			return true;
		}else{
			self::$__connection_type__ = 'pdo';
			self::$__connection__ = new PDO($connection_string,$username,$password,$driver_options);
			return true;
		}
	}
	
	/**
	 * Returns a PDO object, SQLiteDatabase object or a MySQL connection resource
	 * @return mixed 
	 */
	final static public function getCurrentConnection(){
		return self::$__connection__;
	}
	
	/**
	 * Returns 'sqlite', 'mysql' or 'pdo'
	 * @return string
	 */
	final static public function getCurrentConnectionType(){
		return self::$__connection_type__;
	}
	
	
	final static public function addBehavior($class_name,$behavior_name){
		if(!isset(self::$__behaviors__[$class_name]))
			self::$__behaviors__[$class_name] = array();
		self::$__behaviors__[$class_name][$behavior_name] = array_slice(func_get_args(),2);
	}
	
	final static public function getBehaviorList($class_name){
		return (!isset(self::$__behaviors__[$class_name])) ? array() : self::$__behaviors__[$class_name];
	}
	
	/**
	 * @param string $class_name
	 * @param string $relationship_type
	 * @param string $related_class_name
	 * @param string $foreign_key
	 * @param array $params
	 * @return void
	 */
	final static public function addRelationship($class_name,$relationship_type,$related_class_name,$foreign_key,$params = false){
		if(!isset(self::$__relationships__[$class_name]))
			self::$__relationships__[$class_name] = array();
		self::$__relationships__[$class_name][] = ($relationship_type == 'has_and_belongs_to_many')
			? array_slice(func_get_args(),1)
			: array_merge(array_slice(func_get_args(),1,3),($params ? $params : array()))
		;
	}
	
	/**
	 * @param string $class_name
	 * @return array
	 */
	final static public function getRelationshipList($class_name){
		return (!isset(self::$__relationships__[$class_name])) ? array() : self::$__relationships__[$class_name];
	}
	
	/**
	 * @param string $class_name
	 * @return string
	 */
	final static public function tableNameFromClassName($class_name){
		if(!isset(self::$__table_names__[$class_name])){
			if(!class_exists($class_name))
				$table_name = $class_name;
			else{
				$table_name = (defined($class_name.'::TABLE_NAME') ? constant($class_name.'::TABLE_NAME') : null);
				if($table_name === null)
					$table_name = PicoraSupport::pluralize(strtolower(preg_replace('/([a-z])([A-Z])/e',"'\\1_'.strtolower('\\2')",str_replace('ActiveRecord','',$class_name))));
			}
			self::$__table_names__[$class_name] = $table_name;
		}
		return self::$__table_names__[$class_name];
	}
	
	/**
	 * @param string $class_name
	 * @return string
	 */
	final static public function primaryKeyNameFromClassName($class_name){
		if(!isset(self::$__primary_keys__[$class_name])){
			if(!class_exists($class_name))
				$primary_key = 'id';
			else{
				$primary_key = (defined($class_name.'::PRIMARY_KEY') ? constant($class_name.'::PRIMARY_KEY') : null);
				if($primary_key === null)
					$primary_key = 'id';
			}
			self::$__primary_keys__[$class_name] = $primary_key;
		}
		return self::$__primary_keys__[$class_name];
	}
	
	/**
	 * Escapes (or quotes) any scalar value based on the connection type.
	 * @param mixed $value
	 * @return string
	 */
	final static public function escape($value){
		switch(self::$__connection_type__){
			case 'mysql': return mysql_real_escape_string($value,self::$__connection__);
			case 'sqlite': return sqlite_escape_string($value);
			case 'pdo': return self::$__connection__->quote($value);
		}
	}

	final static protected function getLastInsertId(){
		switch(self::$__connection_type__){
			case 'mysql': return mysql_insert_id(self::$__connection__);
			case 'sqlite': return self::$__connection__->lastInsertRowId();
			case 'pdo': return self::$__connection__->lastInsertId();
		}
	}
	
	/**
	 * @param string $sql
	 * @return mixed
	 */
	final static public function executeQuery($sql){
		if(!self::$__connection__)
			throw new Exception('No active database connection for PicoraActiveRecord was found.');
		switch(self::$__connection_type__){
			case 'mysql': $response = mysql_query($sql,self::$__connection__); break;
			case 'sqlite': $response = self::$__connection__->query($sql); break;
			case 'pdo': $response = self::$__connection__->query($sql); break;
		}
		if(!$response){
			switch(self::$__connection_type__){
				case 'mysql': $error = mysql_error(self::$__connection__); break;
				case 'sqlite': $error = self::$__connection__->lastError(); break;
				case 'pdo': $error = implode(',',self::$__connection__->errorInfo()); break;
			}
			throw new Exception('SQL Query Error:'.$error.'<br/><br/>in query<br/><br/>'.$sql);
		}
		return $response;
	}

	final static protected function buildSQL($class_name,$params,$isCountSQL = false){
		if(isset($params['where']) && is_array($params['where'])){
			$where = '';
			foreach($params['where'] as $key => $value)
				$where .= $key." = '".self::escape($value)."' AND ";
			$params['where'] = substr($where,0,-5);
		}
		if(is_string($params))
			throw new Exception('!');
		$params['tables'] = (isset($params['tables'])
			? (is_string($params['tables']) ? array($params['tables']) : $params['tables'])
			: array()
		);
		if(count($params['tables']) == 0)
			$params['tables'] = array(self::tableNameFromClassName($class_name));
		$sql = ($isCountSQL)
			? 'SELECT COUNT(*) AS count FROM '.$params['tables'][0].(isset($params['join']) ? self::buildJoins($class_name,$params['join']) : '').(isset($params['where']) ? ' WHERE '.$params['where'] : '')
			: 'SELECT '.(isset($params['columns']) ? implode(',',$params['columns']) : '*').' FROM '.(isset($params['mock']) ? $params['mock'] : implode(',',$params['tables'])).' '.
				(isset($params['join']) ? self::buildJoins($class_name,$params['join']) : '').
				(isset($params['where']) ? ' WHERE '.$params['where'] : '').
				(isset($params['order']) ? ' ORDER BY '.$params['order'] : '').
				(isset($params['offset'],$params['limit']) ? ' LIMIT '.$params['offset'].','.$params['limit'] : '').
				(!isset($params['offset']) && isset($params['limit']) ? ' LIMIT '.$params['limit'] : '')
		;
		return $sql;
	}
	
	final static protected function buildJoins($class_name,$joins){
		if(is_string($joins))
			return $joins;
		$table_name = self::tableNameFromClassName($class_name);
		$joinSQL = '';
		foreach($joins as $join_class => $key){
			$join_table_name = self::tableNameFromClassName($join_class);
			$joinSQL .= ' LEFT JOIN '.$join_table_name.' ON '.$table_name.'.'.$key.' = '.$join_table_name.'.id';
		}
		return $joinSQL;
	}
	
	/**
	 * Builds a new subclass instance that is not yet saved.
	 * @param string $class_name
	 * @param array $data
	 * @return object
	 */
	static public function build($class_name,$data = false){
		return new $class_name($data);
	}
	
	/**
	 * Builds a new subclass instance and saves it.
	 * @param string $class_name
	 * @param array $data
	 * @return object
	 */
	static public function create($class_name,$data = false){
		$record = self::build($class_name,$data);
		$record->save();
		return $record;
	}
	
	/**
	 * Params can contain contain anything that can be passed to findAll()
	 * @param string $class_name
	 * @param array $params
	 * @return int
	 */
	static public function count($class_name,$params = false){
		if(!$params)
		 	$params = array();
		if(is_string($params))
			$params = array('where'=>$params);
		$result = self::executeQuery(self::buildSQL($class_name,$params,true));
		switch(self::$__connection_type__){
			case 'mysql': $row = mysql_fetch_array($result,MYSQL_ASSOC); break;
			case 'sqlite': $row = $result->fetch(SQLITE_ASSOC); break;
			case 'pdo': $row = $result->fetch(PDO::FETCH_ASSOC); break;
		}
		return (int)$row['count'];
	}
	
	/**
	 * Similar to findAll() but returns only a single instance no matter how many records are found. Returns false if no record is found.
	 * <pre class="highlighted"><code class="php">PicoraActiveRecord::find('Article',1);
	 * PicoraActiveRecord::find('Article',array('where'=>'id = 1'));</code></pre>
	 * @param string $class_name
	 * @param mixed $id_or_params
	 * @return mixed
	 */
	static public function find($class_name,$id_or_params){
		$params = (!is_array($id_or_params)) ? array('where'=>"id = '".self::escape($id_or_params)."'") : $id_or_params;
		$params['limit'] = 1;
		$results = self::findAll($class_name,$params);
		return (isset($results[0])) ? $results[0] : false;
	}
	
	/**
	 * Find all instances of a given $class_name. You can pass a complete SQL statement, or an array of $params which may contain any of the following:
	 * 
	 * - where - string WHERE SQL fragment or array of (string field_name => mixed value) pairs
	 * - order - string ORDER BY SQL fragment
	 * - offset - int LIMIT SQL fragment
	 * - limit - int LIMIT SQL fragment
	 * - tables - array of tables to include (defaults to the table belonging to the current $class_name)
	 * - columns - array of columns to include (defaults to *)
	 * - join - array of (string join_class => string foreign_key) pairs
	 * 
	 * <pre class="highlighted"><code class="php">$articles = PicoraActiveRecord::findAll('Article');
	 * $articles = PicoraActiveRecord::findAll('Article',array(
	 * 	'where' => array('category'=>'News'),
	 * 	'order' => 'id DESC'
	 * ));</code></pre>
	 * @param string class_name
	 * @param mixed $params_or_sql
	 * @return array
	 */
	static public function findAll($class_name,$params_or_sql = array()){
		$results = array();
		$response = self::executeQuery((!is_array($params_or_sql) ? $params_or_sql : self::buildSQL($class_name,$params_or_sql)));
		$target_class_name = (class_exists($class_name) ? $class_name : 'PicoraActiveRecord');
		switch(self::$__connection_type__){
			case 'mysql':
				while($record = mysql_fetch_object($response,$class_name)){
					$record->notifyObservers('afterFind');
					$results[] = $record;
				}
				break;
			case 'sqlite':
				while($record = $response->fetchObject($class_name)){
					$record->notifyObservers('afterFind');
					$results[] = $record;
				}
				break;
			case 'pdo':
				$results = $response->fetchAll(PDO::FETCH_CLASS);
				break;
		}
		return $results;
	}
	
	/**
	 * @param string $class_name
	 * @param string $field
	 * @param mixed $value
	 * @return mixed
	 */
	static public function findByField($class_name,$field,$value = false){
		$results = self::findAllByField($class_name,$field,$value);
		return (isset($results[0])) ? $results[0] : false;
	}
	
	/**
	 * @param string $class_name
	 * @param string $field
	 * @param mixed $value
	 * @param mixed $params
	 * @return mixed
	 */
	static public function findAllByField($class_name,$field,$value = false,$params = false){
		$params = (is_array($value) ? $value : (is_array($params) ? $params : array()));
		$params['where'] = ($value ? array($field => $value) : $field);
		return self::findAll($class_name,$params);
	}
	
	/**
	 * Finds a given instance, merges $data onto the object, then returns it without saving it.
	 * @param string $class_name
	 * @param mixed $id_or_params
	 * @param array $data
	 * @return object
	 */
	static public function merge($class_name,$id_or_params,$data){
		$record = self::find($class_name,$id_or_params);
		if(!$record)
			return false;
		foreach($data as $key => $value)
			$record->{$key} = $value;
		return $record;
	}
	
	//instance
	protected $__class_name__ = false;
	protected $__error_list__ = array();
	
	/**
	 * @param mixed $data
	 * @return object
	 */
   	final public function __construct($data = false){
		if(!$this->__class_name__)
			$this->__class_name__ = get_class($this);
		$primary_key = self::primaryKeyNameFromClassName($this->__class_name__);
		if(!isset($this->{$primary_key}))
		$this->{$primary_key} = false;
		if($data)
			foreach($data as $key => $value)
				$this->{$key} = $value;
	}
	
	public function getKeyForFlash($field = false){
		return $this->__class_name__.'.'.($this->{self::primaryKeyNameFromClassName($this->__class_name__)} === false ? 'new' : $this->{self::primaryKeyNameFromClassName($this->__class_name__)}).($field ? '.'.$field : '');
	}
	
	/**
	 * @param string $field
	 * @param string $message
	 * @return void
	 */
	final public function addError($field,$message = ''){
		if(!isset($this->__error_list__[$field]))
			$this->__error_list__[$field] = array();
		if(!in_array($message,$this->__error_list__[$field])){
			$this->__error_list__[$field][] = $message;
			$flash =& PicoraController::getFlash();
			$key = $this->getKeyForFlash($field);
			if(!isset($flash[$key]))
				$flash[$key] = array();
			$flash[$key][] = $message;
		}
	}
	
	/**
	 * @return void
	 */
	final public function clearErrorList(){
		$this->__error_list__ = array();
		$flash =& PicoraController::getFlash();
		$key = $this->getKeyForFlash($field);
		if(isset($flash[$key]))
			unset($flash[$key]);
	}
	
	/**
	 * @return void
	 */
	final public function getErrorList(){
		return $this->__error_list__;
	}
	
	/**
	 * Does nothing by default, you can override this in a subclass.
	 */
	public function isValid(){}
	
	/**
	 * Saves a given record, validating it in the process it.
	 * @return bool
	 */
	public function save(){
		if($this->isValid() === false || count($this->__error_list__) > 0)
			return false;
		$response = null;
		if($this->notifyObservers('beforeSave') === false)
			return false;
		if(!$this->id){
			if($this->notifyObservers('beforeCreate') === false)
				return false;
			$keys = '';
			$values = '';
			foreach($this->toArray() as $key => $value){
				if($key == self::primaryKeyNameFromClassName($this->__class_name__))
					continue;
				$keys .= $key.',';
				$values .= "'".self::escape($value)."',";
			}
			if(self::executeQuery('INSERT INTO '.self::tableNameFromClassName($this->__class_name__).' ('.substr($keys,0,-1).') VALUES ('.substr($values,0,-1).')')){
				$this->id = self::getLastInsertId();
				foreach(self::getRelationshipList($this->__class_name__) as $r)
					if($r[0] == 'belongs_to' && isset($r['counter']) && $c = $this->{'get'.$r[1]}())
						$c->updateAttribute($r['counter'],intval($c->{$r['counter']}) + 1);
				$response = true;
			}else
				$response = false;
			$this->notifyObservers('afterCreate');
		}else{
			$sql = 'UPDATE '.self::tableNameFromClassName($this->__class_name__).' SET ';
			foreach($this->toArray() as $key => $value)
				$sql .= $key."='".self::escape($value)."',";
			$response = (self::executeQuery(substr($sql,0,-1)." WHERE ".self::primaryKeyNameFromClassName($this->__class_name__)." = '".self::escape($this->{self::primaryKeyNameFromClassName($this->__class_name__)})."'"));
		}
		$this->notifyObservers('afterSave');
		return $response;
	}
	
	/**
	 * Delete a given record from the database.
	 * @return mixed
	 */
	final public function delete(){
		if(!$this->id)
			throw new Exception('Could not delete '.$this->__class_name__.' instance. Object had no id field.');
		if($this->notifyObservers('beforeDelete') === false)
			return false;
		$response = self::executeQuery('DELETE FROM '.self::tableNameFromClassName($this->__class_name__)." WHERE ".self::primaryKeyNameFromClassName($this->__class_name__)." = '".self::escape($this->{self::primaryKeyNameFromClassName($this->__class_name__)})."'");
		$this->notifyObservers('afterDelete');
		foreach(self::getRelationshipList($this->__class_name__) as $r){
			if($r[0] == 'has_one' && isset($r['dependent']) && $r['dependent'] && $child = $this->{'get'.$r[1]}())
				$child->delete();
			elseif($r[0] == 'has_many' && isset($r['dependent']) && $r['dependent']){
				foreach($this->{'get'.$r[1].'List'}() as $child)
					$child->delete();
			}
		}
		foreach(self::getRelationshipList($this->__class_name__) as $r)
			if($r[0] == 'belongs_to' && isset($r['counter']) && $c = $this->{'get'.$r[1]}())
				$c->updateAttribute($r['counter'],max(0,intval($c->{$r['counter']}) - 1));
		return $response;
	}
	
	/**
	 * Updates all properties in the record from the database.
	 * @return bool
	 */
	final public function reload(){
		if(!$this->{self::primaryKeyNameFromClassName($this->__class_name__)} || !($row = self::find($this->__class_name__,$this->{self::primaryKeyNameFromClassName($this->__class_name__)})))
			return false;
		foreach($row as $key => $value)
			$this->{$key} = $value;
		return true;
	}
	
	/**
	 * Updates a given attribute in the record, saving that attribute in the database without performing validation.
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	final public function updateAttribute($key,$value){
		$this->{$key} = $value;
		if(!$this->id)
			return false;
		return self::executeQuery('UPDATE '.self::tableNameFromClassName($this->__class_name__).' SET '.$key." = '".self::escape($value)."' WHERE ".self::primaryKeyNameFromClassName($this->__class_name__)." = '".self::escape($this->{self::primaryKeyNameFromClassName($this->__class_name__)})."'");
	}
	
	//callbacks	
	final protected function notifyObservers($method_name){
		foreach(PicoraEvent::getObserverList($this->__class_name__.'.'.$method_name) as $callback)
			call_user_func($callback,$this);
		return $this->{$method_name}();
	}
	
	public function beforeCreate(){}
	public function afterCreate(){}
	public function beforeSave(){}
	public function afterSave(){}
	public function beforeDelete(){}
	public function afterDelete(){}
	public function afterFind(){}
	
	//ArrayAccess
	final public function toArray(){
		$a = get_object_vars($this);
		foreach(self::$__protected_field_names__ as $field)
			unset($a[$field]);
		return $a;
	}
	
	final public function offsetExists($key){
		return (!in_array($key,self::$__protected_field_names__)) ? isset($this->{$key}) : false;
	}
	
	final public function offsetGet($key){
		if(!in_array($key,self::$__protected_field_names__))
			return $this->{$key};
		else
			throw new Exception($key.' is a protected property of PicoraActiveRecord');
	}
	
	final public function offsetSet($key,$value){
		if(!in_array($key,self::$__protected_field_names__))
			$this->{$key} = $value;
		else
			throw new Exception($key.' is a protected property of PicoraActiveRecord');
	}
	
	final public function offsetUnset($key){
		if(!in_array($key,self::$__protected_field_names__))
			unset($this->{$key});
		else
			throw new Exception($key.' is a protected property of PicoraActiveRecord');
	}
	
	//relationships	
	public function __call($method,$arguments){
		foreach(self::getRelationshipList($this->__class_name__) as $relationship){
			$relationship_type = $relationship[0];
			$related_class_name = $relationship[1];
			$foreign_key = (isset($relationship[2])) ? $relationship[2] : PicoraSupport::singularize($related_class_name).'_id';
			switch($relationship_type){
				case 'has_one':
					switch($method){
						case 'get'.$related_class_name:
							return self::find($related_class_name,$this->{$foreign_key});
						case 'build'.$related_class_name:
							return self::build($related_class_name,(isset($arguments[0]) ? $arguments[0] : array()));
						case 'create'.$related_class_name:
							$record = self::create($related_class_name,(isset($arguments[0]) ? $arguments[0] : array()));
							if($this->id)
								$this->updateAttribute($foreign_key,$record->id);
							return $record;
					}
					break;
				case 'has_many':
					switch($method){
						case 'delete'.$related_class_name:
							$record = self::find($related_class_name,($arguments[0] instanceof PicoraActiveRecord ? $arguments[0]->{self::primaryKeyNameFromClassName(get_class($arguments[0]))} : $arguments[0]));
							return (!$record) ? false : (bool)$record->delete();
						case 'get'.$related_class_name.'List':
						case 'get'.$related_class_name.'Count':
							$params = (isset($arguments[0]) ? $arguments[0] : array());
							$params['where'] = (isset($params['where']) ? PicoraSupport::formatPropertyString($params['where'],$this).' AND ' : '').$foreign_key.' = '.self::escape($this->{self::primaryKeyNameFromClassName($this->__class_name__)});
							if('get'.$related_class_name.'Count' == $method)
								return self::count($related_class_name,array('where' => $params['where']));
							if(isset($relationship['order']) && !isset($params['order']))
								$params['order'] = $relationship['order'];
							$list = self::findAll($related_class_name,$params);
							if('get'.$related_class_name.'List' == $method)
								return $list;
							foreach($list as $item)
								if(!isset($arguments[0]) || (isset($arguments[0]) && $arguments[0] == $item->{self::primaryKeyNameFromClassName(get_class($item))}))
									$item->delete();
							break;
						case 'create'.$related_class_name:
							return self::create($related_class_name,array_merge((isset($arguments[0]) ? $arguments[0] : array()),array($foreign_key => $this->{self::primaryKeyNameFromClassName($this->__class_name__)})));
						case 'build'.$related_class_name:
							return self::build($related_class_name,array_merge((isset($arguments[0]) ? $arguments[0] : array()),array($foreign_key => $this->{self::primaryKeyNameFromClassName($this->__class_name__)})));
					}
					break;
				case 'belongs_to':
					switch($method){
						case 'get'.$related_class_name:
							return self::find($related_class_name,$this->{$foreign_key});
						case 'build'.$related_class_name == $method:
						case 'create'.$related_class_name == $method:
							$record = self::build($related_class_name,(isset($arguments[0]) ? $arguments[0] : array()));
							if(isset($relationship['counter']))
								$record->{$relationship['counter']} = 1;
							if('build'.$related_class_name == $method)
								return $record;
							if($record->save() && $this->{self::primaryKeyNameFromClassName($this->__class_name__)})
								$this->updateAttribute($foreign_key,$record->{self::primaryKeyNameFromClassName(get_class($record))});
							return $record;
					}
					break;
				case 'has_and_belongs_to_many':
					$source_class_name = $this->__class_name__;
					$source_table_name = self::tableNameFromClassName($this->__class_name__);
					$target_class_name = $related_class_name;
					$target_table_name = self::tableNameFromClassName($related_class_name);
					$link_class_name = $relationship[2];
					$link_table_name = self::tableNameFromClassName($relationship[2]);
					$source_key = $relationship[3];
					$target_key = $relationship[4];
					$params = (isset($arguments[0]) ? $arguments[0] : array());
					switch($method){
						case 'set'.$related_class_name.'List':
							$links = array();
							self::executeQuery('DELETE FROM '.$link_table_name.' WHERE '.$source_key.' = '.self::escape($this->id));
							foreach((isset($arguments[0]) ? $arguments[0] : array()) as $id)
								$links[] = self::create($link_class_name,array(
									$source_key => $this->{self::primaryKeyNameFromClassName($this->__class_name__)},
									$target_key => ($id instanceof PicoraActiveRecord ? $id->{self::primaryKeyNameFromClassName(get_class($id))} : $id)
								));
							return $links;
						case 'add'.$related_class_name:
							return self::create($link_class_name,array(
								$source_key => $this->{self::primaryKeyNameFromClassName($this->__class_name__)},
								$target_key => ($arguments[0] instanceof PicoraActiveRecord ? $arguments[0]->{self::primaryKeyNameFromClassName(get_class($arguments[0]))} : $arguments[0])
							));
						case 'remove'.$related_class_name:
							self::executeQuery('DELETE FROM '.$link_table_name.' WHERE '.$source_key.' = '.self::escape($this->{self::primaryKeyNameFromClassName($this->__class_name__)}).' AND '.$target_key.' = '.self::escape(($arguments[0] instanceof PicoraActiveRecord ? $arguments[0]->{self::primaryKeyNameFromClassName(get_class($argument[0]))} : $arguments[0])));
							break;
						case 'has'.$related_class_name:
							return (bool)(self::find($link_class_name,array('where'=>array(
								$source_key => $this->id,
								$target_key => ($arguments[0] instanceof PicoraActiveRecord ? $arguments[0]->{self::primaryKeyNameFromClassName(get_class($arguments[0]))} : $arguments[0])
							))));
						case 'get'.$related_class_name.'List':
						case 'get'.$related_class_name.'Count':
							$params['tables'] = $link_table_name;
							$params['join'] = 'LEFT JOIN '.$target_table_name.' ON ('.$link_table_name.'.'.$target_key.' = '.$target_table_name.'.'.self::primaryKeyNameFromClassName($target_class_name).')';
							$condition = $link_table_name.'.'.$source_key.' = '.$this->{self::primaryKeyNameFromClassName($this->__class_name__)};
							$params['where'] = (isset($params['where']) ? $params['where'].' AND ' : '').(isset($relationship['where']) ? $relationship['where'].' AND '.$condition : $condition);
							if(isset($relationship['order']) && !isset($params['order']))
								$params['order'] = $relationship['order'];
							if('get'.$related_class_name.'Count' == $method)
								return self::count($target_class_name,array('where' => $params['where'],'join' => $params['join']));
							$instance_list = self::findAll($target_class_name,$params);
							foreach($instance_list as $instance)
								unset($instance->{$target_key},$instance->{$source_key});
							if('get'.$related_class_name.'List' == $method)
								return $instance_list;
							break;
					}
					break;
			}
		}
		foreach(PicoraEvent::getObserverList('PicoraActiveRecord.call') as $callback){
			$response = call_user_func($callback,$this,$method,$arguments);
			if(!is_null($response))
				return $response;
		}
		throw new Exception('Method "'.$method.'" of "'.$this->__class_name__.'" does not exist.');
	}
}

?>
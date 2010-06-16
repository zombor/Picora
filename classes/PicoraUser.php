<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraUser',$path.'/classes/PicoraUser.php');
//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraUserGroup',$path.'/classes/PicoraUser.php');
//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraUserGroupLink',$path.'/classes/PicoraUser.php');
//@@PicoraActiveRecord PicoraActiveRecord::addRelationship('PicoraUser','has_and_belongs_to_many','PicoraUserGroup','user_id','group_id');
//@@PicoraActiveRecord PicoraActiveRecord::addRelationship('PicoraUserGroup','has_and_belongs_to_many','PicoraUser','group_id','user_id');
//@@PicoraEvent PicoraEvent::observe('PicoraDispatcher.beforeDispatch',array('PicoraUser','load'));
//@@SQLite CREATE TABLE users (id INTEGER PRIMARY KEY,name,password,email,created,last_login)
//@@SQLite CREATE TABLE user_groups (id INTEGER PRIMARY KEY,name)
//@@SQLite CREATE TABLE user_groups_links (id INTEGER PRIMARY KEY,user_id,group_id)
//@@SQLite INSERT INTO user_groups (name) VALUES ('admin')
//@@MySQL CREATE TABLE `users` (`id` int(8) NOT NULL auto_increment, `name` CHAR(64), `password` CHAR(32), `email` CHAR(64), `created` DATETIME, `last_login` DATETIME, PRIMARY KEY (`id`))
//@@MySQL CREATE TABLE `user_groups` (`id` int(8) NOT NULL auto_increment, `name` CHAR(64), PRIMARY KEY (`id`))
//@@MySQL CREATE TABLE `user_groups_links` (`id` int(8) NOT NULL auto_increment, `user_id` INT(8), `group_id` INT(8), PRIMARY KEY (`id`))
//@@MySQL INSERT INTO user_groups (`name`) VALUES ('admin')

/**
 * The class PicoraUser is a metaphor for the current user browsing your website. You can call login, logout as class methods. Each instance of PicoraUser is a subclass of PicoraActiveRecord and represents that individual user.
 * 
 * Session, cookie, state and security are all handled behind the scenes.
 * 
 * <pre class="highlighted"><code class="php">print PicoraUser::isLoggedIn();
 * if(PicoraUser::login('username','password'))
 * 	print 'You are logged in as: '.PicoraUser::getName();
 * else
 * 	print 'Login failed';
 *
 * foreach(PicoraActiveRecord::findAll('PicoraUser') as $user)
 * 	print $user->name;
 * </code></pre> 
 * 
 * By default there is a PicoraUserGroup created called 'admin' which controls the is_admin property.
 * 
 * @introduction Simple, secure user authentication.
 * @event bool PicoraUser.beforeLogin(string name,string password,bool set_cookie) If false is returned, login() will terminate and return false.
 * @event void PicoraUser.afterLogin(bool success)
 * @event void PicoraUser.beforeLogout()
 * @event void PicoraUser.afterLogout()
 */
class PicoraUser extends PicoraActiveRecord {
	const TABLE_NAME = 'users';
	const PRIMARY_KEY = 'id';
	const SESSION_KEY = 'PicoraUser';
	const COOKIE_KEY = 'PicoraUser';
	const ALLOW_LOGIN_WITH_EMAIL = true;
	const COOKIE_LIFE = 604800;
	const DELAY_ON_INVALID_LOGIN = true;
	const NAME_KEY = 'name';
	const PASSWORD_KEY = 'password';
	const EMAIL_KEY = 'email';
	const CREATED_KEY = 'created';
	const LAST_LOGIN_KEY = 'last_login';
	
	static protected $is_logged_in = false;
	static protected $is_admin = false;
	static protected $user_id = false;
	static protected $record = false;
	static protected $group_list = array();
	
	static public function load(){
		PicoraEvent::notify('PicoraUser.beforeLoad');
		if(isset($_SESSION[self::SESSION_KEY]) && isset($_SESSION[self::SESSION_KEY][self::PRIMARY_KEY]))
			$user = PicoraActiveRecord::find('PicoraUser',$_SESSION[self::SESSION_KEY][self::PRIMARY_KEY]);
		elseif(isset($_COOKIE[self::COOKIE_KEY]))
			$user = self::challengeCookie($_COOKIE[self::COOKIE_KEY]);
		else
			return;
		if(!$user){
			self::logout();
			return;
		}
		self::setLoggedInStateFromRecord($user);
		PicoraEvent::notify('PicoraUser.afterLoad');
	}
	
	static public function setLoggedInStateFromRecord(PicoraActiveRecord $r){
		self::$record = $r;
		$_SESSION[self::SESSION_KEY] = self::$record->toArray();
		unset($_SESSION[self::SESSION_KEY][self::PASSWORD_KEY]);
		self::$is_logged_in = true;
		foreach($r->getPicoraUserGroupList() as $group)
			self::$group_list[] = $group->name;
		self::$is_admin = self::inGroup('admin');
	}
	
	/**
	 * @return bool
	 */
	static public function isLoggedIn(){
		return self::$is_logged_in;
	}
	
	/**
	 * @return bool
	 */
	static public function isAdmin(){
		return self::$is_admin;
	}
	
	/**
	 * Returns the PicoraActiveRecord object belonging to the current user if logged in, else false.
	 * @return mixed
	 */
	static public function getRecord(){
		return self::$record;
	}
	
	/**
	 * Returns the id belonging to the current user if logged in, else false.
	 * @return mixed 
	 */
	static public function getId(){
		return (self::$record ? self::$record->id : false);
	}
	
	/**
	 * Returns the name belonging to the current user if logged in, else false.
	 * @return mixed 
	 */
	static public function getName(){
		return self::$record->name;
	}
	
	/**
	 * @return array
	 */
	static public function getGroupList(){
		return self::$group_list;
	}
	
	/**
	 * @param string group name
	 * @return bool 
	 */
	static public function inGroup($name){
		if(func_num_args() == 1 && is_string($name))
			return in_array($name,self::$group_list);
		else{
			$args = func_get_args();
			foreach((count($args) == 1 && is_array($args[0]) ? $args[0] : $args) as $name)
				if(self::inGroup($name))
					return true;
			return false;
		}
	}
	
	/**
	 * Immediately (no redirect required) logs the user in.
	 * @param string $name
	 * @param string $password
	 * @param bool $set_cookie
	 * @return bool
	 */
	static public function login($name,$password,$set_cookie = false){
		foreach(PicoraEvent::getObserverList('PicoraUser.beforeLogin') as $callback)
			if(call_user_func($callback,$name,$password,$set_cookie) === false)
				return false;
		self::logout();
		$user = PicoraActiveRecord::find('PicoraUser',array('where'=>(self::ALLOW_LOGIN_WITH_EMAIL
			? self::NAME_KEY.' = "'.PicoraActiveRecord::escape($name).'" OR '.self::EMAIL_KEY.' = "'.PicoraActiveRecord::escape($name).'" AND '.self::PASSWORD_KEY.' = "'.PicoraActiveRecord::escape($password).'"'
			: array(self::NAME_KEY=>$name,self::PASSWORD_KEY=>$password)
		)));
		if(!$user){
			if(self::DELAY_ON_INVALID_LOGIN){
				if(!isset($_SESSION[self::SESSION_KEY.'.invalid_logins']))
					$_SESSION[self::SESSION_KEY.'.invalid_logins'] = 1;
				else
					++$_SESSION[self::SESSION_KEY.'.invalid_logins'];
				sleep(max(0,min($_SESSION[self::SESSION_KEY.'.invalid_logins'],(ini_get('max_execution_time') - 1))));
			}
			PicoraEvent::notify('PicoraUser.afterLogin',false);
			return false;
		}else{
			if(isset($user->last_login))
				$user->updateAttribute(self::LAST_LOGIN_KEY,date('Y-m-d H:i:s',time()));
			if($set_cookie){
				$time = time() + self::COOKIE_LIFE;
				$bool = setcookie(self::COOKIE_KEY,self::bakeUserCookie($time,$user->id,$user->name),$time,'/',null,(isset($_ENV['SERVER_PROTOCOL']) && ((strpos($_ENV['SERVER_PROTOCOL'],'https') || strpos($_ENV['SERVER_PROTOCOL'],'HTTPS')))));
			}
			self::setLoggedInStateFromRecord($user);
			PicoraEvent::notify('PicoraUser.afterLogin',true);
			return true;
		}
	}
	
	/**
	 * Immediately (no redirect required) logs the user out, destroying the cookie if it was set. 
	 * @return void
	 */
	static public function logout(){
		PicoraEvent::notify('PicoraUser.beforeLogout');
		session_unregister(self::SESSION_KEY);
		self::eatCookie();
		self::$record = false;
		self::$user_id = false;
		self::$is_admin = false;
		self::$group_list = array();
		PicoraEvent::notify('PicoraUser.afterLogout');
	}
	
	static protected function challengeCookie($cookie){
		$params = self::explodeCookie($cookie);
		if(isset($params['exp'],$params[self::PRIMARY_KEY],$params['digest'])){
			$user = PicoraActiveRecord::find('PicoraUser',$params['id']);
			if(!$user)
				return false;
			if(self::bakeUserCookie($params['exp'],$params[self::PRIMARY_KEY],$user->name) == $cookie && $params['exp'] > time())
				return $user;
		}
		return false;
	}
	
	static protected function explodeCookie($cookie){
		$pieces = explode('&',$cookie);
		if(count($pieces) < 2)
			return array();
		foreach($pieces as $piece){
			$bits = explode('=',$piece);
			$params[$bits[0]] = $bits[1];
		}
		return $params;
	}
	
	static protected function eatCookie(){
		setcookie(self::COOKIE_KEY,false,time() - 36000,'/',null,(isset($_ENV['SERVER_PROTOCOL']) && (strpos($_ENV['SERVER_PROTOCOL'],'https') || strpos($_ENV['SERVER_PROTOCOL'],'HTTPS'))));
	}

	static protected function bakeUserCookie($time,$id,$name){
		return 'exp='.$time.'&'.self::PRIMARY_KEY.'='.$id.='&digest='.md5(SECRET_KEY.$time.$id.$name);
	}
	
	public function afterCreate(){
		$this->updateAttribute(self::CREATED_KEY,date('Y-m-d H:i:s',time()));
		$this->updateAttribute(self::LAST_LOGIN_KEY,date('Y-m-d H:i:s',time()));
	}
}

/**
 * The PicoraUser package creates a very simple User / Group model. By default each PicoraUserGroup object only contains a name, but has the magic association methods because it has_and_belongs_to_many PicoraUsers. This package assumes you will code in each group's role in your application.
 * 
 * <pre class="highlighted"><code class="php">$admin_group = PicoraActiveRecord::findByField('PicoraUserGroup','name','admin');
 * print $admin_group->getPicoraUserCount();
 * foreach($admin_group->getPicoraUserList() as $user)
 * 	print $user->name;</code></pre>
 * @introduction Represents a group that a given PicoraUser belongs to.
 */
class PicoraUserGroup extends PicoraActiveRecord {
	const PRIMARY_KEY = 'id';
	const TABLE_NAME = 'user_groups';
}

class PicoraUserGroupLink extends PicoraActiveRecord {
	const PRIMARY_KEY = 'id';
	const TABLE_NAME = 'user_groups_links';
}

?>
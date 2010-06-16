<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraSearchQuery',$path.'/classes/PicoraSearchQuery.php');

/**
 * This implementation is specific to [MySQL's fulltext search](http://dev.mysql.com/doc/refman/5.0/en/fulltext-search.html) functionality. It will not work with SQLite at this time. The search query string supports the following constructs:
 * 
 * - single_words
 * - multiple words
 * - "quoted words"
 * - -negative -words
 * - -"negative quoted words"
 * - wildcard*
 * 
 * For example:
 * 
 * - apple
 * - apple -computer
 * - apple "wenatchee valley"
 * - appl* stock -"wenatchee valley"
 * <pre class="highlighted"><code class="php">$search = new PicoraSearchQuery($this->params['query'],'title,body');
 * $articles = PicoraActiveRecord::findAll(array('where'=>$search->display()));
 * if(count($articles) == 0){
 * 	$search->wildCardWords();
 * 	$articles = PicoraActiveRecord::findAll(array('where'=>$search->display()));
 * }</code></pre>
 * 
 * @introduction Parses a search query string into a 'WHERE' SQL fragment.
 */
class PicoraSearchQuery {	
	protected $queryString;
	protected $indexString;
	protected $negativeQuoted = array();
	protected $quoted = array();
	protected $words = array();
	protected $negativeWords = array();
	
	/**
	 * @param string $query_string
	 * @param string $index_string The fulltext index to search against
	 * @return object
	 */
	public function __construct($query_string,$index_string){
		$this->queryString = strtolower($query_string);
		$this->indexString = (is_array($index_string) ? implode(',',$index_string) : $index_string);
		//turn "multi word stirngs" with single quotes into double quotes
		$working_string = preg_replace('/\'([^\']+)\'( ?)/','"\1"\2',$query_string);
		//match quoted strings and remove them from the string
		preg_match_all('/(\-)?"([^"]+)"/',$working_string,$quote_matches);
		foreach($quote_matches[2] as $i => $subject){
			//add the match to an index
			if(!empty($quote_matches[1][$i]))
				$this->{(!empty($quote_matches[1][$i]) ? 'negativeQuoted' : 'quoted')}[] = $quote_matches[2][$i];
			//remove the match from the string
			$string = str_replace($quote_matches[0][$i],'',$working_string);
		}
		//match all remaining subjects
		preg_match_all('/(\-)?([^\s]+)/',$working_string,$matches);
		foreach($matches[2] as $i => $subject)
			$this->{(!empty($matches[1][$i]) ? 'negativeWords' : 'words')}[] = $matches[2][$i];
	}
	
	/**
	 * Appends "*" to the end of each search term.
	 * @return void
	 */
	public function wildCardWords(){
		foreach($this->words as $key => $word)
			if(strpos($word,'*') === false)
				$this->words[$key] .= '*';
	}
	
	/**
	 * Generates a SQL where fragment for use in a full SQL query.
	 * @return string SQL where fragment.
	 */
	public function display(){
		$sql_substr = '';
		foreach($this->words as $word)
			$sql_substr .= addslashes($word).' ';
		foreach($this->quoted as $word)
			$sql_substr .= '"'.addslashes($word).'" ';
		foreach($this->negativeWords as $word)
			$sql_substr .= '-'.addslashes($word).' ';
		foreach($this->negativeQuoted as $word)
			$sql_substr .= '-"'.addslashes($word).'" ';
		return 'MATCH ('.$this->indexString.') AGAINST (\''.substr($sql_substr,0,-1).'\' IN BOOLEAN MODE)';
	}
	
	public function __toString(){
		return $this->display();
	}
}

?>
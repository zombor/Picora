<?php

define('SQLITE_CONNECTION_STRING','sqlite://test.db');
define('MYSQL_CONNECTION_STRING','mysql://root:@localhost/picora_test');

require '../picora.php';
require '../components/PicoraTest.php';
require '../components/PicoraCalendar.php';


session_start();

class TestPicoraAutoLoader extends PicoraTest {}
class TestPicoraDispatcher extends PicoraTest {}
class TestPicoraController extends PicoraTest {}
class TestPicoraView extends PicoraTest {}

//ActiveRecord
class Album extends PicoraActiveRecord {
	public function isValid(){
		if($this->name == '')
			$this->addError('name','cannot be blank');
	}
}
class Artist extends PicoraActiveRecord {}
class Bio extends PicoraActiveRecord {}
class Track extends PicoraActiveRecord {}
class ArtistLink extends PicoraActiveRecord {}

/*
	TO DO
		- test addition of WHERE conditions with :params
		- test that order / where conditions work if defined in the addRelationship method, and in the dynamically created methods
		- test manually specified table names
		- test validations
		- try saving records that come from has_one, has_many, belongs_to relationships to see if they contain to many columns
*/
PicoraActiveRecord::addRelationship('Album','has_many','Track','album_id',array('dependent'=>true));
PicoraActiveRecord::addRelationship('Artist','has_one','Bio','bio_id',array('dependent'=>true));
PicoraActiveRecord::addRelationship('Track','belongs_to','Album','album_id',array('counter'=>'track_count'));
PicoraActiveRecord::addRelationship('Artist','has_and_belongs_to_many','Album','ArtistLink','artist_id','album_id');
PicoraActiveRecord::addRelationship('Album','has_and_belongs_to_many','Artist','ArtistLink','album_id','artist_id');


abstract class TestPicoraActiveRecord extends PicoraTest {
	public function testBasics(){
		$a = PicoraActiveRecord::build('Album');
		$this->assertFalse($a->id);
		
		$a = PicoraActiveRecord::build('Album',array('name'=>'Dark Side of the Moon','artist'=>'Pink Floyd','time'=>'43:23','track_count'=>0));
		$this->assertFalse($a->id);
		$this->assertEqual($a->name,'Dark Side of the Moon');
		$this->assertEqual($a->time,'43:23');
		
		$this->assertTrue($a->save());
		
		$this->assertEqual($a->id,1);
		
		$b = PicoraActiveRecord::create('Album',array('name'=>'Piper at the Gates of Dawn','artist'=>'Pink Floyd','time'=>'42:10','track_count'=>0));
		$this->assertEqual($b->id,2);
		$this->assertEqual($b->name,'Piper at the Gates of Dawn');
		
		$this->assertEqual($a,PicoraActiveRecord::find('Album',1));
		$this->assertEqual($b,PicoraActiveRecord::find('Album',2));
		
		$this->assertEqual($a,PicoraActiveRecord::findByField('Album','name','Dark Side of the Moon'));
		$this->assertEqual($b,PicoraActiveRecord::findByField('Album','time','42:10'));
		
		$c = PicoraActiveRecord::build('Album');
		$c->artist = 'Broken Social Scene';
		$c->name = 'You Forgot it in People';
		$c->time = '64:12';
		$c->track_count = 0;
		$this->assertTrue($c->save());
		
		$this->assertEqual(3,PicoraActiveRecord::count('Album'));
		$this->assertEqual(2,PicoraActiveRecord::count('Album',array('where' => 'artist = "Pink Floyd"')));
		$this->assertEqual(2,count(PicoraActiveRecord::findAllByField('Album','artist','Pink Floyd')));
		$this->assertEqual(1,count(PicoraActiveRecord::findAllByField('Album','artist','Broken Social Scene')));
		
		$c->time = '64:13';
		$this->assertTrue($c->save());
		$this->assertEqual($c,PicoraActiveRecord::find('Album',3));
		
		$this->assertEqual(3,count(PicoraActiveRecord::findAll('Album')));
		$this->assertEqual(2,count(PicoraActiveRecord::findAll('Album',array('where'=>'id IN (1,2)'))));
		
		$result = PicoraActiveRecord::findAll('Album',array('where'=>'id IN (1,2)','order'=>'id ASC'));
		$this->assertEqual(1,$result[0]['id']);
		$this->assertEqual(2,$result[1]['id']);
		
		$result = PicoraActiveRecord::findAll('Album',array('where'=>'id IN (1,2)','order'=>'id DESC'));
		$this->assertEqual(2,$result[0]['id']);
		$this->assertEqual(1,$result[1]['id']);
		
		$result = PicoraActiveRecord::findAll('Album',array('limit'=>2,'order'=>'id ASC'));
		$this->assertEqual(1,$result[0]['id']);
		$this->assertEqual(2,$result[1]['id']);
		
		$result = PicoraActiveRecord::findAll('Album',array('limit'=>2,'offset'=>1,'order'=>'id ASC'));
		$this->assertEqual(2,$result[0]['id']);
		$this->assertEqual(3,$result[1]['id']);
		
		$this->assertEqual(array(),PicoraActiveRecord::findAll('Album',array('where'=>'id = 5')));
		
		$this->assertEqual(3,PicoraActiveRecord::count('Album'));
		PicoraActiveRecord::find('Album',1)->delete();
		$this->assertEqual(2,PicoraActiveRecord::count('Album'));
		PicoraActiveRecord::find('Album',2)->delete();
		$this->assertEqual(1,PicoraActiveRecord::count('Album'));
		PicoraActiveRecord::find('Album',3)->delete();
		$this->assertEqual(0,PicoraActiveRecord::count('Album'));
	}
	
	public function testbelongsToAndHasManyRelationships(){
		$a = PicoraActiveRecord::create('Album',array('name'=>'Dark Side of the Moon','artist'=>'Pink Floyd','time'=>'43:23','track_count'=>0));
		$b = PicoraActiveRecord::create('Album',array('name'=>'Piper at the Gates of Dawn','artist'=>'Pink Floyd','time'=>'42:10','track_count'=>0));
		$a->reload();
		$b->reload();
		$this->assertEqual(array(),$a->getTrackList());
		$this->assertEqual(array(),$b->getTrackList());
		$this->assertEqual(0,$a->getTrackCount());
		$this->assertEqual(0,$b->getTrackCount());
		$this->assertEqual(0,$a->track_count);
		$this->assertEqual(0,$b->track_count);
		
		$a1 = $a->buildTrack(array('name'=>'Track 01','time'=>'4:12'));
		$this->assertEqual($a->id,$a1->album_id);
		$this->assertTrue($a1->save());
		$this->assertTrue($a->reload());
		$this->assertEqual(array($a1),$a->getTrackList());
		$this->assertEqual(array(),$b->getTrackList());
		$this->assertEqual(1,$a->getTrackCount());
		$this->assertEqual(0,$b->getTrackCount());
		$this->assertEqual(1,$a->track_count);
		$this->assertEqual(0,$b->track_count);
		$a2 = $a->createTrack(array('name'=>'Track 02','time'=>'5:03'));
		$a3 = $a->createTrack(array('name'=>'Track 03','time'=>'3:46'));
		$a->reload();
		$this->assertEqual(array($a1,$a2,$a3),$a->getTrackList());
		$this->assertEqual(array($a1,$a3),$a->getTrackList(array('where'=>'tracks.id IN ('.$a1->id.','.$a3->id.')','order'=>'tracks.id ASC')));
		$this->assertEqual(array($a3,$a1),$a->getTrackList(array('where'=>'tracks.id IN ('.$a1->id.','.$a3->id.')','order'=>'tracks.id DESC')));
		$this->assertEqual(3,$a->getTrackCount());
		$this->assertEqual(1,$a->getTrackCount(array('where'=>'tracks.id = '.$a2->id)));
		$this->assertEqual(3,$a->track_count);
		$this->assertEqual(3,count(PicoraActiveRecord::findAll('Track')));
		$this->assertEqual(1,$a->deleteTrack($a3->id));
		$a->reload();
		$this->assertEqual(2,$a->getTrackCount());
		$this->assertEqual(2,$a->track_count);
		$a2->delete();
		$a->reload();
		$this->assertEqual(1,$a->getTrackCount());
		$this->assertEqual(1,$a->track_count);
		$a1->delete();
		$a->reload();
		$this->assertEqual(0,$a->getTrackCount());
		$this->assertEqual(0,$a->track_count);
		$a1 = $a->createTrack(array('name'=>'Track 01','time'=>'4:12'));
		$a2 = $a->createTrack(array('name'=>'Track 02','time'=>'5:03'));
		$a3 = $a->createTrack(array('name'=>'Track 03','time'=>'3:46'));
		$a->reload();
		$this->assertEqual(3,$a->getTrackCount());
		$this->assertEqual(3,$a->track_count);
		$this->assertEqual(3,PicoraActiveRecord::count('Track'));
		$a->delete();
		$this->assertEqual(0,PicoraActiveRecord::count('Track'));
		
		$this->assertFalse(PicoraActiveRecord::find('Track',$a1->id));
		$this->assertFalse($a2->reload());
		
		//test belongsTo
		$b1 = $b->createTrack(array('name'=>'Track 01','time'=>'6:32'));
		$b2 = $b->createTrack(array('name'=>'Track 02','time'=>'6:42'));
		$b->reload();
		$this->assertEqual(2,$b->getTrackCount());
		$this->assertEqual(2,$b->track_count);
		
		$this->assertEqual($b,$b1->getAlbum());
		
		$c1 = PicoraActiveRecord::create('Track',array('name'=>'Track 01','time'=>'5:11','album_id'=>0));
		$d1 = PicoraActiveRecord::create('Track',array('name'=>'Track 01','time'=>'5:11','album_id'=>0));
		$this->assertFalse($c1->getAlbum());
		
		
		$this->assertEqual(0,$c1->album_id);
		$c = $c1->createAlbum(array('name'=>'69 Love Songs','artist'=>'Magnetic Fields','time'=>'43:32'));
		$this->assertEqual($c->id,$c1->album_id);
		$this->assertEqual(1,$c->getTrackCount());
		$this->assertEqual(1,$c->track_count);
		
		$c->createTrack(array('name'=>'Track 02','time'=>'4:11'));
		$c->reload();
		$this->assertEqual(2,$c->getTrackCount());
		$this->assertEqual(2,$c->track_count);
	}
	
	public function testHasOneRelationship(){
		$cure = PicoraActiveRecord::create('Artist',array('name'=>'The Cure','bio_id'=>0));
		$this->assertEqual(0,$cure->bio_id);
		$cureBio = $cure->createBio(array('text'=>'The Bio'));
		$this->assertEqual(1,$cure->bio_id);
		$this->assertEqual($cureBio,$cure->getBio());
		$cure->delete();
		$this->assertFalse(PicoraActiveRecord::find('Bio',$cureBio->id));
		$this->assertFalse($cureBio->reload());
	}
	
	public function testHasAndBelongsToManyRelationship(){
		//test is based on the idea of an album with multiple artists
		$beck = PicoraActiveRecord::create('Artist',array('name'=>'Beck','bio_id'=>0));
		$beckAlbums = $beck->getAlbumList();
		$this->assertEqual(array(),$beckAlbums);
		
		$boards = PicoraActiveRecord::create('Artist',array('name'=>'Boards of Canada','bio_id'=>0));
		$boardsAlbums = $boards->getAlbumList();
		$this->assertEqual(array(),$boardsAlbums);
		
		$guero = PicoraActiveRecord::create('Album',array('name'=>'Guero'));
		$gueroArtists = $guero->getArtistList();
		$this->assertEqual(array(),$gueroArtists);
		
		$guerolito = PicoraActiveRecord::create('Album',array('name'=>'Guerolito','artist'=>'','time'=>'','track_count'=>0));
		$guerolitoArtists = $guerolito->getArtistList();
		$this->assertEqual(array(),$guerolitoArtists);
		
		$music = PicoraActiveRecord::create('Album',array('name'=>'Music Has The Right to Children','artist'=>'','time'=>'','track_count'=>0));
		$camp = PicoraActiveRecord::create('Album',array('name'=>'Campfire Headphase','artist'=>'','time'=>'','track_count'=>0));
		$odelay = PicoraActiveRecord::create('Album',array('name'=>'Odelay','artist'=>'','time'=>'','track_count'=>0));
		$sea = PicoraActiveRecord::create('Album',array('name'=>'Sea Change','artist'=>'','time'=>'','track_count'=>0));
		
		$boards->addAlbum($music);
		$this->assertEqual(array($music),$boards->getAlbumList());
		
		$boards->addAlbum($camp);
		$this->assertEqual(array($music,$camp),$boards->getAlbumList());
		
		$boards->addAlbum($guerolito);
		$this->assertEqual(array($music,$camp,$guerolito),$boards->getAlbumList());
		
		$this->assertEqual(array(),$guero->getArtistList());
		$this->assertEqual(array($boards),$guerolito->getArtistList());
		
		$this->assertEqual(array(),$beck->getAlbumList());
		
		$guerolito->addArtist($beck);
		
		$this->assertEqual(array($guerolito),$beck->getAlbumList());
		$this->assertEqual(array($boards,$beck),$guerolito->getArtistList());
		
		$guerolito->setArtistList(array());
		$this->assertEqual(array(),$guerolito->getArtistList());
		$guerolito->setArtistList(array($boards,$beck));
		
		$this->assertEqual(array($beck,$boards),$guerolito->getArtistList(array('order'=>'name ASC')));
		$this->assertEqual(array($boards,$beck),$guerolito->getArtistList(array('order'=>'name DESC')));
		$guerolito->setArtistList(array());
		$this->assertEqual(array(),$guerolito->getArtistList());
	}
	
	public function testValidations(){
		$invalid = PicoraActiveRecord::create('Album',array('name'=>''));
		$this->assertFalse($invalid->isValid());
		$list = $invalid->getErrorList();
		$this->assertEqual(1,count($list));
	}
}

class TestPicoraActiveRecordSQLite extends TestPicoraActiveRecord {
	public function setup(){
		$this->assertTrue(PicoraActiveRecord::connect(SQLITE_CONNECTION_STRING));
		try{@PicoraActiveRecord::executeQuery('DROP TABLE albums');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE albums (id INTEGER PRIMARY KEY,name,artist,time,track_count)'));
		try{@PicoraActiveRecord::executeQuery('DROP TABLE tracks');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE tracks (id INTEGER PRIMARY KEY,name,time,album_id)'));
		try{@PicoraActiveRecord::executeQuery('DROP TABLE bios');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE bios (id INTEGER PRIMARY KEY,text)'));
		try{@PicoraActiveRecord::executeQuery('DROP TABLE artists');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE artists (id INTEGER PRIMARY KEY,name,bio_id)'));
		try{@PicoraActiveRecord::executeQuery('DROP TABLE artist_links');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE artist_links (id INTEGER PRIMARY KEY,artist_id,album_id)'));
	}
	
	
	public function teardown(){
		$this->assertTrue(PicoraActiveRecord::executeQuery('DROP TABLE albums'));
	}
}

class TestPicoraActiveRecordMySQL extends TestPicoraActiveRecord {
	public function setup(){
		$this->assertTrue(PicoraActiveRecord::connect(MYSQL_CONNECTION_STRING));
		try{@PicoraActiveRecord::executeQuery('DROP TABLE albums');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE albums (id int(11) NOT NULL auto_increment,name varchar(255), artist varchar(255),time varchar(255),track_count int(8), PRIMARY KEY (id))'));	
		try{@PicoraActiveRecord::executeQuery('DROP TABLE tracks');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE tracks (id int(11) NOT NULL auto_increment,name varchar(255),time varchar(255),album_id int(4), PRIMARY KEY (id))'));	
		try{@PicoraActiveRecord::executeQuery('DROP TABLE bios');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE bios (id int(11) NOT NULL auto_increment,text varchar(255), PRIMARY KEY (id))'));	
		try{@PicoraActiveRecord::executeQuery('DROP TABLE artists');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE artists (id int(11) NOT NULL auto_increment,name varchar(255),bio_id int(4), PRIMARY KEY (id))'));	
		try{@PicoraActiveRecord::executeQuery('DROP TABLE artist_links');}catch(Exception $e){}
		$this->assertTrue(PicoraActiveRecord::executeQuery('CREATE TABLE artist_links (id int(11) NOT NULL auto_increment,artist_id int(4), album_id int(4), PRIMARY KEY (id))'));	
	}
	
	public function teardown(){
		$this->assertTrue(PicoraActiveRecord::executeQuery('DROP TABLE albums'));
	}
}
class TestSamplePicoraApplication extends PicoraTest {}

class TestPicoraCalendar extends PicoraTest {
	public function testCalenader(){
		$c = new PicoraCalendar(5,2007);
		$may_2007_output = $c->display();
		$this->assertEqual(5,substr_count($may_2007_output,'<tr class="calendar_week">'));
		$this->assertEqual(1,substr_count($may_2007_output,'<tr class="calendar_header"><td colspan="7">'));
		$this->assertEqual(1,substr_count($may_2007_output,'<tr class="calendar_day_names">'));
		
		$c = new PicoraCalendar(5,2007,array('header'=>false,'day_names'=>false));
		$may_2007_output = $c->display();
		$this->assertEqual(5,substr_count($may_2007_output,'<tr class="calendar_week">'));
		$this->assertEqual(0,substr_count($may_2007_output,'<tr class="calendar_header"><td colspan="7">'));
		$this->assertEqual(0,substr_count($may_2007_output,'<tr class="calendar_day_names">'));
		
		$c = new PicoraCalendar(2,2009,array('header'=>false,'day_names'=>false));
		$feb_2009_output = $c->display();
		$this->assertEqual(4,substr_count($feb_2009_output,'<tr class="calendar_week">'));
		$this->assertEqual(0,substr_count($feb_2009_output,'<tr class="calendar_header"><td colspan="7">'));
		$this->assertEqual(0,substr_count($feb_2009_output,'<tr class="calendar_day_names">'));
	}
}

print PicoraTest::run(array(
	'TestPicoraAutoLoader',
	'TestPicoraDispatcher',
	'TestPicoraController',
	'TestPicoraView',
	'TestPicoraActiveRecordSQLite',
	'TestPicoraActiveRecordMySQL',
	'TestSamplePicoraApplication',
	'TestPicoraCalendar'
));

?>
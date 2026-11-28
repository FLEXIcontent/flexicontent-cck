<?php
namespace Joomla\CMS {class Factory {public static $app,$db;static function getApplication(){return self::$app;}static function getContainer(){return new class{function get($s){return Factory::$db;}};}static function getDate($s='now'){return new \Joomla\CMS\Date\Date($s);}}}
namespace Joomla\CMS\Component {class ComponentHelper {static function getParams($s){return new \Joomla\Registry\Registry;}}}
namespace Joomla\CMS\Language {class Text {static function _($s){return $s;}static function sprintf($s,...$a){return $s;}}}
namespace Joomla\CMS\MVC\Controller {class BaseController {public $input;function setRedirect(...$a){}}}
namespace {
define('_JEXEC',1);define('JDEBUG',false);define('DS','/');define('JPATH_SITE','/scratch/site');define('JPATH_BASE','/src');define('COM_FLEXICONTENT_FILEPATH','/scratch/private');define('COM_FLEXICONTENT_MEDIAPATH','/scratch/public');
require __DIR__ . '/common.php';require '/joomla/libraries/vendor/autoload.php';spl_autoload_register('fixtureAutoload');
function jimport($s){}class JLoader {static function register(...$a){}}
class StopFixtureArchive extends RuntimeException {}
class flexicontent_zip {function __construct(){throw new StopFixtureArchive('Bounded batch stops before archive creation');}}
$user=new class {public $id=31,$guest=false;function getAuthorisedViewLevels(){return [1];}function authorise(...$a){return false;}};
$app=new class($user){function __construct(public $user){}function getIdentity(){return $this->user;}function getSession(){return new class{function has(...$a){return false;}};}function get($s,$d=''){return $s==='tmp_path'?'/scratch':$d;}function enqueueMessage(...$a){}function setHeader(...$a){}function redirect(...$a){}};
\Joomla\CMS\Factory::$app=$app;
$db=new \Joomla\Database\Sqlite\SqliteDriver(['driver'=>'sqlite','database'=>':memory:','prefix'=>'jos_']);\Joomla\CMS\Factory::$db=$db;$db->connect();
$connection=$db->getConnection();$connection->sqliteCreateFunction('CHAR_LENGTH','strlen',1);$connection->sqliteCreateFunction('CONCAT_WS',static fn($sep,...$values)=>implode($sep,$values),-1);$connection->sqliteCreateFunction('CONCAT',static fn(...$values)=>implode('',$values),-1);$connection->sqliteCreateFunction('UTC_TIMESTAMP',static fn()=>gmdate('Y-m-d H:i:s'),0);
foreach([
'CREATE TABLE jos_flexicontent_files(id INT,filename TEXT,filename_original TEXT,altname TEXT,secure INT,url INT,hits INT,stamp INT,size INT,uploaded_by INT,published INT,access INT)',
'CREATE TABLE jos_flexicontent_fields(id INT,attribs TEXT,name TEXT,field_type TEXT,access INT)',
'CREATE TABLE jos_flexicontent_fields_item_relations(field_id INT,item_id INT,value TEXT)',
'CREATE TABLE jos_content(id INT,title TEXT,introtext TEXT,fulltext TEXT,language TEXT,access INT,state INT,created_by INT,alias TEXT,catid INT)',
'CREATE TABLE jos_categories(id INT,alias TEXT,access INT)',
'CREATE TABLE jos_flexicontent_items_ext(item_id INT,type_id INT)',
'CREATE TABLE jos_flexicontent_types(id INT,access INT)',
'CREATE TABLE jos_users(id INT,email TEXT)',
'CREATE TABLE jos_flexicontent_download_history(id INT,file_id INT,user_id INT)',
'CREATE TABLE jos_flexicontent_file_usage(id INT,file_id INT,context TEXT)',
'CREATE TABLE jos_flexicontent_download_coupons(id INT,token TEXT,file_id INT,expire_on TEXT,hits_limit INT,hits INT)',
"INSERT INTO jos_flexicontent_files VALUES(100,'open.txt','open.txt','Open',1,0,0,0,5,99,1,1),(101,'private.txt','private.txt','Private',1,0,0,0,5,99,1,1),(102,'media.txt','media.txt','Media',1,0,0,0,5,99,1,1)",
"INSERT INTO jos_flexicontent_fields VALUES(10,'{}','files','file',1),(11,'{}','media','mediafile',1),(12,'{}','links','weblink',1)",
"INSERT INTO jos_flexicontent_fields_item_relations VALUES(10,1,'100'),(10,2,'101'),(11,1,'102')",
"INSERT INTO jos_content VALUES(1,'Open','','','*',1,1,99,'open',2),(2,'Restricted','','','*',2,1,99,'private',2)",
"INSERT INTO jos_categories VALUES(2,'open',1)",
'INSERT INTO jos_flexicontent_items_ext VALUES(1,1),(2,1)',
'INSERT INTO jos_flexicontent_types VALUES(1,1)',
"INSERT INTO jos_users VALUES(99,'owner@example.invalid')",
"INSERT INTO jos_flexicontent_download_coupons VALUES(1,'".str_repeat('a',64)."',101,'2100-01-01',-1,0)",
] as $sql)$db->setQuery($sql)->execute();
require '/src/site/controller.php';
class DownloadFixture extends FlexicontentController {
    public $reads=[];
    function __construct($input){$this->input=$input;}
    protected function _canViewAssignedItem($id,$access,$user){return (int)$id===1;}
    protected function _getSafeDownloadPath($root,$filename){$this->reads[]=$filename;return false;}
}
function requestDownload($id,$field=10,$task='download',$extra=[]){$test=new DownloadFixture(new \Joomla\Input\Input(array_merge(['task'=>$task,'id'=>$id,'cid'=>1,'fid'=>$field],$extra)));try{$test->download();}catch(StopFixtureArchive $e){}return $test->reads;}
check(requestDownload(100)===['open.txt'],'assigned file reaches bounded path check');
check(requestDownload(102,11)===['media.txt'],'assigned mediafile reaches bounded path check');
check(requestDownload(101)===[],'unrelated restricted file cannot borrow public item/field authority');
check(requestDownload(101,11)===[],'mediafile path requires its exact stored assignment');
check(requestDownload(101,12)===[],'other field kinds retain direct-file assignment policy');
check(requestDownload(999)===[],'missing file is rejected without dereferencing it');
check(requestDownload(101,10,'download_file')===[],'direct-file restricted-assignment control');
check(requestDownload(100,10,'download_file')===['open.txt'],'direct-file public-assignment positive control');
check(requestDownload(100,10,'download_file',['cid'=>0])===['open.txt'],'direct-file links retain global assignment policy without a supplied item');
check(requestDownload('100,101',10,'download_files',['cid'=>0])===['open.txt'],'direct-file batches retain readable members only');
check(requestDownload(101,10,'download',['conid'=>1,'contok'=>str_repeat('a',64)])===['private.txt'],'valid resource-bound coupon still grants its own file');
$denied=false;try{requestDownload(100,10,'download',['conid'=>1,'contok'=>str_repeat('a',64)]);}catch(RuntimeException $e){$denied=$e->getCode()===403;}
check($denied,'coupon cannot grant a different file');
finish();
}

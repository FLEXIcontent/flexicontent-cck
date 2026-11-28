<?php
namespace Joomla\CMS { class Factory { public static $app,$db; static function getApplication(){return self::$app;} static function getUser(){return self::$app->user;} static function getDbo(){return self::$db;} } }
namespace Joomla\CMS\Session { class Session { public static $valid=true; static function checkToken($mode='request'){return self::$valid;} } }
namespace Joomla\CMS\Language { class Text {static function _($s){return $s;} static function sprintf($s,...$a){return $s.':'.implode(',',$a);} } }
namespace Joomla\CMS\Component { class ComponentHelper {static function getParams($s){return new \Joomla\Registry\Registry(['copytask_allow_uneditable'=>0]);}} }
namespace {
define('_JEXEC',1);define('DS','/');define('JPATH_SITE','/scratch/site');define('JPATH_ADMINISTRATOR','/scratch/admin');
require __DIR__ . '/common.php';require '/joomla/libraries/vendor/autoload.php';
spl_autoload_register('fixtureAutoload');
class JLoader {static function register(...$a){}}
class FlexicontentControllerBaseAdmin {
    public $input,$task='batchprocess',$returnURL='index.php';
    function getModel($name){return $GLOBALS['model'];}
    function setRedirect(...$a){} protected function _cleanCache(){}
    function _getRecordsQuery($ids,$cols){return 'SELECT id,created_by,catid FROM #__content WHERE id IN ('.implode(',',$ids).')';}
}
class FLEXIUtilities {static function getlanguageslist(){return [];}}
class flexicontent_db {public static $orders=0;static function setValues_commonDataTypes(...$a){++self::$orders;}}
class DummyUser {
    public $id=31; public $rights=[];
    function authorise($action,$asset){return in_array($action,$this->rights,true) || ($action==='core.edit'&&$asset==='com_content.article.1');}
}
$app=new class {
    public $user,$admin=true,$messages=[];
    function __construct(){$this->user=new DummyUser;}
    function getIdentity(){return $this->user;} function isClient($c){return $c==='administrator'&&$this->admin;}
    function enqueueMessage($m,$t='message'){$this->messages[]=[$m,$t];} function setHeader(...$a){}
};
\Joomla\CMS\Factory::$app=$app;
$db=new \Joomla\Database\Sqlite\SqliteDriver(['driver'=>'sqlite','database'=>':memory:','prefix'=>'jos_']);\Joomla\CMS\Factory::$db=$db;
foreach(['CREATE TABLE jos_content(id INTEGER,created_by INTEGER,catid INTEGER,state INTEGER)','INSERT INTO jos_content VALUES(1,31,2,1),(2,99,2,1)','CREATE TABLE jos_flexicontent_cats_item_relations(catid INTEGER,itemid INTEGER)','CREATE TABLE jos_flexicontent_items_versions(version INTEGER,value TEXT,item_id INTEGER,field_id INTEGER,valueorder INTEGER,suborder INTEGER)','CREATE TABLE jos_flexicontent_tags_item_relations(tid INTEGER,itemid INTEGER)'] as $sql)$db->setQuery($sql)->execute();
// The actual moveitem method runs against dummy SQLite rows and a minimal table adapter.
$method=sourceMethod('/src/admin/models/items.php','moveitem');
eval('use Joomla\\Utilities\\ArrayHelper; class DummyMoveModel { public $_db,$records_jtable="dummy"; function __construct($db){$this->_db=$db;} function getTable(...$a){return new DummyTable($this->_db);} function setError($s){} '.$method.' }');
class DummyTable {
    public $id,$created_by,$catid,$state,$language='*',$type_id=1,$access=1;
    function __construct(private $db){}
    function load($id){foreach($this->db->setQuery('SELECT * FROM jos_content WHERE id='.(int)$id)->loadAssoc() as $k=>$v)$this->$k=$v;}
    function store(){$this->db->setQuery('UPDATE jos_content SET catid='.(int)$this->catid.',state='.(int)$this->state.' WHERE id='.(int)$this->id)->execute();return true;}
}
$GLOBALS['model']=new DummyMoveModel($db);
require '/src/admin/controllers/items.php';
class BatchFixture extends FlexicontentControllerItems {
    function __construct($input){$this->input=$input;}
    protected function _cleanCache(){}
    protected function _batchSetFields(array $cid,array $fields,array $custom){$GLOBALS['field_ids']=$cid;return '';}
}
$app->user->rights=['flexicontent.copyitems'];
$input=new \Joomla\Input\Input(['cid'=>[1,2],'method'=>2,'maincat'=>7,'state'=>'0','seccats'=>[8],'keeptags'=>2]);
(new BatchFixture($input))->batchprocess();
check((int)$db->setQuery('SELECT catid FROM jos_content WHERE id=1')->loadResult()===7,'batch modifies permitted dummy article');
check((int)$db->setQuery('SELECT catid FROM jos_content WHERE id=2')->loadResult()===2,'batch preserves ACL-denied dummy article');
check(($GLOBALS['field_ids']??[])===[1],'batch custom-field step retains authorised subset');
// Non-numeric category text must remain data, never become a SQL expression.
try {$GLOBALS['model']->moveitem(1,7,['8','not-a-category',9],null,'1',0,null,[],2);$ok=true;}catch(\Throwable $e){$ok=false;}
check($ok,'model handles invalid category text without executing it as SQL');
if($ok)check(array_map('intval',$db->setQuery('SELECT catid FROM jos_flexicontent_cats_item_relations WHERE itemid=1 ORDER BY catid')->loadColumn())===[7,8,9],'model stores only positive numeric category ids');
// Load the real Search controller with a dummy index model and no app bootstrap.
mkdir('/scratch/admin/components/com_flexicontent/models',0755,true);
file_put_contents('/scratch/admin/components/com_flexicontent/models/search.php','<?php');
require '/src/admin/controllers/search.php';
class SearchFixture extends FlexicontentControllerSearch {function __construct(){}}
$GLOBALS['model']=new class {public $rows=1;function purge(){$this->rows=0;}};
$search=new SearchFixture;
foreach(['purge','custom_order'] as $action){
    foreach([
        ['denied role','POST',true,[],true,false],
        ['GET','GET',true,['flexicontent.managefields','flexicontent.editfield'],true,false],
        ['missing token','POST',false,['flexicontent.managefields','flexicontent.editfield'],true,false],
        ['frontend','POST',true,['flexicontent.managefields','flexicontent.editfield'],false,false],
        ['permitted edit manager','POST',true,['flexicontent.managefields','flexicontent.editfield'],true,true],
        ['permitted create manager','POST',true,['flexicontent.managefields','flexicontent.createfield'],true,true],
    ] as [$label,$verb,$token,$rights,$admin,$allow]){
        $_SERVER['REQUEST_METHOD']=$verb;\Joomla\CMS\Session\Session::$valid=$token;$app->user->rights=$rights;$app->admin=$admin;
        $GLOBALS['model']->rows=1;flexicontent_db::$orders=0;$denied=false;
        try{$search->$action();}catch(\RuntimeException $e){$denied=true;}
        $changed=$action==='purge'?$GLOBALS['model']->rows===0:flexicontent_db::$orders===1;
        check($allow?(!$denied&&$changed):($denied&&!$changed),'search '.$action.' '.$label);
    }
}
finish();
}

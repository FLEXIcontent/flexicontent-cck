<?php
namespace Joomla\CMS {class Factory {public static $app;static function getApplication(){return self::$app;}}}
namespace Joomla\CMS\Component {class ComponentHelper {static function getParams($s){return new \Joomla\Registry\Registry;}}}
namespace Joomla\CMS\MVC\Controller {
class BaseController {
    public $input;
    function display($cachable=false,$urlparams=[]){
        $app=\Joomla\CMS\Factory::getApplication();foreach($urlparams as $k=>$v)$app->registeredurlparams->$k=$v;
        \CacheFixtureState::$key=\Joomla\CMS\Cache\Cache::makeId();
        if($cachable)\CacheFixtureState::$cache->get(\CacheFixtureState::$view,'display',false,false);
    }
}}
namespace {
define('_JEXEC',1);define('DS','/');define('JPATH_SITE','/scratch/site');define('JPATH_BASE','/src');
require __DIR__ . '/common.php';require '/joomla/libraries/vendor/autoload.php';spl_autoload_register('fixtureAutoload');
function jimport($s){} class JLoader {static function register(...$a){}}
class flexicontent_html {static function getMobileDetector(){return new class{function isMobile(){return false;}function isTablet(){return false;}};}}
class CacheFixtureApp {
    public $registeredurlparams;
    function __construct(public $input,public $user){$this->registeredurlparams=new stdClass;}
    function getIdentity(){return new class($this->user){function __construct(public $id){}function get($k){return $this->$k;}};}
    function getMenu(){return new class{function getActive(){return null;}};}
    function getInput(){return $this->input;}
    function getSession(){return new class{function has(...$a){return false;}function get($k,$d=null,...$a){return $d;}};}
}
class DummyCacheStore {public $values=[];function get($k){return $this->values[$k]??false;}function store($v,$k){$this->values[$k]=$v;return true;}function lock($k){return(object)['locked'=>true,'locklooped'=>false];}function unlock($k){}}
class DummyPrivateView {public $executions=0;function display(){++$this->executions;$id=\Joomla\CMS\Factory::$app->user;echo $id?'DUMMY_MEMBER_'.$id:'ACCESS_DENIED';}}
class CacheFixtureState {public static $cache,$view,$key;}
require '/src/site/controller.php';
class CacheFixtureController extends FlexicontentController {function __construct($input){$this->input=$input;}}
$cache=(new ReflectionClass(\Joomla\CMS\Cache\Controller\ViewController::class))->newInstanceWithoutConstructor();
$cache->cache=new DummyCacheStore;CacheFixtureState::$cache=$cache;CacheFixtureState::$view=new DummyPrivateView;
function renderUser($id,$override=null){
    $query=['option'=>'com_flexicontent','view'=>'item','id'=>'501'];if($override!==null)$query['__fc_user_id__']=$override;
    $_GET=$query;$input=new \Joomla\Input\Input($query);\Joomla\CMS\Factory::$app=new CacheFixtureApp($input,$id);
    ob_start();(new CacheFixtureController($input))->display();$html=ob_get_clean();return ['key'=>CacheFixtureState::$key,'html'=>$html,'executions'=>CacheFixtureState::$view->executions];
}
$member=renderUser(31);$guest=renderUser(0,'31');$normal=renderUser(0);
check($member['html']==='DUMMY_MEMBER_31','member cache positive control');
check($guest['key']!==$member['key'],'guest synthetic identity cannot select member cache key');
check($guest['html']==='ACCESS_DENIED','guest receives no cached member output');
check($normal['html']==='ACCESS_DENIED','ordinary guest control is denied');
check($guest['key']===$normal['key'],'guest cache identity is server-owned zero');
$other=renderUser(99,'31');check($other['html']==='DUMMY_MEMBER_99','member identity overrides supplied cache input');
$before=CacheFixtureState::$view->executions;$again=renderUser(31);
check($again['html']==='DUMMY_MEMBER_31'&&$again['executions']===$before,'same member still reuses its own cached view');
finish();
}

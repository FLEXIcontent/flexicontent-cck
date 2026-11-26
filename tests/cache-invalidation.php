<?php
/** Content mutations must also invalidate dependent progressive module metadata. */
namespace Joomla\CMS {class Factory {public static function getContainer(){return new class {public function get($id){return new \Joomla\CMS\CacheControllerFactoryStub();}};}}class CacheControllerFactoryStub {public function createCacheController($type,$options){return new \CacheRecorder($options['defaultgroup'] ?? '',0);}}}
namespace {
 error_reporting(E_ALL);set_error_handler(static function($s,$m,$f,$l){throw new \ErrorException($m,0,$s,$f,$l);});
 class CacheRecorder {public static array $calls=[];public function __construct(private string $group,private int $client){}public function clean($group=null){self::$calls[]=[$group??$this->group,$this->client];}}
 class FLEXIUtilities {public static function getCache($group='',$client=0){return new CacheRecorder($group,$client);}}
 class CacheInput {public function get(...$args){return 'items.save';}}
 class CacheRootFixture {public $input;public function __construct(){$this->input=new CacheInput;}public function run(){$this->_cleanCache();}}
 function method($file,$signature){$source=file_get_contents(__DIR__.'/../'.$file);$start=strpos($source,$signature);if($start===false)throw new \RuntimeException('Missing cache invalidation method');$method='';$depth=0;$opened=false;foreach(token_get_all('<?php '.substr($source,$start))as $token){if(is_array($token)&&$token[0]===T_OPEN_TAG)continue;$method.=is_array($token)?$token[1]:$token;if($token==='{'){$depth++;$opened=true;}if($token==='}'&&--$depth===0&&$opened)break;}return $method;}
 eval('class CacheBaseFixture extends CacheRootFixture {'.method('admin/controllers/base/baseadmin.php','protected function _cleanCache(').'}');
 $count=0;
 foreach(['Base'=>null,'Items'=>'admin/controllers/items.php','Raw'=>'admin/controllers/items.raw.php','Categories'=>'admin/controllers/categories.php']as $name=>$file){
  $class='Cache'.$name.'Fixture';if($file)eval('class '.$class.' extends CacheBaseFixture {'.method($file,'protected function _cleanCache(').'}');
  CacheRecorder::$calls=[];(new $class)->run();foreach(['com_modules','page']as $dependent)if(!in_array([$dependent,0],CacheRecorder::$calls,true))throw new \RuntimeException($name.' did not invalidate '.$dependent);
  if($file&&$name!=='Categories'&&!in_array(['com_flexicontent_items',0],CacheRecorder::$calls,true))throw new \RuntimeException('Existing item invalidation lost');$count++;
 }
 class CacheModelRoot {public array $calls=[];protected function cleanCache($group=null,$client_id=0){$this->calls[]=[$group,$client_id];}public function run($group=null,$client=0){$this->cleanCache($group,$client);}}
 eval('class CacheModelFixture extends CacheModelRoot {'.method('admin/models/parentclassitem.php','protected function cleanCache(').'}');
 foreach([0,1]as $client){$model=new CacheModelFixture;$model->run(null,$client);foreach(['com_modules','page','com_flexicontent_items','com_flexicontent_filters']as $group)if(!in_array([$group,$client],$model->calls,true))throw new \RuntimeException('Model dependent cache invalidation missing');$count++;}
 $model=new CacheModelFixture;$model->run('custom',0);if($model->calls!==[['custom',0]])throw new \RuntimeException('Explicit group clearing changed');$count++;
 restore_error_handler();echo $count." cache invalidation checks passed\n";
}

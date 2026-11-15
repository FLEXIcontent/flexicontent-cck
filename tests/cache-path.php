<?php
/** Match the active Joomla cache path while retaining configured paths and admin storage. */
namespace Joomla\CMS {
 class Factory {public static $config;public static function getConfig(){return self::$config;}}
}
namespace Joomla\CMS\Cache {
 class Cache {public static function getInstance($handler,$options){return (object)$options;}}
}
namespace {
 error_reporting(E_ALL);set_error_handler(static function($s,$m,$f,$l){throw new \ErrorException($m,0,$s,$f,$l);});
 define('JPATH_SITE','/joomla');define('JPATH_ADMINISTRATOR','/joomla/administrator');define('JPATH_CACHE','/joomla/administrator/cache');
 function jimport($path){}
 $source=file_get_contents(__DIR__.'/../site/classes/helpers/flexiutilities.php');$start=strpos($source,'static function getCache(');if($start===false)throw new \RuntimeException('Cache helper missing');
 $method='';$depth=0;$opened=false;foreach(token_get_all('<?php '.substr($source,$start))as $token){if(is_array($token)&&$token[0]===T_OPEN_TAG)continue;$method.=is_array($token)?$token[1]:$token;if($token==='{'){$depth++;$opened=true;}if($token==='}'&&--$depth===0&&$opened)break;}
 eval('class CachePathFixture {'.$method.'}');
 class CacheConfig {public function __construct(private array $values){}public function get($key,$default=null){return $this->values[$key]??$default;}}
 $cases=[ [[],0,JPATH_CACHE], [[],1,JPATH_ADMINISTRATOR.'/cache'], [['cache_path'=>'/custom/cache'],0,'/custom/cache'], [['cache_path'=>'/custom/cache'],1,JPATH_ADMINISTRATOR.'/cache'], [['cache_path'=>'/custom/cache','cache_handler'=>'redis'],0,'/custom/cache'], [[], '1',JPATH_ADMINISTRATOR.'/cache'] ];
 foreach($cases as [$values,$client,$expected]){\Joomla\CMS\Factory::$config=new CacheConfig($values);$result=CachePathFixture::getCache('com_flexicontent',$client);if($result->cachebase!==$expected||$result->defaultgroup!=='com_flexicontent'||$result->storage!==($values['cache_handler']??'')||$result->caching!==true)throw new \RuntimeException('Cache path/options changed incorrectly');}
 restore_error_handler();echo count($cases)." cache path checks passed\n";
}

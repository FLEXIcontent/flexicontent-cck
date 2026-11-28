<?php
namespace Joomla\CMS {class Factory {public static $user;static function getApplication(){return new class{function getIdentity(){return Factory::$user;}};}}}
namespace {
define('_JEXEC',1);require __DIR__.'/common.php';
foreach(['file','mediafile'] as $name){
    $code=file_get_contents('/src/plugins/flexicontent_fields/'.$name.'/'.$name.'.php');
    preg_match_all('/if \(!Factory::getApplication\(\)->getIdentity\(\)->authorise\(\x27flexicontent.editfileproperties\x27, \x27com_flexicontent\x27\)\)\s*\{[^}]+\}/',$code,$blocks);
    for($i=0;$i<2;$i++){
        $block=$blocks[0][$i]??'';
        $apply=eval('use Joomla\\CMS\\Factory; return function(){$iform_lang=$iform_access=$iform_stamp=1;'.$block.'return [$iform_lang,$iform_access,$iform_stamp];};');
        foreach([false,true] as $allow){
            \Joomla\CMS\Factory::$user=new class($allow){function __construct(public $allow){}function authorise($action,$asset){return $this->allow;}};
            check($apply()===($allow?[1,1,1]:[0,0,0]),$name.' '.($i?'save':'form').' advanced properties '.($allow?'permitted':'denied'));
        }
    }
}
finish();
}

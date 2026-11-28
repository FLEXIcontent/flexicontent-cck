<?php
define('_JEXEC',1);require __DIR__ . '/common.php';
$acl=simplexml_load_file('/src/admin/access.xml');
$actions=$acl->xpath('//section[@name="component"]/action[@name="flexicontent.editfileproperties"]');
check(count($actions)===1,'advanced file permission is configurable in component ACL');
$language=file_get_contents('/src/admin/language/en-GB/en-GB.com_flexicontent.ini');
foreach(['FLEXI_PERMISSIONS_COMPONENT_FILES_EDIT_PROPERTIES','FLEXI_PERMISSIONS_COMPONENT_FILES_EDIT_PROPERTIES_DESC'] as $key)check((bool)preg_match('/^'.preg_quote($key,'/').'=".+"/m',$language),'advanced file permission label '.$key);
$source=file_get_contents('/src/admin/controllers/filemanager.php');
$start=strpos($source,"if (!\$user->authorise('flexicontent.editfileproperties'");
$policy='';if($start!==false){$end=strpos($source,"\n\t\t}",$start);$policy=substr($source,$start,$end+4-$start);}
eval('function applyPropertyPolicy($data,$user,$record,$isnew){'.$policy.'return $data;}');
$record=(object)['language'=>'en-GB','access'=>1,'stamp'=>1,'hits'=>0];
foreach([false,true] as $advanced){
    $user=new class($advanced){function __construct(public $allow){}function authorise(...$a){return $this->allow;}};
    $data=['language'=>'zh-TW','access'=>2,'stamp'=>0,'hits'=>37,'altname'=>'Updated title'];
    $out=applyPropertyPolicy($data,$user,$record,false);
    foreach(['language','access','stamp','hits'] as $k)check($out[$k]===($advanced?$data[$k]:$record->$k),'advanced property '.$k.' '.($advanced?'manager allowed':'ordinary editor preserved'));
    check($out['altname']==='Updated title','ordinary title edit retained');
    $out=applyPropertyPolicy($data,$user,$record,true);
    if(!$advanced)check($out['language']==='*'&&$out['access']===1&&$out['stamp']===1&&$out['hits']===0,'new ordinary file gets safe advanced defaults');
}
$start=strpos($source,'$fileName_tmp = $app->getUserState');$end=strpos($source,'$app->setUserState($fileName, $fileName_tmp);',$start);
$chunk=substr($source,$start,$end-$start);eval('function chunkBasename($app,$fileName){'.$chunk.'return $fileName_tmp;}');
$resume='2026_11_12_abcdef0123456';
foreach(['en-GB','../dummy','',17,['dummy'],null,$resume] as $state){
    $app=new class($state){function __construct(public $state){}function getUserState($name,$default){return $this->state??$default;}};
    $value=chunkBasename($app,'filemanager.filter_language');
    check(is_string($value)&&preg_match('/^\d{4}_\d{2}_\d{2}_[0-9a-f]{13}$/D',$value),'chunk uses a generated basename for '.get_debug_type($state));
    if($state===$resume)check($value===$resume,'valid chunk resume name preserved');
}
finish();

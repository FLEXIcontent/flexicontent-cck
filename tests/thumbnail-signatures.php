<?php
/** Check URL compatibility against the bundled phpThumb signing function. */
define('_JEXEC', 1);
error_reporting(E_ALL);
set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
require __DIR__.'/../site/classes/helpers/images.php';
$source=file_get_contents(__DIR__.'/../site/librairies/phpthumb/phpThumb.config.php');
$start=strpos($source,'function phpThumbURL(');
if($start===false)throw new RuntimeException('Bundled signer missing');
$method='';$depth=0;$opened=false;
foreach(token_get_all('<?php '.substr($source,$start))as $token){
    if(is_array($token)&&$token[0]===T_OPEN_TAG)continue;
    $method.=is_array($token)?$token[1]:$token;
    if($token==='{'){$depth++;$opened=true;}
    if($token==='}'&&--$depth===0&&$opened)break;
}
eval($method);
$property=new ReflectionProperty(flexicontent_images::class,'phpthumb_config');
$key=str_repeat('a1B!',16);
$GLOBALS['PHPTHUMB_CONFIG']=['high_security_password'=>$key,'high_security_enabled'=>true,'high_security_url_separator'=>'&'];
$property->setValue(null,$GLOBALS['PHPTHUMB_CONFIG']);
$cases=[
    ['src'=>'/images/test.png','w'=>16],
    ['src'=>'/images/space and 中文.png','w'=>0,'h'=>16],
    ['src'=>'/images/test.png','fltr'=>['blur|2','crop|1'],'q'=>95],
    ['src'=>'/images/a&b.png','f'=>'png','zc'=>1],
];
$count=0;
foreach($cases as $params){
    if(phpThumbURL($params)!=='phpThumb.php?'.flexicontent_images::phpThumbQuery($params))throw new RuntimeException('Bundled signer query mismatch');
    $count++;
}
$url='/components/com_flexicontent/librairies/phpthumb/phpThumb.php?'.flexicontent_images::phpThumbQuery($cases[0]);
$html='<img src="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">';
if(flexicontent_images::phpThumbSignHtml($html)!==$html)throw new RuntimeException('Current signature was not idempotent');
$count++;
$newKey=str_repeat('z9Q$',16);
$property->setValue(null,['high_security_password'=>$newKey,'high_security_enabled'=>true,'high_security_url_separator'=>'&']);
$rotated=flexicontent_images::phpThumbSignHtml($html);
if($rotated===$html||substr_count($rotated,'hash=')!==1)throw new RuntimeException('Old signature was not replaced once');
$decoded=html_entity_decode($rotated,ENT_QUOTES,'UTF-8');
if(!preg_match('/\?([^"]+)"/',$decoded,$m)||!str_ends_with($m[1],'&hash='.hash_hmac('sha256',explode('&hash=',$m[1])[0],$newKey)))throw new RuntimeException('Rotated signature is invalid');
$count++;
$filtered=flexicontent_images::phpThumbQuery(['src'=>'/images/test.png','w'=>0,'h'=>null,'f'=>'','q'=>false]);
if(!str_contains($filtered,'w=0')||str_contains($filtered,'h=')||str_contains($filtered,'f=')||str_contains($filtered,'q='))throw new RuntimeException('Empty/zero query handling changed');
$count++;
foreach(['','weak']as $weak){
    $property->setValue(null,['high_security_password'=>$weak,'high_security_enabled'=>false,'high_security_url_separator'=>'&']);
    if(str_contains(flexicontent_images::phpThumbQuery($cases[0]),'hash='))throw new RuntimeException('Weak key used for signing');
    $count++;
}
restore_error_handler();
echo $count." thumbnail signature compatibility checks passed\n";

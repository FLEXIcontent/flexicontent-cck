<?php
/** Check URL compatibility against the bundled phpThumb signing function. */
define('_JEXEC', 1);
define('JPATH_SITE', dirname(__DIR__));
eval('namespace Joomla\CMS\Uri; final class Uri { public static function root($pathOnly = false) { return $pathOnly ? "" : "https://fixture.invalid/"; } }');
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
// Hand written URLs: a page URL that only contains a thumbnail URL, a parameter outside plain resizing, a remote or
// protected source and a filter that reads a file are never signed. A query without a source is not signed either.
$endpoint='/components/com_flexicontent/librairies/phpthumb/phpThumb.php';
foreach([
    '<a href="/index.php?x=phpThumb.php?src=/a.png&amp;md5s=0&amp;pop=1">',
    '<a href="/index.php'.$endpoint.'?src=/images/test.png&amp;w=16">',
    '<a href="/blog'.$endpoint.'?src=/images/test.png&amp;w=16&amp;pop=1&amp;tmpl=component&amp;print=1">',
    '<img src="'.$endpoint.'?src=/images/test.png&amp;md5s=0">',
    '<img src="'.$endpoint.'?src=/images/test.png&amp;new=FFFFFF">',
    '<img src="'.$endpoint.'?src=/images/test.png&amp;down=a.html">',
    '<img src="'.$endpoint.'?src=/images/test.png&amp;phpThumbDebug=9">',
    '<img src="'.$endpoint.'?src=/images/test.png&amp;nocache=1">',
    '<img src="'.$endpoint.'?src=http://127.0.0.1:8080/a.png&amp;w=16">',
    '<img src="'.$endpoint.'?src=//fileserver/share/a.png">',
    '<img src="'.$endpoint.'?src=/images/../../outside/a.png">',
    '<img src="'.$endpoint.'?src=/configuration.php">',
    '<img src="'.$endpoint.'?src=/components/com_flexicontent/uploads/a.png&amp;w=16">',
    '<img src="'.$endpoint.'?src=/images/item_7_field_3/original/a.png&amp;w=16">',
    '<img src="'.$endpoint.'?src=/images/test.png&amp;fltr[]=over|/components/com_flexicontent/uploads/a.png">',
    '<img src="'.$endpoint.'?w=16&amp;h=16">',
]as $unsigned){
    if(flexicontent_images::phpThumbSignHtml($unsigned)!==$unsigned)throw new RuntimeException('Signed a URL that must stay unsigned: '.$unsigned);
    $count++;
}
foreach(['<a href="'.$endpoint.'?src=/images/test.png&amp;w=16&amp;fltr[]=gray">','<img src="components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=/images/test.png&amp;w=16">']as $legacy){
    if(substr_count(flexicontent_images::phpThumbSignHtml($legacy),'hash=')!==1)throw new RuntimeException('Plain hand written URL was not signed: '.$legacy);
    $count++;
}
foreach([[],['w'=>16,'h'=>16],['src'=>'','w'=>16],['src'=>null]]as $params){
    if(str_contains(flexicontent_images::phpThumbQuery($params),'hash='))throw new RuntimeException('Query without a source was signed');
    $count++;
}
$filtered=flexicontent_images::phpThumbQuery(['src'=>'/images/test.png','w'=>0,'h'=>null,'f'=>'','q'=>false]);
if(!str_contains($filtered,'w=0')||preg_match('/(?:^|&)(?:h|f|q)=/',$filtered))throw new RuntimeException('Empty/zero query handling changed');
$count++;
foreach(['','weak']as $weak){
    $property->setValue(null,['high_security_password'=>$weak,'high_security_enabled'=>false,'high_security_url_separator'=>'&']);
    if(str_contains(flexicontent_images::phpThumbQuery($cases[0]),'hash='))throw new RuntimeException('Weak key used for signing');
    $count++;
}
restore_error_handler();
echo $count." thumbnail signature compatibility checks passed\n";

<?php
define('_JEXEC',1);
define('JPATH_SITE',dirname(__DIR__));
eval('namespace Joomla\CMS\Uri; final class Uri { public static function root($pathOnly = false) { return $pathOnly ? "" : "https://fixture.invalid/"; } }');
error_reporting(E_ALL);
set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
require __DIR__.'/../site/classes/helpers/images.php';
$prop=new ReflectionProperty(flexicontent_images::class,'phpthumb_config');
$prop->setValue(null,['high_security_password'=>str_repeat('a1B!',16),'high_security_enabled'=>true,'high_security_url_separator'=>'&']);
$url='/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=%2Fimages%2Fdummy.png&w=16';
$checks=[];
foreach([
 ['image','<img src="'.$url.'">',true],
 ['style','<style>.a{background:url("'.$url.'")}</style>',true],
 ['script HTML string','<script>const markup = \'<img src="'.$url.'">\';</script>',false],
 ['script plain string','<script>const text = \' src="'.$url.'"\';</script>',false],
 ['text node','<p>Example src="'.$url.'"</p>',false],
 ['textarea','<textarea><img src="'.$url.'"></textarea>',false],
 ['comment','<!-- <img src="'.$url.'"> -->',false],
 ['script unclosed','<script>const markup = \'<img src="'.$url.'">\';',false],
 ['textarea unclosed','<textarea><img src="'.$url.'">',false],
 ['script quoted angle','<script title=">">const markup = \'<img src="'.$url.'">\';</script>',false],
 ['image quoted angle','<img title=">" src="'.$url.'">',true],
 ['escaped text','<p>&lt;img src="'.$url.'"&gt;</p>',false],
 ['srcset','<img srcset="'.$url.' 1x, '.$url.' 2x">',true],
 ['style content','<style>.a::after{content:\' src="'.$url.'"\'}</style>',false],
]as[$name,$html,$expected]){
 $out=flexicontent_images::phpThumbSignHtml($html);$changed=$out!==$html;
 $checks[]=['name'=>$name,'status'=>$changed===$expected?'pass':'fail','changed'=>$changed,'expectedChanged'=>$expected];
}
foreach($checks as $check)if($check['status']!=='pass')throw new RuntimeException('HTML boundary changed: '.$check['name']);
echo count($checks)." thumbnail HTML boundary checks passed\n";

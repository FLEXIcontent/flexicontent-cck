<?php
namespace Joomla\CMS\Language {class Text {static function _($s){return $s;}}}
namespace Joomla\CMS\Uri {class Uri {static function root(){return 'https://fixture.invalid/';}}}
namespace {
define('_JEXEC',1);define('DS','/');define('JPATH_SITE','/scratch/site');define('JPATH_ADMINISTRATOR','/src/administrator');
require __DIR__ . '/common.php';require '/joomla/libraries/vendor/autoload.php';spl_autoload_register('fixtureAutoload');
class JLoader {static function register(...$a){}}
class FCField {public static $isItemsManager=false;public $values=[];}
class FLEXIUtilities {static function paramToArray($s,$pattern,...$a){return preg_split($pattern,$s,-1,PREG_SPLIT_NO_EMPTY);}}
class FlexicontentFields {static function indexFieldsByIds(...$a){return [];}}
require '/src/site/classes/helpers/html.php';
require '/src/site/classes/helpers/security.php';
require '/src/plugins/flexicontent_fields/addressint/addressint.php';
plgFlexicontent_fieldsAddressint::$field_types=['addressint'];
class AddressFixture extends plgFlexicontent_fieldsAddressint {
    function __construct(){}
    function render($value){
        $field=(object)['id'=>10,'name'=>'address','parameters'=>new \Joomla\Registry\Registry(['show_address'=>'item','show_map'=>'','use_custom_marker'=>0]),'display'=>[]];
        $item=(object)['id'=>1];$this->values=[$value];$view='item';$prop='display';$is_ingroup=false;
        require '/src/plugins/flexicontent_fields/addressint/tmpl/value.php';return $field->display[0]??'';
    }
}
$plugin=new AddressFixture;$rows=[];
foreach(['https://maps.example.invalid/?q=one&z=2','/maps?q=one&z=2','javascript:void(0)','data:text/plain,fixture','mailto:dummy@example.invalid'] as $url){
    $field=(object)['field_type'=>'addressint','parameters'=>new \Joomla\Registry\Registry(['use_country'=>0])];$item=(object)['fields'=>[]];$files=[];$post=[['addr_display'=>'Fixture Address','url'=>$url]];
    $plugin->onBeforeSaveField($field,$post,$files,$item);$saved=unserialize($post[0],['allowed_classes'=>false]);
    $rows[]=['source'=>$url,'saved'=>$saved['url'],'html'=>$plugin->render($saved)];
}
$legacy=['addr_display'=>'Legacy address','url'=>'https://maps.example.invalid/" data-audit-marker="address" value="dummy'];
$rows[]=['source'=>'legacy-quoted','saved'=>$legacy['url'],'html'=>$plugin->render($legacy)];
class FileEditorFixture {
    public $lists=['language'=>'<select name="language"></select>','access'=>'<select name="access"></select>','stamp'=>'<select name="stamp"></select>'];
    function __construct(public $row){}
    function render(){
        $code=file_get_contents('/src/admin/views/file/tmpl/default.php');
        if(preg_match('/\$fc_file_escape = static function .*?\};/s',$code,$helper))eval($helper[0]);
        preg_match('/<input type="text" id="altname".*?\/>/s',$code,$input);
        preg_match('/<textarea name="description".*?<\/textarea>/s',$code,$textarea);
        ob_start();eval('?>'.$input[0].$textarea[0]);return ob_get_clean();
    }
    function advanced($allowed){
        $code=file_get_contents('/src/admin/views/file/tmpl/default.php');
        if(preg_match('/\$fc_file_escape = static function .*?\};/s',$code,$helper))eval($helper[0]);
        $fc_can_edit_file_properties=$allowed;
        $start=strpos($code,'<?php if ($fc_can_edit_file_properties) : ?>');
        if($start===false){$button=strpos($code,'value="Advanced properties"');$start=strrpos(substr($code,0,$button),'<tr>');}
        $end=strpos($code,"\t</table>",$start);$fragment=substr($code,$start,$end-$start);
        ob_start();eval('?>'.$fragment);return ob_get_clean();
    }
}
$title='Quoted " data-audit-marker="file-title" value="dummy';$description='Text </textarea><span data-audit-marker="description">dummy</span>';
$fileView=new FileEditorFixture((object)['altname'=>$title,'description'=>$description,'hits'=>0,'ext'=>'txt']);
$editor=$fileView->render();$ordinary=$fileView->advanced(false);$manager=$fileView->advanced(true);
$output=['addresses'=>$rows,'editor'=>$editor,'title'=>$title,'description'=>$description,'advancedOrdinary'=>$ordinary,'advancedManager'=>$manager];
if(($argv[1]??'')==='json'){echo json_encode($output,JSON_UNESCAPED_SLASHES);exit;}
foreach($rows as $i=>$r){
    $dom=new \DOMDocument;@$dom->loadHTML($r['html']);$anchor=$dom->getElementsByTagName('a')->item(0);$href=$anchor?$anchor->getAttribute('href'):'';
    check($anchor!==null,'directions anchor rendered '.$i);
    if($i<2)check($href===$r['source'],'allowed directions URL preserved '.$i);
    else check(str_starts_with($href,'https://maps.google.com/maps?q='),'invalid/legacy directions URL uses safe fallback '.$i);
    check($anchor&&$anchor->getAttribute('rel')==='noopener noreferrer','directions link isolates new tab '.$i);
    if($i>=2&&$i<=4)check($r['saved']==='','save rejects unsupported directions scheme '.$i);
}
$dom=new \DOMDocument;@$dom->loadHTML($editor);$xpath=new \DOMXPath($dom);
check($xpath->query('//*[@data-audit-marker]')->length===0,'file editor quote text creates no attributes or elements');
check($xpath->query('//*[@id="altname"]')->item(0)->getAttribute('value')===$title,'stored title round-trips intact');
check($xpath->query('//*[@id="file-desc"]')->item(0)->textContent===$description,'stored description round-trips as literal text');
check(!str_contains($ordinary,'name="hits"'),'ordinary file editor omits advanced controls');
check(str_contains($manager,'name="hits"')&&str_contains($manager,'name="access"'),'permitted file manager retains advanced controls');
finish();
}

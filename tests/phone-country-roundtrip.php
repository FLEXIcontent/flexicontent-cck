<?php
/** Exercise the actual country option/selection blocks and save method without a database. */
namespace Joomla\CMS {
    class Factory { public static function getApplication() { return (object)['input'=>new class { public function get($k,$d='',$t='') {return $d;} }]; } }
}
namespace Joomla\CMS\Language {
    class Text { public static function _($key) {return $key;} }
    class LanguageHelper { public static function parseIniFile($file) {
        return parse_ini_file(__DIR__.'/../plugins/flexicontent_fields/phonenumbers/en-GB.plg_flexicontent_fields_phonenumbers.ini');
    } }
}
namespace Joomla\CMS\HTML {
    class HTMLHelper {
        public static array $last=[];
        public static function _($method,$options,$name,$attributes,$key,$text,$selected,$id) {
            self::$last=compact('options','name','attributes','selected','id');
            $out='<select name="'.$name.'" '.$attributes.'>';
            foreach($options as $option) if(is_object($option)) $out.='<option value="'.$option->value.'"'.((string)$option->value===(string)$selected?' selected':'').'>'.$option->text.'</option>';
            return $out.'</select>';
        }
    }
}
namespace {
    error_reporting(E_ALL);
    set_error_handler(static function($severity,$message,$file,$line){throw new \ErrorException($message,0,$severity,$file,$line);});
    define('_JEXEC',1);
    $fixture=sys_get_temp_dir().'/fc-phone-'.bin2hex(random_bytes(6));
    mkdir($fixture.'/language/en-GB',0777,true);
    $languageFile=$fixture.'/language/en-GB/en-GB.plg_flexicontent_fields_phonenumbers.ini';
    copy(__DIR__.'/../plugins/flexicontent_fields/phonenumbers/en-GB.plg_flexicontent_fields_phonenumbers.ini',$languageFile);
    define('JPATH_ADMINISTRATOR',$fixture);
    register_shutdown_function(static function() use($languageFile,$fixture) {
        unlink($languageFile);rmdir($fixture.'/language/en-GB');rmdir($fixture.'/language');rmdir($fixture);
    });
    class FCField { public function unserialize_array($value,...$args) {return unserialize($value,['allowed_classes'=>false]);} }
    class FLEXIUtilities { public static function paramToArray($value,$regex,...$args) {return preg_split($regex,$value);} }
    class flexicontent_html { public static function dataFilter($value,...$args) {return strip_tags((string)$value);} }
    class Params { public function __construct(private array $data=[]) {} public function get($key,$default=null) {return $this->data[$key]??$default;} }
    $file=__DIR__.'/../plugins/flexicontent_fields/phonenumbers/phonenumbers.php';
    require $file;
    $plugin=(new \ReflectionClass('plgFlexicontent_fieldsPhonenumbers'))->newInstanceWithoutConstructor();
    $plugin::$field_types=['phonenumbers'];
    $source=file_get_contents($file);
    $start=strpos($source,'$ac_country_default ');
    $end=strpos($source,'// CSS classes of value container',$start);
    $optionsBlock=substr($source,$start,$end-$start);
    $start=strpos($source,'$selected_cc =');
    $end=strpos($source,'$phone =',$start);
    if($start===false||$end===false)throw new \RuntimeException('Country selection block missing');
    $selectionBlock=substr($source,$start,$end-$start);
    $render=\Closure::bind(static function($field,$value) use($optionsBlock,$selectionBlock) {
        $fieldname_n='custom[phone][0]';$elementid_n='custom_phone_0';$use_cc=1;
        eval($optionsBlock);eval($selectionBlock);
        return $phonecc;
    },null,'plgFlexicontent_fieldsPhonenumbers');
    $check=static function($condition,$message){if(!$condition)throw new \RuntimeException($message);};
    $field=(object)['field_type'=>'phonenumbers','parameters'=>new Params()];$upload=[];$item=(object)[];
    foreach(['1','+1','886','+886','+1 441','1441','+61 89164'] as $cc) {
        $post=[['label'=>'Sample','cc'=>$cc,'phone1'=>'202','phone2'=>'555','phone3'=>'0142']];
        $plugin->onBeforeSaveField($field,$post,$upload,$item);
        $expected=str_replace(['+',' '],'',$cc);
        $check($post[0]['cc']===$expected,'Country code changed during save: '.$cc);
        $html=$render($field,$post[0]);
        $check(str_contains($html,'value="'.$expected.'" selected'),'Saved country not selected on reopen: '.$cc);
        $legacy=$render($field,['cc'=>$cc]);
        $check(str_contains($legacy,'value="'.$expected.'" selected'),'Legacy country not selected: '.$cc);
    }
    $field->parameters=new Params(['ac_country_default'=>'+886','ac_country_allowed_list'=>'+886']);
    $html=$render($field,[]);
    $check(str_contains($html,'disabled="disabled"'),'Single-country selector should remain locked');
    $check(str_contains($html,'type="hidden" name="custom[phone][0][cc]" value="886"'),'Disabled country lost from form submission');
    $check(str_contains($html,'value="886" selected'),'Configured default not selected');
    foreach(['javascript:1','1" onclick="alert(1)','+1-441',"1\n441",'++1'] as $cc) {
        $post=[['label'=>'Sample','cc'=>$cc,'phone1'=>'202','phone2'=>'555','phone3'=>'0142']];
        $plugin->onBeforeSaveField($field,$post,$upload,$item);
        $check($post[0]['cc']==='','Malformed country code accepted');
    }
    echo "PASS: country choices survive save/reopen, legacy and spaced codes, defaults, locked selection and invalid input\n";
}

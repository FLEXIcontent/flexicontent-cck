<?php
/** Batch request scalars and omitted fields must preserve valid language/state data. */
error_reporting(E_ALL);
set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
$source=file_get_contents(__DIR__.'/../admin/models/items.php');
$start=strpos($source,'protected function normaliseBatchLanguages(');
if($start===false)throw new RuntimeException('Batch language normalizer missing');
$method='';$depth=0;$opened=false;
foreach(token_get_all('<?php '.substr($source,$start))as $token){
    if(is_array($token)&&$token[0]===T_OPEN_TAG)continue;
    $method.=is_array($token)?$token[1]:$token;
    if($token==='{'){$depth++;$opened=true;}
    if($token==='}'&&--$depth===0&&$opened)break;
}
eval('class BatchValueFixture {public function languages($v){return $this->normaliseBatchLanguages($v);}'.$method.'}');
$fixture=new BatchValueFixture;$count=0;
foreach([[null,[]],['',[]],['_not_posted_',[]],['*',['*']],['fr-FR',['fr-FR']],[['en-GB','fr-FR'],['en-GB','fr-FR']],[[],[]]]as[$value,$expected]){
    if($fixture->languages($value)!==$expected)throw new RuntimeException('Batch language targets changed');$count++;
}
$move=substr($source,strpos($source,'function moveitem('));
foreach(['language','state']as $property){
    if(!preg_match('/\$item->'.$property.'\s*=\s*[^;]+;/',$move,$match))throw new RuntimeException('Batch assignment missing');
    $assignment=$match[0];
    $cases=$property==='language'?[[null,'en-GB'],['','en-GB'],['_not_posted_','en-GB'],['*','*'],['fr-FR','fr-FR']]:[[null,1],['',1],[false,1],[0,0],['0','0'],[-2,-2],[1,1]];
    foreach($cases as[$value,$expected]){
        $item=(object)['language'=>'en-GB','state'=>1];$lang=$state=$value;eval($assignment);
        if($item->$property!==$expected)throw new RuntimeException('Omitted batch '.$property.' changed stored data');$count++;
    }
}
$copy=substr($source,strpos($source,'function copyitems('),strpos($source,'function moveitem(')-strpos($source,'function copyitems('));
if(!preg_match('/\$row->state\s*=\s*[^;]+;/',$copy,$match))throw new RuntimeException('Batch copy state assignment missing');
foreach([[null,1],['',1],[false,1],[0,0],['0','0'],[-2,-2],[1,1]]as[$state,$expected]){
    $row=(object)['state'=>1];eval($match[0]);
    if($row->state!==$expected)throw new RuntimeException('Omitted copy state changed stored data');$count++;
}
restore_error_handler();echo $count." batch value checks passed\n";

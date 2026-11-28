<?php
define('_JEXEC',1);require __DIR__.'/common.php';
$source='/src/site/tasks/core.php';$code=file_get_contents($source);
if(str_contains($code,'function hasSameOrigin')){
    eval('class OriginFixture {'.sourceMethod($source,'hasSameOrigin').'}');
    $guard=new OriginFixture;
    $origin=static fn($server,$proxy=false)=>$guard->hasSameOrigin($server,$proxy);
}else{
    if(!preg_match('/\$http_host =[^\r\n]*\R\s*\$referer\s*=[^\r\n]*\R\s*\$is_valid_origin =[^\r\n]*/',$code,$matches))throw new RuntimeException('Original origin block missing');
    $block=$matches[0];
    eval('function oldOriginFixture($server){$_SERVER=$server;'.$block.'return $is_valid_origin;}');
    $origin=static fn($server,$proxy=false)=>oldOriginFixture($server);
}
foreach([
 ['http://fixture.invalid:8080/path','fixture.invalid:8080','http',true],
 ['https://fixture.invalid/path','fixture.invalid','https',true],
 ['https://FIXTURE.invalid:443/path','fixture.invalid','https',true],
 ['http://fixture.invalid/path','fixture.invalid:80','http',true],
 ['http://[::1]:8080/path','[::1]:8080','http',true],
 ['http://fixture.invalid:8081/path','fixture.invalid:8080','http',false],
 ['https://fixture.invalid/path','fixture.invalid','http',false],
 ['http://other.invalid:8080/path','fixture.invalid:8080','http',false],
 ['', 'fixture.invalid','http',false],
 ['http://user@fixture.invalid/path','fixture.invalid','http',false],
 ['http://fixture.invalid/path','fixture.invalid/path','http',false],
 ['http://fixture.invalid/path','fixture.invalid@other.invalid','http',false],
 ["http://fixture.invalid/path\n",'fixture.invalid','http',false],
 ['//fixture.invalid/path','fixture.invalid','http',false],
 ['http://fixture.invalid:0/path','fixture.invalid:0','http',false],
 ['http://fixture.invalid/\\path','fixture.invalid','http',false],
] as $i=>[$referer,$host,$scheme,$expected]){
    check($origin(['HTTP_REFERER'=>$referer,'HTTP_HOST'=>$host,'HTTPS'=>$scheme==='https'?'on':'off'])===$expected,'complete origin '.$i);
}
$server=['HTTP_REFERER'=>'https://fixture.invalid/path','HTTP_HOST'=>'fixture.invalid','HTTPS'=>'off','HTTP_X_FORWARDED_PROTO'=>'https'];
check($origin($server,true),'configured proxy may supply HTTPS scheme');
check(!$origin($server,false),'untrusted forwarded scheme is ignored');
$server['HTTP_X_FORWARDED_PROTO']='https,http';check(!$origin($server,true),'proxy scheme lists are rejected');
$server['HTTP_X_FORWARDED_PROTO']='invalid';check(!$origin($server,true),'invalid proxy schemes are rejected');
finish();

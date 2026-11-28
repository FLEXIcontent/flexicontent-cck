<?php
require __DIR__ . '/common.php';
$list=__DIR__.'/lint-targets.json';
$files=is_file($list)?json_decode(file_get_contents($list),true):[
    'admin/controllers/filemanager.php','admin/controllers/items.php','admin/controllers/search.php','admin/models/items.php',
    'admin/views/file/tmpl/default.php','admin/views/filemanager/tmpl/default.php',
    'plugins/flexicontent_fields/addressint/addressint.php','plugins/flexicontent_fields/addressint/tmpl/value.php',
    'plugins/flexicontent_fields/file/file.php','plugins/flexicontent_fields/mediafile/mediafile.php',
    'site/controller.php','site/controllers/filemanager.php','site/tasks/core.php',
    'tests/filemanager-frontend-guard.php',
];
if(!is_file($list))foreach(glob(__DIR__.'/*.php') as $file)$files[]='tests/security-boundaries/'.basename($file);
foreach($files as $relative){
    $pipes=[];$p=proc_open([PHP_BINARY,'-l','/src/'.$relative],[1=>['pipe','w'],2=>['pipe','w']],$pipes,'/scratch',['PATH'=>'/usr/local/bin:/usr/bin:/bin','HOME'=>'/scratch','TMPDIR'=>'/scratch','LANG'=>'C.UTF-8','TZ'=>'UTC']);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);
    check($status===0,'PHP syntax '.$relative);if($status!==0)echo $out.$err;
}
finish();

<?php
// Run only inside the read-only, network-none container described in README.md.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$baseline=in_array('--baseline',$argv,true);$results=[];$bad=0;
foreach(['admin','download','cache','properties','render','origin','inline','frontend'] as $test){
    $pipes=[];
    $process=proc_open([PHP_BINARY,__DIR__.'/'.$test.'.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,'/scratch',['PATH'=>'/usr/local/bin:/usr/bin:/bin','HOME'=>'/scratch','TMPDIR'=>'/scratch','LANG'=>'C.UTF-8','TZ'=>'UTC']);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $valid=preg_match('/CHECKS=(\d+) FAILURES=(\d+)/',$out,$summary)&&$exit===($baseline?1:0)&&($baseline?(int)$summary[2]>0:(int)$summary[2]===0);
    $results[]=['test'=>$test,'exit'=>$exit,'expected_result'=>$valid,'checks'=>isset($summary[1])?(int)$summary[1]:0];
    echo ($valid?'PASS ':'FAIL ').$test."\n";
    if(!$valid){++$bad;echo $out.$err;}
}
echo json_encode(['baseline'=>$baseline,'results'=>$results,'failed_suites'=>$bad],JSON_PRETTY_PRINT)."\n";
exit($bad?1:0);

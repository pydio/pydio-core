<?php

$summarizeLines = file($argv[1]);
$srcDir = $argv[2];
$patchDir = $argv[3];
$toDelete = array();

@mkdir ($patchDir);
@mkdir ($patchDir."/UPGRADE");
foreach($summarizeLines as $line){
    $line = trim($line);
    $letter = substr($line, 0, 1);
    $end = trim(substr($line, 1));
    if($letter == "D"){
        $toDelete[] = $end;
        continue;
    }
    if(is_dir($end)){
        if(!is_dir($patchDir."/".$end)) mkdir($patchDir."/".$end, 777, true);
    }else{
        if(!is_dir($patchDir."/".dirname($end))) mkdir($patchDir."/".dirname($end), 777, true);
        copy($srcDir."/".$end, $patchDir."/".$end);
    }
}
if(count($toDelete)){
    file_put_contents($patchDir."/UPGRADE/CLEAN-FILES", implode("\r\n", str_replace("\\", "/", $toDelete)));
}
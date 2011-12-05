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
    if(is_dir($srcDir."/".$end)){
        if(!is_dir($patchDir."/".$end)) mkdir($patchDir."/".$end, 777, true);
    }else{
        if(!is_dir($patchDir."/".dirname($end))) mkdir($patchDir."/".dirname($end), 777, true);
        echo("\n-- Copy ".$srcDir."/".$end ." to ".$patchDir."/".$end);
        copy($srcDir."/".$end, $patchDir."/".$end);
    }
}
if(count($toDelete)){
    file_put_contents($patchDir."/UPGRADE/CLEAN-FILES", implode("\r\n", str_replace("\\", "/", $toDelete)));
}

function copy_r( $path, $dest )
{
    if( is_dir($path) )
    {
        @mkdir( $dest );
        $objects = scandir($path);
        if( sizeof($objects) > 0 )
        {
            foreach( $objects as $file )
            {
                if( $file == "." || $file == ".." )
                    continue;
                // go on
                if( is_dir( $path.DIRECTORY_SEPARATOR.$file ) )
                {
                    self::copy_r( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                }
                else
                {
                    copy( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                }
            }
        }
        return true;
    }
    elseif( is_file($path) )
    {
        return copy($path, $dest);
    }
    else
    {
        return false;
    }
}

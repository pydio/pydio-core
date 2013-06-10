<?php

$folder = "/var/www/trace";
$command = "clamscan --remove=yes FILE";


    while (true) {
		$list = scandir($folder);
		foreach ($list as $element) {
			if ($element != "." && $element != ".." && $element != ""){
				$file = file_get_contents($folder . DIRECTORY_SEPARATOR . $element);
				$command2 = str_replace("FILE", $file , $command);
				passthru($command2);
				unlink($folder . DIRECTORY_SEPARATOR . $element);
				}
			}
		sleep(0.21);
    }
    

?>

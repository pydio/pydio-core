<?php

class VideoReader extends AJXP_Plugin {
	
	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		
		$streamData = $repository->streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "read_video_data"){
			AJXP_Logger::debug("REading video");
			session_write_close();
			$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
 			$filesize = filesize($destStreamURL.$file);
 			
 			$fp = fopen($destStreamURL.$file, "r");
 			if(preg_match("/\.ogv$/", $file)){
				header("Content-Type: video/ogg; name=\"".basename($file)."\"");
 			}else if(preg_match("/\.mp4$/", $file)){
 				header("Content-Type: video/mp4; name=\"".basename($file)."\"");
 			}else if(preg_match("/\.webm$/", $file)){
 				header("Content-Type: video/webm; name=\"".basename($file)."\"");
 			}

			header("Content-Length: ".$filesize);
			header('Cache-Control: public');
			
			$class = $streamData["classname"];
			$stream = fopen("php://output", "a");
			call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL.$file, $stream);
			fflush($stream);
			fclose($stream);
			//exit(1);
		}else if($action == "get_sess_id"){
			HTMLWriter::charsetHeader("text/plain");
			print(session_id());
		}
	}	
	
}

?>
<?php

class AJXP_Exception {
	var $messageId;
	function AJXP_Exception($messageId = -1){
		$this->messageId = $messageId;
	}
	
	function errorToXml($mixed)
	{
		if(is_a($mixed, "AJXP_Exception"))
		{			
			$messages = ConfService::getMessages();
			$error = "Unkown Error";			
			if(isSet($mixed->messageId) && array_key_exists($mixed->messageId, $messages))
			{
				$error = $messages[$mixed->messageId];
			}else{
				$error = $mixed->messageId;
			}

			AJXP_XMLWriter::header();
			AJXP_XMLWriter::sendMessage(null, $error);
			AJXP_XMLWriter::close();
			exit(1);
		}
	}
}

?>

<?php
/** A useful class for session switching */
defined('AJXP_EXEC') or die( 'Access not allowed');

class SessionSwitcher
{
    /** The current session stack */
    public static $sessionArray;
    
    /** Construction. This kills the current session if any started, and restart the given session */
    public function __construct($name, $killPreviousSession = false, $loadPreviousSession = false, $saveHandlerType = "files", $saveHandlerData = null)
    {   
    	AJXP_Logger::debug("Switching to session ".$name); 	
        if (session_id() == "")
        {
			if(isSet($saveHandlerData)){
				session_set_save_handler(
					$saveHandlerData["open"], 
					$saveHandlerData["close"], 
					$saveHandlerData["read"], 
					$saveHandlerData["write"], 
					$saveHandlerData["destroy"], 
					$saveHandlerData["gc"]					
				);
			}else{
				ini_set('session.save_handler', $saveHandlerType);
			}
            // Start a default session and save on the handler
            session_start();
            SessionSwitcher::$sessionArray[] = array('id'=>session_id(), 'name'=>session_name());
            session_write_close();
        }else{
        	SessionSwitcher::$sessionArray[] = array('id'=>session_id(), 'name'=>session_name());
        }
        // Please note that there is no start here, session might be already started
        if (session_id() != "")
        {
            // There was a previous session
            if ($killPreviousSession)
            {
                if (isset($_COOKIE[session_name()]))
				setcookie(session_name(), '', time() - 42000, '/');
                session_destroy();
            }
            AJXP_Logger::debug("Closing previous session ".session_name()." / ".session_id());
            session_write_close();
            session_regenerate_id(false);
            $_SESSION = array();
        }

		if(isSet($saveHandlerData)){
			session_set_save_handler(
				$saveHandlerData["open"], 
				$saveHandlerData["close"], 
				$saveHandlerData["read"], 
				$saveHandlerData["write"], 
				$saveHandlerData["destroy"], 
				$saveHandlerData["gc"]					
			);
		}else{
			ini_set('session.save_handler', $saveHandlerType);
		}

        if($loadPreviousSession){
	        AJXP_Logger::debug("Restoring previous session".SessionSwitcher::$sessionArray[0]['id']);
			session_id(SessionSwitcher::$sessionArray[0]['id']);
        }else{
        	$newId = md5(SessionSwitcher::$sessionArray[0]['id'].$name);        
        	session_id($newId);
        }
        session_name($name);
        session_start();
        AJXP_Logger::debug("Restarted session ".session_name()." / ".session_id(), $_SESSION);
    }
};
?>
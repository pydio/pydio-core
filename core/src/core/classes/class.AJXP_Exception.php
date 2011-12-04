<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * @package info.ajaxplorer.core
 */
/**
 * Custom exception (legacy from php4 when there were no exceptions)
 */
class AJXP_Exception extends Exception {
	
	function AJXP_Exception($messageString, $messageId = false){
		if($messageId !== false && class_exists("ConfService")){
			$messages = ConfService::getMessages();
			if(array_key_exists($messageId, $messages)){
				$messageString = $messages[$messageId];
			}else{
				$messageString = $messageId;
			}
		}
		parent::__construct($messageString);
	}
		
	function errorToXml($mixed)
	{
		if(is_a($mixed, "Exception")){
			throw $this;
		}else{
			throw new AJXP_Exception($mixed);
		}
	}
}

?>

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
require_once('../classes/class.AbstractTest.php');

/**
 * @package info.ajaxplorer.test
 * Detect HTTPS protocol
 */
class SSLEncryption extends AbstractTest
{
    function SSLEncryption() { parent::AbstractTest("SSL Encryption", "You are not using SSL encryption, or it was not detected by the server. Be aware that it is strongly recommended to secure all communication of data over the network."); }
    function doTest() 
    { 
        // Get the locale
        $ssl = false;
		if(isSet($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on"){
			$ssl = true;
		}        
        if (!$ssl) { 
        	$this->failedLevel = "warning"; 
        	return FALSE; 
        }else{
        	$this->failedInfo .= "Https protocol detected"; 
        	return TRUE;
        }
    }
};

?>
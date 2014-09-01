<?php
/*
 * Copyright © 2003-2010, The ESUP-Portail consortium & the JA-SIG Collaborative.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *		 * Redistributions of source code must retain the above copyright notice,
 *			 this list of conditions and the following disclaimer.
 *		 * Redistributions in binary form must reproduce the above copyright notice,
 *			 this list of conditions and the following disclaimer in the documentation
 *			 and/or other materials provided with the distribution.
 *		 * Neither the name of the ESUP-Portail consortium & the JA-SIG
 *			 Collaborative nor the names of its contributors may be used to endorse or
 *			 promote products derived from this software without specific prior
 *			 written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once(dirname(__FILE__).'/Abstract.php');

/**
 * This class is used to make proxied service requests via the HTTP GET method.
 *
 * Usage Example:
 *		
 *			try {
 * 				$service = phpCAS::getProxiedService(PHPCAS_PROXIED_SERVICE_HTTP_GET);
 * 				$service->setUrl('http://www.example.com/path/');
 * 				$service->send();
 *				if ($service->getResponseStatusCode() == 200)
 *					return $service->getResponseBody();
 *				else
 *					// The service responded with an error code 404, 500, etc.
 *					throw new Exception('The service responded with an error.');
 *				
 *			} catch (CAS_ProxyTicketException $e) {
 *				if ($e->getCode() == PHPCAS_SERVICE_PT_FAILURE)
 *					return "Your login has timed out. You need to log in again.";
 *				else
 *					// Other proxy ticket errors are from bad request format (shouldn't happen)
 *					// or CAS server failure (unlikely) so lets just stop if we hit those.
 *					throw $e; 
 *			} catch (CAS_ProxiedService_Exception $e) {
 *				// Something prevented the service request from being sent or received.
 *				// We didn't even get a valid error response (404, 500, etc), so this
 *				// might be caused by a network error or a DNS resolution failure.
 *				// We could handle it in some way, but for now we will just stop.
 *				throw $e;
 *			}
 *
 */
class CAS_ProxiedService_Http_Get
	extends CAS_ProxiedService_Http_Abstract
{
	
	/**
	 * Add any other parts of the request needed by concrete classes
	 * 
	 * @param CAS_RequestInterface $request
	 * @return void
	 */
	protected function populateRequest (CAS_RequestInterface $request) {
		// do nothing, since the URL has already been sent and that is our
		// only data.
	}

	
}

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
include_once(dirname(__FILE__).'/../Exception.php');
include_once(dirname(__FILE__).'/../../InvalidArgumentException.php');
include_once(dirname(__FILE__).'/../../OutOfSequenceException.php');

/**
 * This class is used to make proxied service requests via the HTTP POST method.
 *
 * Usage Example:
 *		
 *			try {
 * 				$service = phpCAS::getProxiedService(PHPCAS_PROXIED_SERVICE_HTTP_POST);
 * 				$service->setUrl('http://www.example.com/path/');
 *				$service->setContentType('text/xml');
 *				$service->setBody(''<?xml version="1.0"?'.'><methodCall><methodName>example.search</methodName></methodCall>');
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
 */
class CAS_ProxiedService_Http_Post
	extends CAS_ProxiedService_Http_Abstract
{

	/**
	 * The content-type of this request
	 * 
	 * @var string $_contentType
	 */
	private $_contentType;
	
	/**
	 * The body of the this request
	 * 
	 * @var string $_body
	 */
	private $_body;
	
	/**
	 * Set the content type of this POST request.
	 * 
	 * @param string $contentType
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after the Request has been sent.
	 */
	public function setContentType ($contentType) {
		if ($this->hasBeenSent())
			throw new CAS_OutOfSequenceException('Cannot set the content type, request already sent.');
		
		$this->_contentType = $contentType;
	}

	/**
	 * Set the body of this POST request.
	 * 
	 * @param string $body
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after the Request has been sent.
	 */
	public function setBody ($body) {
		if ($this->hasBeenSent())
			throw new CAS_OutOfSequenceException('Cannot set the body, request already sent.');
		
		$this->_body = $body;
	}
	
	/**
	 * Add any other parts of the request needed by concrete classes
	 * 
	 * @param CAS_RequestInterface $request
	 * @return void
	 */
	protected function populateRequest (CAS_RequestInterface $request) {
		if (empty($this->_contentType) && !empty($this->_body))
			throw new CAS_ProxiedService_Exception("If you pass a POST body, you must specify a content type via ".get_class($this).'->setContentType($contentType).');
		
		$request->makePost();
		if (!empty($this->_body)) {
			$request->addHeader('Content-Type: '.$this->_contentType);
			$request->addHeader('Content-Length: '.strlen($this->_body));
			$request->setPostBody($this->_body);
		}
	}

	
}

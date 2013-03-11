<?php
/*
 * Copyright � 2003-2010, The ESUP-Portail consortium & the JA-SIG Collaborative.
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

require_once(dirname(__FILE__).'/../ProxiedService.php');
require_once(dirname(__FILE__).'/Testable.php');
include_once(dirname(__FILE__).'/../InvalidArgumentException.php');
include_once(dirname(__FILE__).'/../OutOfSequenceException.php');


/**
 * This class implements common methods for ProxiedService implementations included
 * with phpCAS.
 */
abstract class CAS_ProxiedService_Abstract
	implements CAS_ProxiedService, CAS_ProxiedService_Testable
{
	
	/**
	 * The proxy ticket that can be used when making service requests.
	 * @var string $_proxyTicket; 
	 */
	private $_proxyTicket;
	
	/**
	 * Register a proxy ticket with the Proxy that it can use when making requests.
	 * 
	 * @param string $proxyTicket
	 * @return void
	 * @throws InvalidArgumentException If the $proxyTicket is invalid.
	 * @throws CAS_OutOfSequenceException If called after a proxy ticket has already been initialized/set.
	 */
	public function setProxyTicket ($proxyTicket) {
		if (empty($proxyTicket))
			throw new CAS_InvalidArgumentException("Trying to initialize with an empty proxy ticket.");
		if (!empty($this->_proxyTicket))
			throw new CAS_OutOfSequenceException('Already initialized, cannot change the proxy ticket.');
		
		$this->_proxyTicket = $proxyTicket;
	}
	
	/**
	 * Answer the proxy ticket to be used when making requests.
	 * 
	 * @return string
	 * @throws CAS_OutOfSequenceException If called before a proxy ticket has already been initialized/set.
	 */
	protected function getProxyTicket () {
		if (empty($this->_proxyTicket))
			throw new CAS_OutOfSequenceException('No proxy ticket yet. Call $this->initializeProxyTicket() to aquire the proxy ticket.');
		
		return $this->_proxyTicket;
	}
	
	/**
	 * @var CAS_Client $_casClient; 
	 */
	private $_casClient;
	
	/**
	 * Use a particular CAS_Client->initializeProxiedService() rather than the 
	 * static phpCAS::initializeProxiedService().
	 *
	 * This method should not be called in standard operation, but is needed for unit
	 * testing.
	 * 
	 * @param CAS_Client $casClient
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after a proxy ticket has already been initialized/set.
	 */
	public function setCasClient (CAS_Client $casClient) {
		if (!empty($this->_proxyTicket))
			throw new CAS_OutOfSequenceException('Already initialized, cannot change the CAS_Client.');
		
		$this->_casClient = $casClient;
	}
	
	/**
	 * Fetch our proxy ticket.
	 *
	 * Descendent classes should call this method once their service URL is available
	 * to initialize their proxy ticket.
	 *
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after a proxy ticket has already been initialized.
	 */
	protected function initializeProxyTicket() {
		if (!empty($this->_proxyTicket))
			throw new CAS_OutOfSequenceException('Already initialized, cannot initialize again.');
		
		// Allow usage of a particular CAS_Client for unit testing.
		if (empty($this->_casClient))
			phpCAS::initializeProxiedService($this);
		else
			$this->_casClient->initializeProxiedService($this);
	}
	
}

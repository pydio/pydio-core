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
include_once(dirname(__FILE__).'/../InvalidArgumentException.php');
include_once(dirname(__FILE__).'/../OutOfSequenceException.php');

/**
 * Provides access to a proxy-authenticated IMAP stream
 */
class CAS_ProxiedService_Imap
	extends CAS_ProxiedService_Abstract
{
	
	/**
	 * The username to send via imap_open.
	 *
	 * @var string $_username;
	 */
	private $_username;
	
	/**
	 * Constructor.
	 * 
	 * @param string $username
	 * @return void
	 */
	public function __construct ($username) {
		if (!is_string($username) || !strlen($username))
			throw new CAS_InvalidArgumentException('Invalid username.');
		
		$this->_username = $username;
	}
	
	/**
	 * The target service url.
	 * @var string $_url;
	 */
	private $_url;
	
	/**
	 * Answer a service identifier (URL) for whom we should fetch a proxy ticket.
	 * 
	 * @return string
	 * @throws Exception If no service url is available.
	 */
	public function getServiceUrl () {
		if (empty($this->_url))
			throw new CAS_ProxiedService_Exception('No URL set via '.get_class($this).'->getServiceUrl($url).');
		
		return $this->_url;
	}
	
	/*********************************************************
	 * Configure the Stream
	 *********************************************************/

	/**
	 * Set the URL of the service to pass to CAS for proxy-ticket retrieval.
	 *
	 * @param string $url
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after the stream has been opened.
	 */
	public function setServiceUrl ($url) {
		if ($this->hasBeenOpened())
			throw new CAS_OutOfSequenceException('Cannot set the URL, stream already opened.');
		if (!is_string($url) || !strlen($url))
			throw new CAS_InvalidArgumentException('Invalid url.');
		
		$this->_url = $url;
	}
	
	/**
	 * The mailbox to open. See the $mailbox parameter of imap_open().
	 * 
	 * @var string $_mailbox
	 */
	private $_mailbox;
	
	/**
	 * Set the mailbox to open. See the $mailbox parameter of imap_open().
	 * 
	 * @param string $mailbox
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after the stream has been opened.
	 */
	public function setMailbox ($mailbox) {
		if ($this->hasBeenOpened())
			throw new CAS_OutOfSequenceException('Cannot set the mailbox, stream already opened.');
		if (!is_string($mailbox) || !strlen($mailbox))
			throw new CAS_InvalidArgumentException('Invalid mailbox.');
		
		$this->_mailbox = $mailbox;
	}
	
	/**
	 * A bit mask of options to pass to imap_open() as the $options parameter.
	 * 
	 * @var int $_options
	 */
	private $_options = NULL;
	
	/**
	 * Set the options for opening the stream. See the $options parameter of imap_open().
	 * 
	 * @param int $options
	 * @return void
	 * @throws CAS_OutOfSequenceException If called after the stream has been opened.
	 */
	public function setOptions ($options) {
		if ($this->hasBeenOpened())
			throw new CAS_OutOfSequenceException('Cannot set options, stream already opened.');
		if (!is_int($options))
			throw new CAS_InvalidArgumentException('Invalid options.');
		
		$this->_options = $options;
	}
	
	/*********************************************************
	 * 2. Open the stream
	 *********************************************************/

	/**
	 * Open the IMAP stream (similar to imap_open()).
	 *
	 * @return resource Returns an IMAP stream on success
	 * @throws CAS_OutOfSequenceException If called multiple times.
	 * @throws CAS_ProxyTicketException If there is a proxy-ticket failure.
	 *		The code of the Exception will be one of: 
	 *			PHPCAS_SERVICE_PT_NO_SERVER_RESPONSE 
	 *			PHPCAS_SERVICE_PT_BAD_SERVER_RESPONSE
	 *			PHPCAS_SERVICE_PT_FAILURE
	 * @throws CAS_ProxiedService_Exception If there is a failure sending the request to the target service.	 */
	public function open () {
		if ($this->hasBeenOpened())
			throw new CAS_OutOfSequenceException('Stream already opened.');
		if (empty($this->_mailbox))
			throw new CAS_ProxiedService_Exception('You must specify a mailbox via '.get_class($this).'->setMailbox($mailbox)');
		
		phpCAS::traceBegin();
		
		// Get our proxy ticket and append it to our URL.
		$this->initializeProxyTicket();
		phpCAS::trace('opening IMAP mailbox `'.$this->_mailbox.'\'...');
		$this->_stream = @imap_open($this->_mailbox, $this->_username, $this->getProxyTicket(), $this->_options);
		if ($this->_stream) {
			phpCAS::trace('ok');
		} else {
			phpCAS::trace('could not open mailbox');
			// @todo add localization integration.
// 			$this->_errorMessage = sprintf($this->getString(CAS_STR_SERVICE_UNAVAILABLE), $url, var_export(imap_errors(),TRUE));
			$message = 'IMAP Error: '.$url.' '. var_export(imap_errors(),TRUE);
			phpCAS::trace($message);
			throw new CAS_ProxiedService_Exception($message);
		}
				
		phpCAS::traceEnd();
		return $this->_stream;
	}
	
	/**
	 * Answer true if our request has been sent yet.
	 * 
	 * @return boolean
	 */
	protected function hasBeenOpened () {
		return !empty($this->_stream);
	}

	/*********************************************************
	 * 3. Access the result
	 *********************************************************/
	/**
	 * The IMAP stream
	 * 
	 * @var resource $_stream
	 */
	private $_stream;
	
	/**
	 * Answer the IMAP stream
	 * 
	 * @return resource
	 */
	public function getStream () {
		if (!$this->hasBeenOpened())
			throw new CAS_OutOfSequenceException('Cannot access stream, not opened yet.');
		
		return $this->_stream;
	}
	
	/**
	 * CAS_Client::serviceMail() needs to return the proxy ticket for some reason,
	 * so this method provides access to it.
	 * 
	 * @return string 
	 * @throws CAS_OutOfSequenceException If called before the stream has been opened.
	 */
	public function getImapProxyTicket () {
		if (!$this->hasBeenOpened())
			throw new CAS_OutOfSequenceException('Cannot access errors, stream not opened yet.');
		
		return $this->getProxyTicket();
	}
}

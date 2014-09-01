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

/**
 * This interface defines methods that allow proxy-authenticated service handlers
 * to be tested in unit tests.
 *
 * Classes implementing this interface SHOULD store the CAS_Client passed and initialize
 * themselves with that client rather than via the static phpCAS method. For example:
 *
 *		/ **
 *		 * Fetch our proxy ticket.
 *		 * /
 *		protected function initializeProxyTicket() {
 *			// Allow usage of a particular CAS_Client for unit testing.
 *			if (is_null($this->casClient))
 *				phpCAS::initializeProxiedService($this);
 *			else
 *				$this->casClient->initializeProxiedService($this);
 *		}
 * 
 */
interface CAS_ProxiedService_Testable {
		
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
	public function setCasClient (CAS_Client $casClient);
	
}

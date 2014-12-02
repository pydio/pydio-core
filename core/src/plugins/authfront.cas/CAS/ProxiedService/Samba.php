<?php
/**
 * Created by PhpStorm.
 * User: c12simple
 * Date: 05/07/14
 * Time: 10:15
 */

require_once(dirname(__FILE__) . '/Abstract.php');
include_once(dirname(__FILE__) . '/../Exception.php');
include_once(dirname(__FILE__) . '/../InvalidArgumentException.php');
include_once(dirname(__FILE__) . '/../OutOfSequenceException.php');

/**
 * Provides access to a proxy-authenticated CIFS
 */
class CAS_ProxiedService_Samba
    extends CAS_ProxiedService_Abstract{

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
            throw new CAS_ProxiedService_Exception('No URL set via '.get_class($this).'->setUrl($url).');

        return $this->_url;
    }

    /*********************************************************
     * Configure the Request
     *********************************************************/

    /**
     * Set the URL of the Request
     *
     * @param string $url
     * @return void
     * @throws CAS_OutOfSequenceException If called after the Request has been sent.
     */
    public function setServiceUrl ($url) {
        if ($this->hasGot())
            throw new CAS_OutOfSequenceException('Cannot set the URL, request already sent.');
        if (!is_string($url))
            throw new CAS_InvalidArgumentException('$url must be a string.');

        $this->_url = $url;
    }

    private $count = 0;

    public function getSambaProxyTicket(){
        phpCAS::traceBegin();
        if ($this->hasGot())
            throw new CAS_OutOfSequenceException('Cannot set the URL, request already sent.');

        $this->count = 1;
        $this->initializeProxyTicket();

        phpCAS::traceEnd();
        return $this->getProxyTicket();
    }

    public function hasGot(){
        return ($this->count > 0);
    }

} 
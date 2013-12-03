<?php


/*
 * Copyright © 2003-2010, The ESUP-Portail consortium & the JA-SIG Collaborative.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *     * Neither the name of the ESUP-Portail consortium & the JA-SIG
 *       Collaborative nor the names of its contributors may be used to endorse or
 *       promote products derived from this software without specific prior
 *       written permission.

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

//
// hack by Vangelis Haniotakis to handle the absence of $_SERVER['REQUEST_URI'] in IIS
//
if (php_sapi_name() != 'cli') {
    if (!isset($_SERVER['REQUEST_URI'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
    }
}

// Add a E_USER_DEPRECATED for php versions <= 5.2
if (!defined('E_USER_DEPRECATED')) {
    define('E_USER_DEPRECATED', E_USER_NOTICE);
}

/**
 * @file CAS/CAS.php
 * Interface class of the phpCAS library
 *
 * @ingroup public
 */

// ########################################################################
//  CONSTANTS
// ########################################################################

// ------------------------------------------------------------------------
//  CAS VERSIONS
// ------------------------------------------------------------------------

/**
 * phpCAS version. accessible for the user by phpCAS::getVersion().
 */
define('PHPCAS_VERSION', '${phpcas.version}');

// ------------------------------------------------------------------------
//  CAS VERSIONS
// ------------------------------------------------------------------------
/**
 * @addtogroup public
 * @{
 */

/**
 * CAS version 1.0
 */
define("CAS_VERSION_1_0", '1.0');
/*!
 * CAS version 2.0
 */
define("CAS_VERSION_2_0", '2.0');

// ------------------------------------------------------------------------
//  SAML defines
// ------------------------------------------------------------------------

/**
 * SAML protocol
 */
define("SAML_VERSION_1_1", 'S1');

/**
 * XML header for SAML POST
 */
define("SAML_XML_HEADER", '<?xml version="1.0" encoding="UTF-8"?>');

/**
 * SOAP envelope for SAML POST
 */
define("SAML_SOAP_ENV", '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Header/>');

/**
 * SOAP body for SAML POST
 */
define("SAML_SOAP_BODY", '<SOAP-ENV:Body>');

/**
 * SAMLP request
 */
define("SAMLP_REQUEST", '<samlp:Request xmlns:samlp="urn:oasis:names:tc:SAML:1.0:protocol"  MajorVersion="1" MinorVersion="1" RequestID="_192.168.16.51.1024506224022" IssueInstant="2002-06-19T17:03:44.022Z">');
define("SAMLP_REQUEST_CLOSE", '</samlp:Request>');

/**
 * SAMLP artifact tag (for the ticket)
 */
define("SAML_ASSERTION_ARTIFACT", '<samlp:AssertionArtifact>');

/**
 * SAMLP close
 */
define("SAML_ASSERTION_ARTIFACT_CLOSE", '</samlp:AssertionArtifact>');

/**
 * SOAP body close
 */
define("SAML_SOAP_BODY_CLOSE", '</SOAP-ENV:Body>');

/**
 * SOAP envelope close
 */
define("SAML_SOAP_ENV_CLOSE", '</SOAP-ENV:Envelope>');

/**
 * SAML Attributes
 */
define("SAML_ATTRIBUTES", 'SAMLATTRIBS');

/** @} */
/**
 * @addtogroup publicPGTStorage
 * @{
 */
// ------------------------------------------------------------------------
//  FILE PGT STORAGE
// ------------------------------------------------------------------------
/**
 * Default path used when storing PGT's to file
 */
define("CAS_PGT_STORAGE_FILE_DEFAULT_PATH", '/tmp');
/** @} */
// ------------------------------------------------------------------------
// SERVICE ACCESS ERRORS
// ------------------------------------------------------------------------
/**
 * @addtogroup publicServices
 * @{
 */

/**
 * phpCAS::service() error code on success
 */
define("PHPCAS_SERVICE_OK", 0);
/**
 * phpCAS::service() error code when the PT could not retrieve because
 * the CAS server did not respond.
 */
define("PHPCAS_SERVICE_PT_NO_SERVER_RESPONSE", 1);
/**
 * phpCAS::service() error code when the PT could not retrieve because
 * the response of the CAS server was ill-formed.
 */
define("PHPCAS_SERVICE_PT_BAD_SERVER_RESPONSE", 2);
/**
 * phpCAS::service() error code when the PT could not retrieve because
 * the CAS server did not want to.
 */
define("PHPCAS_SERVICE_PT_FAILURE", 3);
/**
 * phpCAS::service() error code when the service was not available.
 */
define("PHPCAS_SERVICE_NOT_AVAILABLE", 4);

// ------------------------------------------------------------------------
// SERVICE TYPES
// ------------------------------------------------------------------------
/**
 * phpCAS::getProxiedService() type for HTTP GET
 */
define("PHPCAS_PROXIED_SERVICE_HTTP_GET", 'CAS_ProxiedService_Http_Get');
/**
 * phpCAS::getProxiedService() type for HTTP POST
 */
define("PHPCAS_PROXIED_SERVICE_HTTP_POST", 'CAS_ProxiedService_Http_Post');
/**
 * phpCAS::getProxiedService() type for IMAP
 */
define("PHPCAS_PROXIED_SERVICE_IMAP", 'CAS_ProxiedService_Imap');


/** @} */
// ------------------------------------------------------------------------
//  LANGUAGES
// ------------------------------------------------------------------------
/**
 * @addtogroup publicLang
 * @{
 */

define("PHPCAS_LANG_ENGLISH", 'english');
define("PHPCAS_LANG_FRENCH", 'french');
define("PHPCAS_LANG_GREEK", 'greek');
define("PHPCAS_LANG_GERMAN", 'german');
define("PHPCAS_LANG_JAPANESE", 'japanese');
define("PHPCAS_LANG_SPANISH", 'spanish');
define("PHPCAS_LANG_CATALAN", 'catalan');

/** @} */

/**
 * @addtogroup internalLang
 * @{
 */

/**
 * phpCAS default language (when phpCAS::setLang() is not used)
 */
define("PHPCAS_LANG_DEFAULT", PHPCAS_LANG_ENGLISH);

/** @} */
// ------------------------------------------------------------------------
//  DEBUG
// ------------------------------------------------------------------------
/**
 * @addtogroup publicDebug
 * @{
 */

/**
 * The default directory for the debug file under Unix.
 */
define('DEFAULT_DEBUG_DIR', '/tmp/');

/** @} */
// ------------------------------------------------------------------------
//  MISC
// ------------------------------------------------------------------------
/**
 * @addtogroup internalMisc
 * @{
 */

/**
 * This global variable is used by the interface class phpCAS.
 *
 * @hideinitializer
 */
$GLOBALS['PHPCAS_CLIENT'] = null;

/**
 * This global variable is used to store where the initializer is called from
 * (to print a comprehensive error in case of multiple calls).
 *
 * @hideinitializer
 */
$GLOBALS['PHPCAS_INIT_CALL'] = array (
    'done' => FALSE,
    'file' => '?',
    'line' => -1,
    'method' => '?'
);

/**
 * This global variable is used to store where the method checking
 * the authentication is called from (to print comprehensive errors)
 *
 * @hideinitializer
 */
$GLOBALS['PHPCAS_AUTH_CHECK_CALL'] = array (
    'done' => FALSE,
    'file' => '?',
    'line' => -1,
    'method' => '?',
    'result' => FALSE
);

/**
 * This global variable is used to store phpCAS debug mode.
 *
 * @hideinitializer
 */
$GLOBALS['PHPCAS_DEBUG'] = array (
    'filename' => FALSE,
    'indent' => 0,
    'unique_id' => ''
);

/** @} */

// ########################################################################
//  CLIENT CLASS
// ########################################################################

// include client class
include_once (dirname(__FILE__) . '/CAS/Client.php');

// ########################################################################
//  INTERFACE CLASS
// ########################################################################

/**
 * @class phpCAS
 * The phpCAS class is a simple container for the phpCAS library. It provides CAS
 * authentication for web applications written in PHP.
 *
 * @ingroup public
 * @author Pascal Aubry <pascal.aubry at univ-rennes1.fr>
 *
 * \internal All its methods access the same object ($PHPCAS_CLIENT, declared
 * at the end of CAS/Client.php).
 */

class phpCAS
{
    // ########################################################################
    //  INITIALIZATION
    // ########################################################################

    /**
     * @addtogroup publicInit
     * @{
     */

    /**
     * phpCAS client initializer.
     * @note Only one of the phpCAS::client() and phpCAS::proxy functions should be
     * called, only once, and before all other methods (except phpCAS::getVersion()
     * and phpCAS::setDebug()).
     *
     * @param $server_version the version of the CAS server
     * @param $server_hostname the hostname of the CAS server
     * @param $server_port the port the CAS server is running on
     * @param $server_uri the URI the CAS server is responding on
     * @param $start_session Have phpCAS start PHP sessions (default true)
     *
     * @return a newly created CAS_Client object
     */
    public static function client($server_version, $server_hostname, $server_port, $server_uri, $start_session = true)
    {
        global $PHPCAS_CLIENT, $PHPCAS_INIT_CALL;

        phpCAS :: traceBegin();
        if (is_object($PHPCAS_CLIENT)) {
            phpCAS :: error($PHPCAS_INIT_CALL['method'] . '() has already been called (at ' . $PHPCAS_INIT_CALL['file'] . ':' . $PHPCAS_INIT_CALL['line'] . ')');
        }
        if (gettype($server_version) != 'string') {
            phpCAS :: error('type mismatched for parameter $server_version (should be `string\')');
        }
        if (gettype($server_hostname) != 'string') {
            phpCAS :: error('type mismatched for parameter $server_hostname (should be `string\')');
        }
        if (gettype($server_port) != 'integer') {
            phpCAS :: error('type mismatched for parameter $server_port (should be `integer\')');
        }
        if (gettype($server_uri) != 'string') {
            phpCAS :: error('type mismatched for parameter $server_uri (should be `string\')');
        }

        // store where the initializer is called from
        $dbg = debug_backtrace();
        $PHPCAS_INIT_CALL = array (
            'done' => TRUE,
            'file' => $dbg[0]['file'],
            'line' => $dbg[0]['line'],
            'method' => __CLASS__ . '::' . __FUNCTION__
        );

        // initialize the global object $PHPCAS_CLIENT
        $PHPCAS_CLIENT = new CAS_Client($server_version, FALSE /*proxy*/
        , $server_hostname, $server_port, $server_uri, $start_session);
        phpCAS :: traceEnd();
    }

    /**
     * phpCAS proxy initializer.
     * @note Only one of the phpCAS::client() and phpCAS::proxy functions should be
     * called, only once, and before all other methods (except phpCAS::getVersion()
     * and phpCAS::setDebug()).
     *
     * @param $server_version the version of the CAS server
     * @param $server_hostname the hostname of the CAS server
     * @param $server_port the port the CAS server is running on
     * @param $server_uri the URI the CAS server is responding on
     * @param $start_session Have phpCAS start PHP sessions (default true)
     *
     * @return a newly created CAS_Client object
     */
    public static function proxy($server_version, $server_hostname, $server_port, $server_uri, $start_session = true)
    {
        global $PHPCAS_CLIENT, $PHPCAS_INIT_CALL;

        phpCAS :: traceBegin();
        if (is_object($PHPCAS_CLIENT)) {
            phpCAS :: error($PHPCAS_INIT_CALL['method'] . '() has already been called (at ' . $PHPCAS_INIT_CALL['file'] . ':' . $PHPCAS_INIT_CALL['line'] . ')');
        }
        if (gettype($server_version) != 'string') {
            phpCAS :: error('type mismatched for parameter $server_version (should be `string\')');
        }
        if (gettype($server_hostname) != 'string') {
            phpCAS :: error('type mismatched for parameter $server_hostname (should be `string\')');
        }
        if (gettype($server_port) != 'integer') {
            phpCAS :: error('type mismatched for parameter $server_port (should be `integer\')');
        }
        if (gettype($server_uri) != 'string') {
            phpCAS :: error('type mismatched for parameter $server_uri (should be `string\')');
        }

        // store where the initialzer is called from
        $dbg = debug_backtrace();
        $PHPCAS_INIT_CALL = array (
            'done' => TRUE,
            'file' => $dbg[0]['file'],
            'line' => $dbg[0]['line'],
            'method' => __CLASS__ . '::' . __FUNCTION__
        );

        // initialize the global object $PHPCAS_CLIENT
        $PHPCAS_CLIENT = new CAS_Client($server_version, TRUE /*proxy*/
        , $server_hostname, $server_port, $server_uri, $start_session);
        phpCAS :: traceEnd();
    }

    /** @} */
    // ########################################################################
    //  DEBUGGING
    // ########################################################################

    /**
     * @addtogroup publicDebug
     * @{
     */

    /**
     * Set/unset debug mode
     *
     * @param $filename the name of the file used for logging, or FALSE to stop debugging.
     */
    public static function setDebug($filename = '')
    {
        global $PHPCAS_DEBUG;

        if ($filename != FALSE && gettype($filename) != 'string') {
            phpCAS :: error('type mismatched for parameter $dbg (should be FALSE or the name of the log file)');
        }
        if ($filename === FALSE) {
            $PHPCAS_DEBUG['filename'] = FALSE;
        } else {
            if (empty ($filename)) {
                if (preg_match('/^Win.*/', getenv('OS'))) {
                    if (isset ($_ENV['TMP'])) {
                        $debugDir = $_ENV['TMP'] . '/';
                    } else
                        if (isset ($_ENV['TEMP'])) {
                            $debugDir = $_ENV['TEMP'] . '/';
                        } else {
                            $debugDir = '';
                        }
                } else {
                    $debugDir = DEFAULT_DEBUG_DIR;
                }
                $filename = $debugDir . 'phpCAS.log';
            }

            if (empty ($PHPCAS_DEBUG['unique_id'])) {
                $PHPCAS_DEBUG['unique_id'] = substr(strtoupper(md5(uniqid(''))), 0, 4);
            }

            $PHPCAS_DEBUG['filename'] = $filename;

            phpCAS :: trace('START phpCAS-' . PHPCAS_VERSION . ' ******************');
        }
    }


    /**
     * Logs a string in debug mode.
     *
     * @param $str the string to write
     *
     * @private
     */
    public static function log($str)
    {
        $indent_str = ".";
        global $PHPCAS_DEBUG;

        if (!empty($PHPCAS_DEBUG['filename'])) {
            for ($i = 0; $i < $PHPCAS_DEBUG['indent']; $i++) {
                $indent_str .= '|    ';
            }
            // allow for multiline output with proper identing. Usefull for dumping cas answers etc.
            $str2 = str_replace("\n", "\n" . $PHPCAS_DEBUG['unique_id'] . ' ' . $indent_str, $str);
            error_log($PHPCAS_DEBUG['unique_id'] . ' ' . $indent_str . $str2 . "\n", 3, $PHPCAS_DEBUG['filename']);
        }

    }

    /**
     * This method is used by interface methods to print an error and where the function
     * was originally called from.
     *
     * @param $msg the message to print
     *
     * @private
     */
    public static function error($msg)
    {
        $dbg = debug_backtrace();
        $function = '?';
        $file = '?';
        $line = '?';
        if (is_array($dbg)) {
            for ($i = 1; $i < sizeof($dbg); $i++) {
                if (is_array($dbg[$i]) && isset($dbg[$i]['class']) ) {
                    if ($dbg[$i]['class'] == __CLASS__) {
                        $function = $dbg[$i]['function'];
                        $file = $dbg[$i]['file'];
                        $line = $dbg[$i]['line'];
                    }
                }
            }
        }
        echo "<br />\n<b>phpCAS error</b>: <font color=\"FF0000\"><b>" . __CLASS__ . "::" . $function . '(): ' . htmlentities($msg) . "</b></font> in <b>" . $file . "</b> on line <b>" . $line . "</b><br />\n";
        phpCAS :: trace($msg);
        phpCAS :: traceExit();
        exit ();
    }

    /**
     * This method is used to log something in debug mode.
     */
    public static function trace($str)
    {
        $dbg = debug_backtrace();
        phpCAS :: log($str . ' [' . basename($dbg[0]['file']) . ':' . $dbg[0]['line'] . ']');
    }

    /**
     * This method is used to indicate the start of the execution of a function in debug mode.
     */
    public static function traceBegin()
    {
        global $PHPCAS_DEBUG;

        $dbg = debug_backtrace();
        $str = '=> ';
        if (!empty ($dbg[1]['class'])) {
            $str .= $dbg[1]['class'] . '::';
        }
        $str .= $dbg[1]['function'] . '(';
        if (is_array($dbg[1]['args'])) {
            foreach ($dbg[1]['args'] as $index => $arg) {
                if ($index != 0) {
                    $str .= ', ';
                }
                if (is_object($arg)) {
                    $str .= get_class($arg);
                } else {
                    $str .= str_replace(array("\r\n", "\n", "\r"), "", var_export($arg, TRUE));
                }
            }
        }
        if (isset($dbg[1]['file']))
        $file = basename($dbg[1]['file']);
        else
        $file = 'unknown_file';
        if (isset($dbg[1]['line']))
        $line = $dbg[1]['line'];
        else
        $line = 'unknown_line';
        $str .= ') [' . $file . ':' . $line . ']';
        phpCAS :: log($str);
        $PHPCAS_DEBUG['indent']++;
    }

    /**
     * This method is used to indicate the end of the execution of a function in debug mode.
     *
     * @param $res the result of the function
     */
    public static function traceEnd($res = '')
    {
        global $PHPCAS_DEBUG;

        $PHPCAS_DEBUG['indent']--;
        $dbg = debug_backtrace();
        $str = '';
        if (is_object($res)) {
            $str .= '<= ' . get_class($res);
        } else {
            $str .= '<= ' . str_replace(array("\r\n", "\n", "\r"), "", var_export($res, TRUE));
        }

        phpCAS :: log($str);
    }

    /**
     * This method is used to indicate the end of the execution of the program
     */
    public static function traceExit()
    {
        global $PHPCAS_DEBUG;

        phpCAS :: log('exit()');
        while ($PHPCAS_DEBUG['indent'] > 0) {
            phpCAS :: log('-');
            $PHPCAS_DEBUG['indent']--;
        }
    }

    /** @} */
    // ########################################################################
    //  INTERNATIONALIZATION
    // ########################################################################
    /**
     * @addtogroup publicLang
     * @{
     */

    /**
     * This method is used to set the language used by phpCAS.
     * @note Can be called only once.
     *
     * @param $lang a string representing the language.
     *
     * @sa PHPCAS_LANG_FRENCH, PHPCAS_LANG_ENGLISH
     */
    public static function setLang($lang)
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (gettype($lang) != 'string') {
            phpCAS :: error('type mismatched for parameter $lang (should be `string\')');
        }
        $PHPCAS_CLIENT->setLang($lang);
    }

    /** @} */
    // ########################################################################
    //  VERSION
    // ########################################################################
    /**
     * @addtogroup public
     * @{
     */

    /**
     * This method returns the phpCAS version.
     *
     * @return the phpCAS version.
     */
    public static function getVersion()
    {
        return PHPCAS_VERSION;
    }

    /** @} */
    // ########################################################################
    //  HTML OUTPUT
    // ########################################################################
    /**
     * @addtogroup publicOutput
     * @{
     */

    /**
     * This method sets the HTML header used for all outputs.
     *
     * @param $header the HTML header.
     */
    public static function setHTMLHeader($header)
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (gettype($header) != 'string') {
            phpCAS :: error('type mismatched for parameter $header (should be `string\')');
        }
        $PHPCAS_CLIENT->setHTMLHeader($header);
    }

    /**
     * This method sets the HTML footer used for all outputs.
     *
     * @param $footer the HTML footer.
     */
    public static function setHTMLFooter($footer)
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (gettype($footer) != 'string') {
            phpCAS :: error('type mismatched for parameter $footer (should be `string\')');
        }
        $PHPCAS_CLIENT->setHTMLFooter($footer);
    }

    /** @} */
    // ########################################################################
    //  PGT STORAGE
    // ########################################################################
    /**
     * @addtogroup publicPGTStorage
     * @{
     */

    /**
     * This method can be used to set a custom PGT storage object.
     *
     * @param $storage a PGT storage object that inherits from the CAS_PGTStorage class
     */
    public static function setPGTStorage($storage)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if ($PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called before ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() (called at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ')');
        }
        if ( !($storage instanceof CAS_PGTStorage) ) {
            phpCAS :: error('type mismatched for parameter $storage (should be a CAS_PGTStorage `object\')');
        }
        $PHPCAS_CLIENT->setPGTStorage($storage);
        phpCAS :: traceEnd();
    }

    /**
     * This method is used to tell phpCAS to store the response of the
     * CAS server to PGT requests in a database.
     *
     * @param $dsn_or_pdo a dsn string to use for creating a PDO object or a PDO object
     * @param $username the username to use when connecting to the database
     * @param $password the password to use when connecting to the database
     * @param $table the table to use for storing and retrieving PGT's
     * @param $driver_options any driver options to use when connecting to the database
     */
    public static function setPGTStorageDb($dsn_or_pdo, $username='', $password='', $table='', $driver_options=null)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if ($PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called before ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() (called at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ')');
        }
        if (gettype($username) != 'string') {
            phpCAS :: error('type mismatched for parameter $username (should be `string\')');
        }
        if (gettype($password) != 'string') {
            phpCAS :: error('type mismatched for parameter $password (should be `string\')');
        }
        if (gettype($table) != 'string') {
            phpCAS :: error('type mismatched for parameter $table (should be `string\')');
        }
        $PHPCAS_CLIENT->setPGTStorageDb($dsn_or_pdo, $username, $password, $table, $driver_options);
        phpCAS :: traceEnd();
    }

    /**
     * This method is used to tell phpCAS to store the response of the
     * CAS server to PGT requests onto the filesystem.
     * @param $format the format used to store the PGT's. This parameter has no effect and is only for backwards compatibility
     * @param $path the path where the PGT's should be stored
     */
    public static function setPGTStorageFile($format = '', $path = '')
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if ($PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called before ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() (called at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ')');
        }
        if (gettype($format) != 'string') {
            phpCAS :: error('type mismatched for parameter $format (should be `string\')');
        }
        if (gettype($path) != 'string') {
            phpCAS :: error('type mismatched for parameter $format (should be `string\')');
        }
        $PHPCAS_CLIENT->setPGTStorageFile($path);
        phpCAS :: traceEnd();
    }

    /** @} */
    // ########################################################################
    // ACCESS TO EXTERNAL SERVICES
    // ########################################################################
    /**
     * @addtogroup publicServices
     * @{
     */

    /**
     * Answer a proxy-authenticated service handler.
     *
     * @param string $type The service type. One of:
     *			PHPCAS_PROXIED_SERVICE_HTTP_GET
     *			PHPCAS_PROXIED_SERVICE_HTTP_POST
     *			PHPCAS_PROXIED_SERVICE_IMAP
     *
     *
     * @return CAS_ProxiedService
     * @throws InvalidArgumentException If the service type is unknown.
     */
    public static function getProxiedService ($type)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after the programmer is sure the user has been authenticated (by calling ' . __CLASS__ . '::checkAuthentication() or ' . __CLASS__ . '::forceAuthentication()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        if (gettype($type) != 'string') {
            phpCAS :: error('type mismatched for parameter $type (should be `string\')');
        }

        $res = $PHPCAS_CLIENT->getProxiedService($type);

        phpCAS :: traceEnd();
        return $res;
    }

    /**
     * Initialize a proxied-service handler with the proxy-ticket it should use.
     *
     * @param CAS_ProxiedService $proxiedService
     * @return void
     * @throws CAS_ProxyTicketException If there is a proxy-ticket failure.
     *		The code of the Exception will be one of:
     *			PHPCAS_SERVICE_PT_NO_SERVER_RESPONSE
     *			PHPCAS_SERVICE_PT_BAD_SERVER_RESPONSE
     *			PHPCAS_SERVICE_PT_FAILURE
     */
    public static function initializeProxiedService (CAS_ProxiedService $proxiedService)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after the programmer is sure the user has been authenticated (by calling ' . __CLASS__ . '::checkAuthentication() or ' . __CLASS__ . '::forceAuthentication()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }

        $PHPCAS_CLIENT->initializeProxiedService($proxiedService);
    }

    /**
     * This method is used to access an HTTP[S] service.
     *
     * @param $url the service to access.
     * @param $err_code an error code Possible values are PHPCAS_SERVICE_OK (on
     * success), PHPCAS_SERVICE_PT_NO_SERVER_RESPONSE, PHPCAS_SERVICE_PT_BAD_SERVER_RESPONSE,
     * PHPCAS_SERVICE_PT_FAILURE, PHPCAS_SERVICE_NOT_AVAILABLE.
     * @param $output the output of the service (also used to give an error
     * message on failure).
     *
     * @return TRUE on success, FALSE otherwise (in this later case, $err_code
     * gives the reason why it failed and $output contains an error message).
     */
    public static function serviceWeb($url, & $err_code, & $output)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after the programmer is sure the user has been authenticated (by calling ' . __CLASS__ . '::checkAuthentication() or ' . __CLASS__ . '::forceAuthentication()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be `string\')');
        }

        $res = $PHPCAS_CLIENT->serviceWeb($url, $err_code, $output);

        phpCAS :: traceEnd($res);
        return $res;
    }

    /**
     * This method is used to access an IMAP/POP3/NNTP service.
     *
     * @param $url a string giving the URL of the service, including the mailing box
     * for IMAP URLs, as accepted by imap_open().
     * @param $service a string giving for CAS retrieve Proxy ticket
     * @param $flags options given to imap_open().
     * @param $err_code an error code Possible values are PHPCAS_SERVICE_OK (on
     * success), PHPCAS_SERVICE_PT_NO_SERVER_RESPONSE, PHPCAS_SERVICE_PT_BAD_SERVER_RESPONSE,
     * PHPCAS_SERVICE_PT_FAILURE, PHPCAS_SERVICE_NOT_AVAILABLE.
     * @param $err_msg an error message on failure
     * @param $pt the Proxy Ticket (PT) retrieved from the CAS server to access the URL
     * on success, FALSE on error).
     *
     * @return an IMAP stream on success, FALSE otherwise (in this later case, $err_code
     * gives the reason why it failed and $err_msg contains an error message).
     */
    public static function serviceMail($url, $service, $flags, & $err_code, & $err_msg, & $pt)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after the programmer is sure the user has been authenticated (by calling ' . __CLASS__ . '::checkAuthentication() or ' . __CLASS__ . '::forceAuthentication()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be `string\')');
        }

        if (gettype($flags) != 'integer') {
            phpCAS :: error('type mismatched for parameter $flags (should be `integer\')');
        }

        $res = $PHPCAS_CLIENT->serviceMail($url, $service, $flags, $err_code, $err_msg, $pt);

        phpCAS :: traceEnd($res);
        return $res;
    }

    /** @} */
    // ########################################################################
    //  AUTHENTICATION
    // ########################################################################
    /**
     * @addtogroup publicAuth
     * @{
     */

    /**
     * Set the times authentication will be cached before really accessing the CAS server in gateway mode:
     * - -1: check only once, and then never again (until you pree login)
     * - 0: always check
     * - n: check every "n" time
     *
     * @param $n an integer.
     */
    public static function setCacheTimesForAuthRecheck($n)
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (gettype($n) != 'integer') {
            phpCAS :: error('type mismatched for parameter $header (should be `string\')');
        }
        $PHPCAS_CLIENT->setCacheTimesForAuthRecheck($n);
    }

    /**
     * Set a callback function to be run when a user authenticates.
     *
     * The callback function will be passed a $logoutTicket as its first parameter,
     * followed by any $additionalArgs you pass. The $logoutTicket parameter is an
     * opaque string that can be used to map the session-id to logout request in order
     * to support single-signout in applications that manage their own sessions
     * (rather than letting phpCAS start the session).
     *
     * phpCAS::forceAuthentication() will always exit and forward client unless
     * they are already authenticated. To perform an action at the moment the user
     * logs in (such as registering an account, performing logging, etc), register
     * a callback function here.
     *
     * @param callback $function
     * @param optional array $additionalArgs
     * @return void
     */
    public static function setPostAuthenticateCallback ($function, array $additionalArgs = array())
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }

        $PHPCAS_CLIENT->setPostAuthenticateCallback($function, $additionalArgs);
    }

    /**
     * Set a callback function to be run when a single-signout request is received.
     *
     * The callback function will be passed a $logoutTicket as its first parameter,
     * followed by any $additionalArgs you pass. The $logoutTicket parameter is an
     * opaque string that can be used to map a session-id to the logout request in order
     * to support single-signout in applications that manage their own sessions
     * (rather than letting phpCAS start and destroy the session).
     *
     * @param callback $function
     * @param optional array $additionalArgs
     * @return void
     */
    public static function setSingleSignoutCallback ($function, array $additionalArgs = array())
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }

        $PHPCAS_CLIENT->setSingleSignoutCallback($function, $additionalArgs);
    }

    /**
     * This method is called to check if the user is already authenticated locally or has a global cas session. A already
     * existing cas session is determined by a cas gateway call.(cas login call without any interactive prompt)
     * @return TRUE when the user is authenticated, FALSE when a previous gateway login failed or
     * the function will not return if the user is redirected to the cas server for a gateway login attempt
     */
    public static function checkAuthentication()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }

        $auth = $PHPCAS_CLIENT->checkAuthentication();

        // store where the authentication has been checked and the result
        $dbg = debug_backtrace();
        $PHPCAS_AUTH_CHECK_CALL = array (
            'done' => TRUE,
            'file' => $dbg[0]['file'],
            'line' => $dbg[0]['line'],
            'method' => __CLASS__ . '::' . __FUNCTION__,
            'result' => $auth
        );
        phpCAS :: traceEnd($auth);
        return $auth;
    }

    /**
     * This method is called to force authentication if the user was not already
     * authenticated. If the user is not authenticated, halt by redirecting to
     * the CAS server.
     */
    public static function forceAuthentication()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }

        $auth = $PHPCAS_CLIENT->forceAuthentication();

        // store where the authentication has been checked and the result
        $dbg = debug_backtrace();
        $PHPCAS_AUTH_CHECK_CALL = array (
            'done' => TRUE,
            'file' => $dbg[0]['file'],
            'line' => $dbg[0]['line'],
            'method' => __CLASS__ . '::' . __FUNCTION__,
            'result' => $auth
        );

        if (!$auth) {
            phpCAS :: trace('user is not authenticated, redirecting to the CAS server');
            $PHPCAS_CLIENT->forceAuthentication();
        } else {
            phpCAS :: trace('no need to authenticate (user `' . phpCAS :: getUser() . '\' is already authenticated)');
        }

        phpCAS :: traceEnd();
        return $auth;
    }

    /**
     * This method is called to renew the authentication.
     **/
    public static function renewAuthentication()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        $auth = $PHPCAS_CLIENT->renewAuthentication();
        // store where the authentication has been checked and the result
        $dbg = debug_backtrace();
        $PHPCAS_AUTH_CHECK_CALL = array (
            'done' => TRUE,
            'file' => $dbg[0]['file'],
            'line' => $dbg[0]['line'],
            'method' => __CLASS__ . '::' . __FUNCTION__,
            'result' => $auth
        );

        //$PHPCAS_CLIENT->renewAuthentication();
        phpCAS :: traceEnd();
    }

    /**
     * This method is called to check if the user is authenticated (previously or by
     * tickets given in the URL).
     *
     * @return TRUE when the user is authenticated.
     */
    public static function isAuthenticated()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;

        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }

        // call the isAuthenticated method of the global $PHPCAS_CLIENT object
        $auth = $PHPCAS_CLIENT->isAuthenticated();

        // store where the authentication has been checked and the result
        $dbg = debug_backtrace();
        $PHPCAS_AUTH_CHECK_CALL = array (
            'done' => TRUE,
            'file' => $dbg[0]['file'],
            'line' => $dbg[0]['line'],
            'method' => __CLASS__ . '::' . __FUNCTION__,
            'result' => $auth
        );
        phpCAS :: traceEnd($auth);
        return $auth;
    }

    /**
     * Checks whether authenticated based on $_SESSION. Useful to avoid
     * server calls.
     * @return true if authenticated, false otherwise.
     * @since 0.4.22 by Brendan Arnold
     */
    public static function isSessionAuthenticated()
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        return ($PHPCAS_CLIENT->isSessionAuthenticated());
    }

    /**
     * This method returns the CAS user's login name.
     * @warning should not be called only after phpCAS::forceAuthentication()
     * or phpCAS::checkAuthentication().
     *
     * @return the login name of the authenticated user
     */
    public static function getUser()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::forceAuthentication() or ' . __CLASS__ . '::isAuthenticated()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        return $PHPCAS_CLIENT->getUser();
    }

    /**
     * Answer attributes about the authenticated user.
     *
     * @warning should not be called only after phpCAS::forceAuthentication()
     * or phpCAS::checkAuthentication().
     *
     * @return array
     */
    public static function getAttributes()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::forceAuthentication() or ' . __CLASS__ . '::isAuthenticated()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        return $PHPCAS_CLIENT->getAttributes();
    }

    /**
     * Answer true if there are attributes for the authenticated user.
     *
     * @warning should not be called only after phpCAS::forceAuthentication()
     * or phpCAS::checkAuthentication().
     *
     * @return boolean
     */
    public static function hasAttributes()
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::forceAuthentication() or ' . __CLASS__ . '::isAuthenticated()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        return $PHPCAS_CLIENT->hasAttributes();
    }

    /**
     * Answer true if an attribute exists for the authenticated user.
     *
     * @warning should not be called only after phpCAS::forceAuthentication()
     * or phpCAS::checkAuthentication().
     *
     * @param string $key
     * @return boolean
     */
    public static function hasAttribute($key)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::forceAuthentication() or ' . __CLASS__ . '::isAuthenticated()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        return $PHPCAS_CLIENT->hasAttribute($key);
    }

    /**
     * Answer an attribute for the authenticated user.
     *
     * @warning should not be called only after phpCAS::forceAuthentication()
     * or phpCAS::checkAuthentication().
     *
     * @param string $key
     * @return mixed string for a single value or an array if multiple values exist.
     */
    public static function getAttribute($key)
    {
        global $PHPCAS_CLIENT, $PHPCAS_AUTH_CHECK_CALL;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['done']) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::forceAuthentication() or ' . __CLASS__ . '::isAuthenticated()');
        }
        if (!$PHPCAS_AUTH_CHECK_CALL['result']) {
            phpCAS :: error('authentication was checked (by ' . $PHPCAS_AUTH_CHECK_CALL['method'] . '() at ' . $PHPCAS_AUTH_CHECK_CALL['file'] . ':' . $PHPCAS_AUTH_CHECK_CALL['line'] . ') but the method returned FALSE');
        }
        return $PHPCAS_CLIENT->getAttribute($key);
    }

    /**
     * Handle logout requests.
     */
    public static function handleLogoutRequests($check_client = true, $allowed_clients = false)
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        return ($PHPCAS_CLIENT->handleLogoutRequests($check_client, $allowed_clients));
    }

    /**
     * This method returns the URL to be used to login.
     * or phpCAS::isAuthenticated().
     *
     * @return the login name of the authenticated user
     */
    public static function getServerLoginURL()
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        return $PHPCAS_CLIENT->getServerLoginURL();
    }

    /**
     * Set the login URL of the CAS server.
     * @param $url the login URL
     * @since 0.4.21 by Wyman Chan
     */
    public static function setServerLoginURL($url = '')
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after
                                        ' . __CLASS__ . '::client()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be
                                    `string\')');
        }
        $PHPCAS_CLIENT->setServerLoginURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * Set the serviceValidate URL of the CAS server.
     * Used only in CAS 1.0 validations
     * @param $url the serviceValidate URL
     * @since 1.1.0 by Joachim Fritschi
     */
    public static function setServerServiceValidateURL($url = '')
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after
                                        ' . __CLASS__ . '::client()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be
                                    `string\')');
        }
        $PHPCAS_CLIENT->setServerServiceValidateURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * Set the proxyValidate URL of the CAS server.
     * Used for all CAS 2.0 validations
     * @param $url the proxyValidate URL
     * @since 1.1.0 by Joachim Fritschi
     */
    public static function setServerProxyValidateURL($url = '')
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after
                                        ' . __CLASS__ . '::client()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be
                                    `string\')');
        }
        $PHPCAS_CLIENT->setServerProxyValidateURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * Set the samlValidate URL of the CAS server.
     * @param $url the samlValidate URL
     * @since 1.1.0 by Joachim Fritschi
     */
    public static function setServerSamlValidateURL($url = '')
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after
                                        ' . __CLASS__ . '::client()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be
                                    `string\')');
        }
        $PHPCAS_CLIENT->setServerSamlValidateURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * This method returns the URL to be used to login.
     * or phpCAS::isAuthenticated().
     *
     * @return the login name of the authenticated user
     */
    public static function getServerLogoutURL()
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should not be called before ' . __CLASS__ . '::client() or ' . __CLASS__ . '::proxy()');
        }
        return $PHPCAS_CLIENT->getServerLogoutURL();
    }

    /**
     * Set the logout URL of the CAS server.
     * @param $url the logout URL
     * @since 0.4.21 by Wyman Chan
     */
    public static function setServerLogoutURL($url = '')
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after
                                        ' . __CLASS__ . '::client()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be
                                    `string\')');
        }
        $PHPCAS_CLIENT->setServerLogoutURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * This method is used to logout from CAS.
     * @params $params an array that contains the optional url and service parameters that will be passed to the CAS server
     * @public
     */
    public static function logout($params = "")
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        $parsedParams = array ();
        if ($params != "") {
            if (is_string($params)) {
                phpCAS :: error('method `phpCAS::logout($url)\' is now deprecated, use `phpCAS::logoutWithUrl($url)\' instead');
            }
            if (!is_array($params)) {
                phpCAS :: error('type mismatched for parameter $params (should be `array\')');
            }
            foreach ($params as $key => $value) {
                if ($key != "service" && $key != "url") {
                    phpCAS :: error('only `url\' and `service\' parameters are allowed for method `phpCAS::logout($params)\'');
                }
                $parsedParams[$key] = $value;
            }
        }
        $PHPCAS_CLIENT->logout($parsedParams);
        // never reached
        phpCAS :: traceEnd();
    }

    /**
     * This method is used to logout from CAS. Halts by redirecting to the CAS server.
     * @param $service a URL that will be transmitted to the CAS server
     */
    public static function logoutWithRedirectService($service)
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        if (!is_string($service)) {
            phpCAS :: error('type mismatched for parameter $service (should be `string\')');
        }
        $PHPCAS_CLIENT->logout(array (
            "service" => $service
        ));
        // never reached
        phpCAS :: traceEnd();
    }

    /**
     * This method is used to logout from CAS. Halts by redirecting to the CAS server.
     * @param $url a URL that will be transmitted to the CAS server
     * @deprecated The url parameter has been removed from the CAS server as of version 3.3.5.1
     */
    public static function logoutWithUrl($url)
    {
        trigger_error('Function deprecated for cas servers >= 3.3.5.1', E_USER_DEPRECATED);
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        if (!is_string($url)) {
            phpCAS :: error('type mismatched for parameter $url (should be `string\')');
        }
        $PHPCAS_CLIENT->logout(array (
            "url" => $url
        ));
        // never reached
        phpCAS :: traceEnd();
    }

    /**
     * This method is used to logout from CAS. Halts by redirecting to the CAS server.
     * @param $service a URL that will be transmitted to the CAS server
     * @param $url a URL that will be transmitted to the CAS server
     * @deprecated The url parameter has been removed from the CAS server as of version 3.3.5.1
     */
    public static function logoutWithRedirectServiceAndUrl($service, $url)
    {
        trigger_error('Function deprecated for cas servers >= 3.3.5.1', E_USER_DEPRECATED);
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        if (!is_string($service)) {
            phpCAS :: error('type mismatched for parameter $service (should be `string\')');
        }
        if (!is_string($url)) {
            phpCAS :: error('type mismatched for parameter $url (should be `string\')');
        }
        $PHPCAS_CLIENT->logout(array (
            "service" => $service,
            "url" => $url
        ));
        // never reached
        phpCAS :: traceEnd();
    }

    /**
     * Set the fixed URL that will be used by the CAS server to transmit the PGT.
     * When this method is not called, a phpCAS script uses its own URL for the callback.
     *
     * @param $url the URL
     */
    public static function setFixedCallbackURL($url = '')
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (!$PHPCAS_CLIENT->isProxy()) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be `string\')');
        }
        $PHPCAS_CLIENT->setCallbackURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * Set the fixed URL that will be set as the CAS service parameter. When this
     * method is not called, a phpCAS script uses its own URL.
     *
     * @param $url the URL
     */
    public static function setFixedServiceURL($url)
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (gettype($url) != 'string') {
            phpCAS :: error('type mismatched for parameter $url (should be `string\')');
        }
        $PHPCAS_CLIENT->setURL($url);
        phpCAS :: traceEnd();
    }

    /**
     * Get the URL that is set as the CAS service parameter.
     */
    public static function getServiceURL()
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        return ($PHPCAS_CLIENT->getURL());
    }

    /**
     * Retrieve a Proxy Ticket from the CAS server.
     */
    public static function retrievePT($target_service, & $err_code, & $err_msg)
    {
        global $PHPCAS_CLIENT;
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::proxy()');
        }
        if (gettype($target_service) != 'string') {
            phpCAS :: error('type mismatched for parameter $target_service(should be `string\')');
        }
        return ($PHPCAS_CLIENT->retrievePT($target_service, $err_code, $err_msg));
    }

    /**
     * Set the certificate of the CAS server CA.
     *
     * @param $cert the CA certificate
     */
    public static function setCasServerCACert($cert)
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        if (gettype($cert) != 'string') {
            phpCAS :: error('type mismatched for parameter $cert (should be `string\')');
        }
        $PHPCAS_CLIENT->setCasServerCACert($cert);
        phpCAS :: traceEnd();
    }

    /**
     * Set no SSL validation for the CAS server.
     */
    public static function setNoCasServerValidation()
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        $PHPCAS_CLIENT->setNoCasServerValidation();
        phpCAS :: traceEnd();
    }


    /**
     * Disable the removal of a CAS-Ticket from the URL when authenticating
     * DISABLING POSES A SECURITY RISK:
     * We normally remove the ticket by an additional redirect as a security precaution
     * to prevent a ticket in the HTTP_REFERRER or be carried over in the URL parameter
     */
    public static function setNoClearTicketsFromUrl()
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        $PHPCAS_CLIENT->setNoClearTicketsFromUrl();
        phpCAS :: traceEnd();
    }

    /** @} */

    /**
     * Change CURL options.
     * CURL is used to connect through HTTPS to CAS server
     * @param $key the option key
     * @param $value the value to set
     */
    public static function setExtraCurlOption($key, $value)
    {
        global $PHPCAS_CLIENT;
        phpCAS :: traceBegin();
        if (!is_object($PHPCAS_CLIENT)) {
            phpCAS :: error('this method should only be called after ' . __CLASS__ . '::client() or' . __CLASS__ . '::proxy()');
        }
        $PHPCAS_CLIENT->setExtraCurlOption($key, $value);
        phpCAS :: traceEnd();
    }


    /**
     * Answer an array of proxies that are sitting in front of this application.
     *
     * This method will only return a non-empty array if we have received and validated
     * a Proxy Ticket.
     *
     * @return array
     * @access public
     * @since 6/25/09
     */
    public static function getProxies ()
    {
        global $PHPCAS_CLIENT;
        if ( !is_object($PHPCAS_CLIENT) ) {
            phpCAS::error('this method should only be called after '.__CLASS__.'::client()');
        }

        return($PHPCAS_CLIENT->getProxies());
    }

}

// ########################################################################
// DOCUMENTATION
// ########################################################################

// ########################################################################
//  MAIN PAGE

/**
 * @mainpage
 *
 * The following pages only show the source documentation.
 *
 */

// ########################################################################
//  MODULES DEFINITION

/** @defgroup public User interface */

/** @defgroup publicInit Initialization
 *  @ingroup public */

/** @defgroup publicAuth Authentication
 *  @ingroup public */

/** @defgroup publicServices Access to external services
 *  @ingroup public */

/** @defgroup publicConfig Configuration
 *  @ingroup public */

/** @defgroup publicLang Internationalization
 *  @ingroup publicConfig */

/** @defgroup publicOutput HTML output
 *  @ingroup publicConfig */

/** @defgroup publicPGTStorage PGT storage
 *  @ingroup publicConfig */

/** @defgroup publicDebug Debugging
 *  @ingroup public */

/** @defgroup internal Implementation */

/** @defgroup internalAuthentication Authentication
 *  @ingroup internal */

/** @defgroup internalBasic CAS Basic client features (CAS 1.0, Service Tickets)
 *  @ingroup internal */

/** @defgroup internalProxy CAS Proxy features (CAS 2.0, Proxy Granting Tickets)
 *  @ingroup internal */

/** @defgroup internalSAML CAS SAML features (SAML 1.1)
 *  @ingroup internal */

/** @defgroup internalPGTStorage PGT storage
 *  @ingroup internalProxy */

/** @defgroup internalPGTStorageDb PGT storage in a database
 *  @ingroup internalPGTStorage */

/** @defgroup internalPGTStorageFile PGT storage on the filesystem
 *  @ingroup internalPGTStorage */

/** @defgroup internalCallback Callback from the CAS server
 *  @ingroup internalProxy */

/** @defgroup internalProxyServices Proxy other services
 *  @ingroup internalProxy */

/** @defgroup internalProxied CAS proxied client features (CAS 2.0, Proxy Tickets)
 *  @ingroup internal */

/** @defgroup internalConfig Configuration
 *  @ingroup internal */

/** @defgroup internalBehave Internal behaviour of phpCAS
 *  @ingroup internalConfig */

/** @defgroup internalOutput HTML output
 *  @ingroup internalConfig */

/** @defgroup internalLang Internationalization
 *  @ingroup internalConfig
 *
 * To add a new language:
 * - 1. define a new constant PHPCAS_LANG_XXXXXX in CAS/CAS.php
 * - 2. copy any file from CAS/languages to CAS/languages/XXXXXX.php
 * - 3. Make the translations
 */

/** @defgroup internalDebug Debugging
 *  @ingroup internal */

/** @defgroup internalMisc Miscellaneous
 *  @ingroup internal */

// ########################################################################
//  EXAMPLES

/**
 * @example example_simple.php
 */
/**
 * @example example_service.php
 */
/**
 * @example example_service_that_proxies.php
 */
/**
 * @example example_service_POST.php
 */
/**
 * @example example_proxy_serviceWeb.php
 */
/**
 * @example example_proxy_serviceWeb_chaining.php
 */
/**
 * @example example_proxy_POST.php
 */
/**
 * @example example_proxy_GET.php
 */
/**
 * @example example_lang.php
 */
/**
 * @example example_html.php
 */
/**
 * @example example_pgt_storage_file.php
 */
/**
 * @example example_gateway.php
 */
/**
 * @example example_logout.php
 */
/**
 * @example example_custom_urls.php
 */
/**
 * @example example_advanced_saml11.php
 */

<?php
/* ============================================================================
(c) Copyright 2012 Hewlett-Packard Development Company, L.P.
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge,publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE  LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
============================================================================ */
/**
 * @file
 * Implements a transporter with CURL.
 */

namespace HPCloud\Transport;

use \HPCloud\Bootstrap;

/**
 * Provide HTTP transport with CURL.
 *
 * You should choose the Curl backend if...
 *
 * - You KNOW Curl support is compiled into your PHP version
 * - You do not like the built-in PHP HTTP handler
 * - Performance is a big deal to you
 * - You will be sending large objects (>2M)
 * - Or PHP stream wrappers for URLs are not supported on your system.
 *
 * CURL is demonstrably faster than the built-in PHP HTTP handling, so
 * ths library gives a performance boost. Error reporting is slightly
 * better too.
 *
 * But the real strong point to Curl is that it can take file objects
 * and send them over HTTP without having to buffer them into strings
 * first. This saves memory and processing.
 *
 * The only downside to Curl is that it is not available on all hosts.
 * Some installations of PHP do not compile support.
 */
class CURLTransport implements Transporter
{
  const HTTP_USER_AGENT_SUFFIX = ' (c93c0a) CURL/1.0';

  protected $curlInst = NULL;
  /**
   * The curl_multi instance.
   *
   * By using curl_multi to wrap CURL requests, we can re-use the same
   * connection for multiple requests. This has tremendous value for
   * cases where several transactions occur in short order.
   */
  protected $multi = NULL;

  public function __destruct()
  {
    // Destroy the multi handle.
    if (!empty($this->multi)) {
      curl_multi_close($this->multi);
    }
  }

  /*
  public function curl($uri)
  {
    //if (empty($this->curlInst)) {
      $this->curlInst = curl_init();
    //}
    curl_setopt($this->curlInst, CURLOPT_URL, $uri);
    return $this->curlInst;
  }
   */

  public function doRequest($uri, $method = 'GET', $headers = array(), $body = NULL)
  {
    $in = NULL;
    if (!empty($body)) {
      // For whatever reason, CURL seems to want POST request data to be
      // a string, not a file handle. So we adjust. PUT, on the other hand,
      // needs to be in a file handle.
      if ($method == 'POST') {
        $in = $body;
      } else {
        // First we turn our body into a temp-backed buffer.
        $in = fopen('php://temp', 'wr', FALSE);
        fwrite($in, $body, strlen($body));
        rewind($in);
      }
    }
    return $this->handleDoRequest($uri, $method, $headers, $in);
    //return $this->handleDoRequest($uri, $method, $headers, $body);

  }

  public function doRequestWithResource($uri, $method, $headers, $resource)
  {
    if (is_string($resource)) {
      $in = open($resource, 'rb', FALSE);
    } else {
      // FIXME: Is there a better way?
      // There is a bug(?) in CURL which prevents it
      // from writing the same stream twice. But we
      // need to be able to flush a file multiple times.
      // So we have to create a new temp buffer for each
      // write operation.
      $in = fopen('php://temp', 'rb+'); //tmpfile();
      stream_copy_to_stream($resource, $in);
      rewind($in);
    }
    return $this->handleDoRequest($uri, $method, $headers, $in);
  }

  /**
   * Internal workhorse.
   */
  protected function handleDoRequest($uri, $method, $headers, $in = NULL)
  {
    // XXX: I don't like this, but I'm getting bug reports that mistakenly
    // assume this library is broken, when in fact CURL is not installed.
    if (!function_exists('curl_init')) {
      throw new \HPCloud\Exception('The CURL library is not available.');
    }

    //syslog(LOG_WARNING, "Real Operation: $method $uri");

    //$urlParts = parse_url($uri);
    $opts = array(
      CURLOPT_USERAGENT => self::HTTP_USER_AGENT . self::HTTP_USER_AGENT_SUFFIX,
      // CURLOPT_RETURNTRANSFER => TRUE, // Make curl_exec return the results.
      // CURLOPT_BINARYTRANSFER => TRUE, // Raw output if RETURNTRANSFER is TRUE.

      // Timeout if the remote has not connected in 30 sec.
      //CURLOPT_CONNECTTIMEOUT => 30,

      // If this is set, CURL will auto-deflate any encoding it can.
      // CURLOPT_ENCODING => '',

      // Later, we may want to do this to support range-based
      // fetching of large objects.
      // CURLOPT_RANGE => 'X-Y',

      // Limit curl to only these protos.
      // CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,

      // (Re-)set defaults.
      //CURLOPT_POSTFIELDS => NULL,
      //CURLOPT_INFILE => NULL,
      //CURLOPT_INFILESIZE => NULL,
    );


      $uri = str_replace(" ", "%20", $uri);

    // Write to in-mem handle backed by a temp file.
    $out = fopen('php://temp', 'wb+');
    $headerFile = fopen('php://temp', 'w+');

    $curl = curl_init($uri);
    //$curl = $this->curl($uri);
    // Set method
    $this->determineMethod($curl, $method);

    // Set the upload
    $copy = NULL;

    // If we get a string, we send the string
    // data.
    if (is_string($in)) {
      //curl_setopt($curl, CURLOPT_POSTFIELDS, $in);
      $opts[CURLOPT_POSTFIELDS] = $in;
      if (!isset($headers['Content-Length'])) {
        $headers['Content-Length'] = strlen($in);
      }
    }
    // If we get a resource, we treat it like a stream
    // and pass it into CURL as a file.
    elseif (is_resource($in)) {
      //curl_setopt($curl, CURLOPT_INFILE, $in);
      $opts[CURLOPT_INFILE] = $in;

      // Tell CURL about the content length if we know it.
      if (!empty($headers['Content-Length'])) {
        //curl_setopt($curl, CURLOPT_INFILESIZE, $headers['Content-Length']);
        $opts[CURLOPT_INFILESIZE] = $headers['Content-Length'];
        unset($headers['Content-Length']);
      }
    }

    // Set headers.
    $this->setHeaders($curl, $headers);

    // Get the output.
    //curl_setopt($curl, CURLOPT_FILE, $out);
    $opts[CURLOPT_FILE] = $out;
    //curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

      // We need to capture the headers, too.
    //curl_setopt($curl, CURLOPT_WRITEHEADER, $headerFile);
    $opts[CURLOPT_WRITEHEADER] = $headerFile;


    if (Bootstrap::hasConfig('transport.debug')) {
      $debug = Bootstrap::config('transport.debug', NULL);
      //curl_setopt($curl, CURLOPT_VERBOSE, (int) $debug);
      $opts[CURLOPT_VERBOSE] = (int) $debug;
    }

      if (Bootstrap::hasConfig('transport.timeout')) {
      //curl_setopt($curl, CURLOPT_TIMEOUT, (int) Bootstrap::config('transport.timeout'));
      $opts[CURLOPT_TIMEOUT] = (int) Bootstrap::config('transport.timeout');
    }

    if (Bootstrap::hasConfig('transport.ssl.verify')) {
      $validate = (boolean) Bootstrap::config('transport.ssl.verify', TRUE);
      //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $validate);
      $opts[CURLOPT_SSL_VERIFYPEER] = $validate;
    }


    // Set all of the curl opts and then execute.
    curl_setopt_array($curl, $opts);
    $ret = curl_exec($curl);
      \AJXP_Logger::debug("CURL $method ".$uri /*,debug_backtrace()*/);
    //$ret = $this->execCurl($curl);
    $info = curl_getinfo($curl);
      //var_dump($info);
      $status = $info['http_code'];

      //var_dump(fstat($headerFile));
    rewind($headerFile);
    $responseHeaders = $this->fetchHeaders($headerFile);
    fclose($headerFile);
      //var_dump($responseHeaders);

    if (!$ret || $status < 200 || $status > 299 || empty($responseHeaders)) {
      if (empty($responseHeaders)) {
        $err = 'Unknown (non-HTTP) error: ' . $status;
      } else {
        $err = $responseHeaders[0];
      }
      //rewind($out);
      //fwrite(STDERR, stream_get_contents($out));
      Response::failure($status, $err, $info['url'], $method, $info);
    }


    rewind($out);
    // Now we need to build a response.
    $resp = new Response($out, $info, $responseHeaders);

    //curl_close($curl);
    if (is_resource($copy)) {
      fclose($copy);
    }

    return $resp;
  }


  /**
   * Poor man's connection pooling.
   *
   * Instead of using curl_exec(), we use curl_multi_* to
   * handle the processing The CURL multi library tracks connections, and
   * basically provides connection sharing across requests. So two requests made to
   * the same server will use the same connection (even when they are executed
   * separately) assuming that the remote server supports this.
   *
   * We've noticed that this improves performance substantially, especially since
   * SSL requests only require the SSL handshake once.
   *
   * @param resource $handle
   *   A CURL handle from curl_init().
   * @retval boolean
   *   Returns a boolean value indicating whether or not CURL could process the
   *   request.
   */
  protected function execCurl($handle)
  {
    if (empty($this->multi)) {
      $multi = curl_multi_init();
      $this->multi = $multi;
      //echo "Creating MULTI handle.\n";
    } else {
      //echo "Reusing MULTI handle.\n";
      $multi = $this->multi;
    }

      curl_setopt($handle, CURLOPT_VERBOSE, true);
      $stderr = fopen("php://output", "w");
      curl_setopt($handle, CURLOPT_STDERR, $stderr);
    curl_multi_add_handle($multi, $handle);

      $active = NULL;
    do {
        $ret = curl_multi_exec($multi, $active);
        var_dump($active);
        var_dump($ret);
    } while ($ret == CURLM_CALL_MULTI_PERFORM);

    while ($active && $ret == CURLM_OK) {
        var_dump($active);
        var_dump($ret. "waiting");
        if (curl_multi_select($multi) != -1) {
            var_dump("executing next");
            sleep(1);
            do {
           $mrc = curl_multi_exec($multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    curl_multi_remove_handle($multi, $handle);
    curl_multi_close($multi);
fclose($stderr);
    return TRUE;

  }

  /**
   * This function reads the header file into an array.
   *
   * This format mataches the format returned by the stream handlers, so
   * we can re-use the header parsing logic in Response.
   *
   * @param resource $file
   *   A file pointer to the file that has the headers.
   * @retval array
   *   An array of headers, one header per line.
   */
  protected function fetchHeaders($file)
  {
    $buffer = array();
    while ($header = fgets($file)) {
      $header = trim($header);
      if ($header == 'HTTP/1.1 100 Continue') {
        // Obey the command.
        continue;
      }
      if (!empty($header)) {
        $buffer[] = $header;
      }
    }
    return $buffer;
  }

  /**
   * Set the appropriate constant on the CURL object.
   *
   * Curl handles method name setting in a slightly counter-intuitive
   * way, so we have a special function for setting the method
   * correctly. Note that since we do not POST as www-form-*, we
   * use a custom post.
   *
   * @param resource $curl
   *   A curl object.
   * @param string $method
   *   An HTTP method name.
   */
  protected function determineMethod($curl, $method)
  {
    $method = strtoupper($method);

    switch ($method) {
      case 'GET':
        curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
        break;
      case 'HEAD':
        curl_setopt($curl, CURLOPT_NOBODY, TRUE);
        break;

      // Put is problematic: Some PUT requests might not have
      // a body.
      case 'PUT':
        curl_setopt($curl, CURLOPT_PUT, TRUE);
        break;

      // We use customrequest for post because we are
      // not submitting form data.
      case 'POST':
      case 'DELETE':
      case 'COPY':
      default:
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    }

  }

  public function setHeaders($curl, $headers)
  {
    $buffer = array();
    $format = '%s: %s';

    foreach ($headers as $name => $value) {
      $buffer[] = sprintf($format, $name, $value);
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $buffer);
  }
}

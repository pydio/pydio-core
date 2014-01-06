<?php

// mamta
class HixieKey {

    public $number;
    public $key;

    public function __construct($number, $key) {
        $this->number = $number;
        $this->key = $key;
    }

}

class WebSocketProtocolVersions {

    const HIXIE_76 = 0;
    const HYBI_8 = 8;
    const HYBI_9 = 8;
    const HYBI_10 = 8;
    const HYBI_11 = 8;
    const HYBI_12 = 8;
    const LATEST = self::HYBI_12;

    private function __construct() {
        
    }

}

class WebSocketFunctions {

    /**
     * Parse a HTTP HEADER 'Cookie:' value into a key-value pair array
     *
     * @param string $line Value of the COOKIE header
     * @return array Key-value pair array
     */
    public static function cookie_parse($line) {
        $cookies = array();
        $csplit = explode(';', $line);
        $cdata = array();

        foreach ($csplit as $data) {

            $cinfo = explode('=', $data);
            $key = trim($cinfo[0]);
            $val = urldecode($cinfo[1]);

            $cookies[$key] = $val;
        }

        return $cookies;
    }

    public static function writeWholeBuffer($fp, $string) {
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written));
            if ($fwrite === false) {
                return $written;
            }
        }
        return $written;
    }

    public static function readWholeBuffer($resource) {
        $buffer = '';
        $buffsize = 8192;

        $metadata['unread_bytes'] = 0;

        do {
            if (feof($resource)) {
                return false;
            }

            $result = fread($resource, $buffsize);
            if ($result === false) {
                return false;
            }
            $buffer .= $result;

            $metadata = stream_get_meta_data($resource);

            $buffsize = min($buffsize, $metadata['unread_bytes']);
        } while ($metadata['unread_bytes'] > 0);

        return $buffer;
    }

    /**
     * Parse HTTP request into an array
     *
     * @param string $header HTTP request as a string
     * @return array Headers as a key-value pair array
     */
    public static function parseHeaders($header) {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($matches){return strtoupper($matches[0]);}, strtolower(trim($match[1])));
                if (isset($retVal[$match[1]])) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }

        if (preg_match("/GET (.*) HTTP/", $header, $match)) {
            $retVal['GET'] = $match[1];
        }

        return $retVal;
    }

    public static function calcHybiResponse($challenge) {
        return base64_encode(sha1($challenge . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }

    /**
     * Calculate the #76 draft key based on the 2 challenges from the client and the last 8 bytes of the request
     *
     * @param string $key1 Sec-WebSocket-Key1
     * @param string $key2 Sec-Websocket-Key2
     * @param string $l8b Last 8 bytes of the client's opening handshake
     */
    public static function calcHixieResponse($key1, $key2, $l8b) {
        // Get the numbers from the opening handshake
        $numbers1 = preg_replace("/[^0-9]/", "", $key1);
        $numbers2 = preg_replace("/[^0-9]/", "", $key2);

        //Count spaces
        $spaces1 = substr_count($key1, " ");
        $spaces2 = substr_count($key2, " ");

        if ($spaces1 == 0 || $spaces2 == 0) {
            throw new WebSocketInvalidKeyException($key1, $key2, $l8b);
            return null;
        }

        // Key is the number divided by the amount of spaces expressed as a big-endian 32 bit integer
        $key1_sec = pack("N", $numbers1 / $spaces1);
        $key2_sec = pack("N", $numbers2 / $spaces2);

        // The response is the md5-hash of the 2 keys and the last 8 bytes of the opening handshake, expressed as a binary string
        return md5($key1_sec . $key2_sec . $l8b, 1);
    }

    public static function randHybiKey() {
        return base64_encode(
                chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
                . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
                . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
                . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
        );
    }

    /**
     * Output a line to stdout
     *
     * @param string $msg Message to output to the STDOUT
     */
    public static function say($msg = "") {
        echo date("Y-m-d H:i:s") . " | " . $msg . "\n";
    }

    // mamta
    public static function genKey3() {
        return "" . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
                . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255));
    }

    public static function randHixieKey() {
        $_MAX_INTEGER = (1 << 32) - 1;
        #$_AVAILABLE_KEY_CHARS = range(0x21, 0x2f + 1) + range(0x3a, 0x7e + 1);
        #$_MAX_CHAR_BYTE = (1<<8) -1;
        # $spaces_n = 2;
        $spaces_n = rand(1, 12); // random.randint(1, 12)
        $max_n = $_MAX_INTEGER / $spaces_n;
        # $number_n = 123456789;
        $number_n = rand(0, $max_n); // random.randint(0, max_n)
        $product_n = $number_n * $spaces_n;
        $key_n = "" . $product_n;
        # $range = 3; //
        $range = rand(1, 12);
        for ($i = 0; $i < $range; $i++) {
            #i in range(random.randint(1, 12)):
            if (rand(0, 1) > 0) {
                $c = chr(rand(0x21, 0x2f + 1)); #random.choice(_AVAILABLE_KEY_CHARS)
            } else {
                $c = chr(rand(0x3a, 0x7e + 1)); #random.choice(_AVAILABLE_KEY_CHARS)
            }
            # $c = chr(65);
            $len = strlen($key_n);
            # $pos = 2;
            $pos = rand(0, $len);
            $key_n1 = substr($key_n, 0, $pos);
            $key_n2 = substr($key_n, $pos);
            $key_n = $key_n1 . $c . $key_n2;
        }
        for ($i = 0; $i < $spaces_n; $i++) {
            $len = strlen($key_n);
            # $pos = 2;
            $pos = rand(1, $len - 1);
            $key_n1 = substr($key_n, 0, $pos);
            $key_n2 = substr($key_n, $pos);
            $key_n = $key_n1 . " " . $key_n2;
        }

        return new HixieKey($number_n, $key_n);
    }

}

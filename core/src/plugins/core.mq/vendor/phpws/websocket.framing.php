<?php

/**
 * Enum-like construct containing all opcodes defined in the WebSocket protocol

 * @author Chris
 *
 */
class WebSocketOpcode {

    const __default = 0;
    const ContinuationFrame = 0x00;
    const TextFrame = 0x01;
    const BinaryFrame = 0x02;
    const CloseFrame = 0x08;
    const PingFrame = 0x09;
    const PongFrame = 0x09;

    private function __construct() {
        
    }

    /**
     * Check if a opcode is a control frame. Control frames should be handled internally by the server.
     * @param int $type
     */
    public static function isControlFrame($type) {
        $controlframes = array(self::CloseFrame, self::PingFrame, self::PongFrame);

        return array_search($type, $controlframes) !== false;
    }

}

/**
 * Interface for WebSocket frames. One or more frames compose a message.
 * In the case of the Hixie protocol, a message contains of one frame only
 *
 * @author Chris
 */
interface IWebSocketFrame {

    /**
     * Serialize the frame so that it can be send over a socket
     * @return string Serialized binary string
     */
    public function encode();

    /**
     * Deserialize a binary string into a IWebSocketFrame
     * @return string Serialized binary string
     */
    public static function decode(&$string, $head = null);

    /**
     * @return string Payload Data inside the frame
     */
    public function getData();

    /**
     * @return int The frame type (opcode)
     */
    public function getType();

    /**
     * Create a frame by type and payload data
     * @param int $type
     * @param string $data
     *
     * @return IWebSocketFrame
     */
    public static function create($type, $data = null);
}

/**
 * HYBIE WebSocketFrame
 *
 * @author Chris
 *
 */
class WebSocketFrame implements IWebSocketFrame {

    // First Byte
    protected $FIN = 0;
    protected $RSV1 = 0;
    protected $RSV2 = 0;
    protected $RSV3 = 0;
    protected $opcode = WebSocketOpcode::TextFrame;
    // Second Byte
    protected $mask = 0;
    protected $payloadLength = 0;
    protected $maskingKey = 0;
    protected $payloadData = '';
    protected $actualLength = 0;

    private function __construct() {
        
    }

    public static function create($type, $data = null) {
        $o = new self();

        $o->FIN = true;
        $o->payloadData = $data;
        $o->payloadLength = $data != null ? strlen($data) : 0;
        $o->setType($type);

        return $o;
    }

    public function setMasked($mask) {
        $this->mask = $mask ? 1 : 0;
    }

    public function isMasked() {
        return $this->mask == 1;
    }

    protected function setType($type) {
        $this->opcode = $type;

        if ($type == WebSocketOpcode::CloseFrame)
            $this->mask = 1;
    }

    protected static function IsBitSet($byte, $pos) {
        return ($byte & pow(2, $pos)) > 0 ? 1 : 0;
    }

    protected static function rotMask($data, $key, $offset = 0) {
        $res = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $j = ($i + $offset) % 4;
            $res .= chr(ord($data[$i]) ^ ord($key[$j]));
        }

        return $res;
    }

    public function getType() {
        return $this->opcode;
    }

    public function encode() {
        $this->payloadLength = strlen($this->payloadData);

        $firstByte = $this->opcode;

        $firstByte += $this->FIN * 128 + $this->RSV1 * 64 + $this->RSV2 * 32 + $this->RSV3 * 16;

        $encoded = chr($firstByte);

        if ($this->payloadLength <= 125) {
            $secondByte = $this->payloadLength;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte);
        } else if ($this->payloadLength <= 255 * 255 - 1) {
            $secondByte = 126;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte) . pack("n", $this->payloadLength);
        } else {
            // TODO: max length is now 32 bits instead of 64 !!!!!
            $secondByte = 127;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte);
            $encoded .= pack("N", 0);
            $encoded .= pack("N", $this->payloadLength);
        }

        $key = 0;
        if ($this->mask) {
            $key = pack("N", rand(0, pow(255, 4) - 1));
            $encoded .= $key;
        }

        if ($this->payloadData)
            $encoded .= ($this->mask == 1) ? $this->rotMask($this->payloadData, $key) : $this->payloadData;

        return $encoded;
    }

    public static function decode(&$raw, $head = null) {
        if ($head != null) {
            $frame = $head;
        } else {
            $frame = new self();

            // Read the first two bytes, then chop them off
            list($firstByte, $secondByte) = substr($raw, 0, 2);
            $raw = substr($raw, 2);

            $firstByte = ord($firstByte);
            $secondByte = ord($secondByte);

            $frame->FIN = self::IsBitSet($firstByte, 7);
            $frame->RSV1 = self::IsBitSet($firstByte, 6);
            $frame->RSV2 = self::IsBitSet($firstByte, 5);
            $frame->RSV3 = self::IsBitSet($firstByte, 4);

            $frame->mask = self::IsBitSet($secondByte, 7);

            $frame->opcode = ($firstByte & 0x0F);

            $len = $secondByte & ~128;

            if ($len <= 125)
                $frame->payloadLength = $len;
            elseif ($len == 126) {
                $arr = unpack("nfirst", $raw);
                $frame->payloadLength = array_pop($arr);
                $raw = substr($raw, 2);
            } elseif ($len == 127) {
                list(, $h, $l) = unpack('N2', $raw);
                $frame->payloadLength = ($l + ($h * 0x0100000000));
                $raw = substr($raw, 8);
            }

            if ($frame->mask) {
                $frame->maskingKey = substr($raw, 0, 4);
                $raw = substr($raw, 4);
            }
        }

        $currentOffset = $frame->actualLength;
        $fullLength = min($frame->payloadLength - $frame->actualLength, strlen($raw));
        $frame->actualLength += $fullLength;

        if ($fullLength < strlen($raw)) {
            $frameData = substr($raw, 0, $fullLength);
            $raw = substr($raw, $fullLength);
        } else {
            $frameData = $raw;
            $raw = '';
        }

        if ($frame->mask)
            $frame->payloadData .= self::rotMask($frameData, $frame->maskingKey, $currentOffset);
        else
            $frame->payloadData .= $frameData;

        return $frame;
    }

    public function isReady() {
        if ($this->actualLength > $this->payloadLength) {
            throw new WebSocketFrameSizeMismatch($this);
        }
        return ($this->actualLength == $this->payloadLength);
    }

    public function isFinal() {
        return $this->FIN == 1;
    }

    public function getData() {
        return $this->payloadData;
    }

}

class WebSocketFrame76 implements IWebSocketFrame {

    public $payloadData = '';
    protected $opcode = WebSocketOpcode::TextFrame;

    public static function create($type, $data = null) {
        $o = new self();

        $o->payloadData = $data;

        return $o;
    }

    public function encode() {
        return chr(0) . $this->payloadData . chr(255);
    }

    public function getData() {
        return $this->payloadData;
    }

    public function getType() {
        return $this->opcode;
    }

    public static function decode(&$str, $head = null) {
        $o = new self();
        $o->payloadData = substr($str, 1, strlen($str) - 2);

        return $o;
    }

}

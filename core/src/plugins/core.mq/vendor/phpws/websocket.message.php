<?php

/**
 * 
 * Interface for incoming and outgoing messages
 * @author Chris
 *
 */
interface IWebSocketMessage {

    /**
     * Retreive an array of frames of which this message is composed
     * 
     * @return WebSocketFrame[]
     */
    public function getFrames();

    /**
     * Set the body of the message 
     * This should recompile the array of frames
     * @param string $data
     */
    public function setData($data);

    /**
     * Retreive the body of the message
     * @return string
     */
    public function getData();

    /**
     * Create a new message
     * @param string $data Content of the message to be created
     */
    public static function create($data);

    /**
     * Check if we have received the last frame of the message
     *  
     * @return bool
     */
    public function isFinalised();

    /**
     * Create a message from it's first frame
     * @param IWebSocketFrame $frame
     * @throws Exception
     */
    public static function fromFrame(IWebSocketFrame $frame);
}

/**
 * WebSocketMessage compatible with the Hixie Draft #76
 * Used for backwards compatibility with older versions of Chrome and
 * several Flash fallback solutions
 * 
 * @author Chris
 */
class WebSocketMessage76 implements IWebSocketMessage {

    protected $data = '';
    protected $frame = null;

    public static function create($data) {
        $o = new self();

        $o->setData($data);
        return $o;
    }

    public function getFrames() {
        $arr = array();

        $arr[] = $this->frame;

        return $arr;
    }

    public function setData($data) {
        $this->data = $data;
        $this->frame = WebSocketFrame76::create(WebSocketOpcode::TextFrame, $data);
    }

    public function getData() {
        return $this->frame->getData();
    }

    public function isFinalised() {
        return true;
    }

    /**
     * Creates a new WebSocketMessage76 from a IWebSocketFrame
     * @param IWebSocketFrame $frame
     * 
     * @return WebSocketMessage76 Message composed of the frame provided
     */
    public static function fromFrame(IWebSocketFrame $frame) {
        $o = new self();
        $o->frame = $frame;

        return $o;
    }

}

/**
 * WebSocketMessage compatible with the latest draft.
 * Should be updated to keep up with the latest changes.
 * 
 * @author Chris
 *
 */
class WebSocketMessage implements IWebSocketMessage {

    /**
     * 
     * Enter description here ...
     * @var WebSocketFrame[];
     */
    protected $frames = array();
    protected $data = '';

    public function setData($data) {
        $this->data = $data;

        $this->createFrames();
    }

    public static function create($data) {
        $o = new self();

        $o->setData($data);
        return $o;
    }

    public function getData() {
        if ($this->isFinalised() == false)
            throw new WebSocketMessageNotFinalised($this);

        $data = '';

        foreach ($this->frames as $frame) {
            $data .= $frame->getData();
        }

        return $data;
    }

    public static function fromFrame(IWebSocketFrame $frame) {
        $o = new self();
        $o->takeFrame($frame);

        return $o;
    }

    protected function createFrames() {
        $this->frames = array(WebSocketFrame::create(WebSocketOpcode::TextFrame, $this->data));
    }

    public function getFrames() {
        return $this->frames;
    }

    public function isFinalised() {
        if (count($this->frames) == 0)
            return false;

        return $this->frames[count($this->frames) - 1]->isFinal();
    }

    /**
     * Append a frame to the message
     * @param unknown_type $frame
     */
    public function takeFrame(WebSocketFrame $frame) {
        $this->frames[] = $frame;
    }

}
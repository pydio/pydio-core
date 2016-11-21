<?php

namespace Pydio\Access\Core\Stream;

use GuzzleHttp\Stream\Stream as GuzzleStream;
use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;

/**
 * Provides a buffer stream that can be written to to fill a buffer, and read
 * from to remove bytes from the buffer.
 *
 * This stream returns a "hwm" metadata value that tells upstream consumers
 * what the configured high water mark of the stream is, or the maximum
 * preferred size of the buffer.
 *
 * @package GuzzleHttp\Stream
 */
class WriteBufferStream implements StreamInterface {

    use StreamDecoratorTrait;

    /** @var StreamInterface $bufferStream */
    private $bufferStream;

    private $size;
    private $hwm;

    /**
     * @param StreamInterface $stream
     * @param AJXP_Node $node
     * @param int $hwm High water mark, representing the preferred maximum
     *                 buffer size. If the size of the buffer exceeds the high
     *                 water mark, then calls to write will continue to succeed
     *                 but will return false to inform writers to slow down
     *                 until the buffer has been drained by reading from it.
     */
    public function __construct(
        StreamInterface $stream,
        AJXP_Node $node,
        $hwm = -1) {

        $this->bufferStream = new GuzzleStream(fopen('php://temp', 'w+'));

        $this->stream = $stream;

        $this->hwm = $hwm;
    }

    /**
     * @return string
     */
    public function getContents() {
        return $this->stream->getContents();
    }

    public function close() {
        $this->stream->write($this->bufferStream);

        $this->size = 0;
        return $this->stream->close();
    }

    /**
     * Writes data to the buffer.
     * @param string $string
     * @return bool|int
     */
    public function write($string)
    {
        $this->size += $this->bufferStream->write($string);

        if ($this->hwm > 0 && $this->size >= $this->hwm) {
            $this->stream->write($this->bufferStream->getContents());

            $this->size = 0;

            return false;
        }

        return strlen($string);
    }
}

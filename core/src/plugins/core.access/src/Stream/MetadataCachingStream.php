<?php
/**
 * Created by PhpStorm.
 * User: ghecquet
 * Date: 21/06/16
 * Time: 09:39
 */

namespace Pydio\Access\Core\Stream;
use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;

/**
 * Stream decorator that can cache previously read bytes from a sequentially
 * read stream.
 */
class MetadataCachingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var array stat */
    private static $stat;

    /** @var array stat */
    private $size = -1;

    /** @var boolean is_file */
    private $is_file = null;

    /** @var string uri */
    private $uri;

    /**
     * We will treat the buffer object as the body of the stream
     *
     * @param StreamInterface $stream Stream to cache
     * @param AJXP_Node $node
     * @param StreamInterface $target
     */
    public function __construct(
        StreamInterface $stream,
        AJXP_Node $node,
        StreamInterface $target = null
    ) {
        $this->uri = $node->getUrl();

        $this->stream = $stream;

        $this->stat();
    }

    public function __destruct() {
        $this->stream->close();
    }

    public function getSize() {
        $stat = $this->stat();

        if (isset($stat["size"])) {
            $this->size = (int) $stat["size"];
            return $this->size;
        }

        return null;
    }

    public function isFile() {
        $stat = $this->stat();
        if (isset($stat["type"])) {
            return ($stat["type"] != "folder");
        }

        return null;
    }

    public function getContents() {
        return $this->stream->getContents();
    }

    public function stat() {
        if (isset(self::$stat[$this->uri])) {
            return self::$stat[$this->uri];
        }

        self::$stat[$this->uri] = $this->stream->stat();
        return self::$stat[$this->uri];
    }
}

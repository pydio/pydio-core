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

    /** @var string uri */
    private $uri;

    /** @var string path */
    private $path;

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
        $this->path = parse_url($this->uri, PHP_URL_PATH);

        $this->stream = $stream;

        $this->stat();
    }

    public function __destruct() {
        $this->stream->close();
    }

    public function getSize() {
        $stat = $this->stat();

        if (isset($stat["size"])) {
            return (int) $stat["size"];
        }

        return null;
    }

    public function getLastModifiedTime() {
        $stat = $this->stat();

        if (isset($stat["mtime"])) {
            return (int) $stat["mtime"];
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

        $stats = $this->stream->stat();

        // Some APIs will return us multiple uri to avoid having to send multiple requests
        // So storing them in a local cache
        if (is_array($stats[0])) {
            foreach ($stats as $stat) {
                $path = $stat["name"];
                $key = rtrim($this->uri . "/" . $path, "/");
                self::$stat[$key] = $stat;
            }
        } else {
            self::$stat[$this->uri] = $stats;
        }

        return self::$stat[$this->uri];
    }
}

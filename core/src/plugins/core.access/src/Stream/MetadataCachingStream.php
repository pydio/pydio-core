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
use Pydio\Cache\Core\AbstractCacheDriver;
use Pydio\Core\Services\CacheService;

/**
 * Stream decorator that can cache previously read bytes from a sequentially
 * read stream.
 */
class MetadataCachingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var array stat */
    private static $stat;

    /** @var AJXP_Node node */
    private $node;

    /** @var string uri */
    private $uri;

    /** @var array contentFilters */
    private $contentFilters;

    /** @var string path */
    private $path;

    /** @var array statCacheId */
    private $cacheOptions;

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
        $this->node = $node;
        $this->uri = $node->getUrl();
        $this->cacheOptions = AbstractCacheDriver::getOptionsForNode($node, "meta");
        $this->contentFilters = $node->getRepository()->getContentFilter()->filters;
        $this->path = parse_url($this->uri, PHP_URL_PATH);

        $this->stream = $stream;

        $this->stat();
    }

    /**
     * @return int|null
     */
    public function getSize() {
        $stat = $this->stat();

        if (isset($stat["size"])) {
            return (int) $stat["size"];
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getLastModifiedTime() {
        $stat = $this->stat();

        if (isset($stat["mtime"])) {
            return (int) $stat["mtime"];
        }

        return null;
    }

    /**
     * @return bool|null
     */
    public function isFile() {
        $stat = $this->stat();
        if (isset($stat["type"])) {
            return ($stat["type"] != "folder");
        }

        return null;
    }

    /**
     * @return string
     */
    public function getContents() {
        return $this->stream->getContents();
    }

    /**
     * @return bool|mixed
     */
    public function stat() {

        $stat = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $this->cacheOptions["id"]);

        if(is_array($stat)) return $stat;

        $stats = $this->stream->stat();

        // Some APIs will return us multiple uri to avoid having to send multiple requests
        // So storing them in a local cache
        if (is_array($stats[0])) {
            foreach ($stats as $stat) {
                $path = "/" . $stat["name"];

                if (isset($this->contentFilters[$path])) {
                    $path = $this->contentFilters[$path];
                }

                $node = new AJXP_Node($this->uri . "/" . $path);

                $id = AbstractCacheDriver::getOptionsForNode($node, "meta")["id"];

                CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $id, $stat);
            }
        } else {
            CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $this->cacheOptions["id"], $stats, $this->cacheOptions["timelimit"]);
        }

        return CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $this->cacheOptions["id"]);
    }

    public function write($string) {
        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->cacheOptions["id"]);

        return $this->stream->write($string);
    }

    public function rename($newNode) {
        $parentNode = $this->node->getParent();

        if (isset($parentNode)) {
            $parentOptions = AbstractCacheDriver::getOptionsForNode($parentNode, "meta");
            CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $parentOptions["id"]);
        }

        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->cacheOptions["id"]);

        $this->node = $newNode;
        $this->uri = $newNode->getUrl();
        $this->cacheOptions = AbstractCacheDriver::getOptionsForNode($newNode, "meta");
        $this->contentFilters = $newNode->getRepository()->getContentFilter()->filters;
        $this->path = parse_url($newNode->uri, PHP_URL_PATH);

        return $this->stream->rename($newNode);
    }

    public function delete() {
        $parentNode = $this->node->getParent();

        if (isset($parentNode)) {
            $parentOptions = AbstractCacheDriver::getOptionsForNode($parentNode, "meta");
            CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $parentOptions["id"]);
        }

        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $this->cacheOptions["id"]);

        return $this->stream->delete();
    }
}

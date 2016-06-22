<?php
namespace Pydio\Access\Core\Stream;

use Exception;
use Guzzle\Service\Loader\JsonLoader;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Client as HTTPClient;
use GuzzleHttp\Stream\GuzzleStreamWrapper;
use GuzzleHttp\Stream\Stream as GuzzleStream;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\ContextInterface;
use Symfony\Component\Config\FileLocator;


/**
 * Decorator used to return only a subset of a stream
 */
class Stream implements StreamInterface
{
    private $resource;
    private $size;
    private $customMetadata;

    /** @var AJXP_Node $node */
    private $node;

    /** @var GuzzleClient $client */
    private $client;

    private $seekable = true;
    private $readable = true;
    private $writable = true;

    /** @var array Hash of readable and writable stream types */
    private static $readWriteHash = [
        'read' => [
            'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true
        ],
        'write' => [
            'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true
        ]
    ];

    /**
     * Stream constructor.
     * @param $resource
     * @param AJXP_Node $node
     * @param $options
     * @throws \Exception
     */
    public function __construct(
        $resource,
        AJXP_Node $node,
        $options = []
    ) {
        $ctx = $node->getContext();
        $repository = $ctx->getRepository();

        $this->customMetadata["uri"] = $node->getUrl();

        $apiUrl = $repository->getContextOption($ctx, "API_URL");
        $host = $repository->getContextOption($ctx, "HOST");
        $uri = $repository->getContextOption($ctx, "URI");
        $resourcePath = $repository->getContextOption($ctx, "API_RESOURCES_PATH");
        $resourceFile = $repository->getContextOption($ctx, "API_RESOURCES_FILE");

        if ($apiUrl == "") {
            $apiUrl = $options["api_url"];

            if ($apiUrl == "") {
                $apiUrl = $host . $uri;
            }
        }

        if ($resourcePath == "") {
            $resourcePath = $options["api_resources_path"];
        }

        if ($resourceFile == "") {
            $resourceFile = $options["api_resources_file"];
        }

        $options["base_url"] = $apiUrl;
        $default = stream_context_get_options(stream_context_get_default());;
        $options["defaults"] = $default[$node->getScheme()];

        // Creating Guzzle instances
        $httpClient = new HTTPClient($options);
        $locator = new FileLocator([$resourcePath]);
        $jsonLoader = new JsonLoader($locator);
        $description = $jsonLoader->load($locator->locate($resourceFile));
        $description = new Description($description);
        $client = new GuzzleClient($httpClient, $description, $options);

        foreach ($options["defaults"]["subscribers"] as $subscriber) {
            $client->getEmitter()->attach($subscriber);
        }

        $stream = Stream::factory($resource);
        $resource = PydioStreamWrapper::getResource($stream);
        $this->attach($resource);

        $this->node = $node;
        $this->client = $client;
    }

    public static function factory($resource = '', array $options = [])
    {
        if ($resource instanceof AJXP_Node) {
            $stream = fopen('php://memory', 'r+');

            return new self($stream, $resource, $options);
        }

        return GuzzleStream::factory($resource, $options);
    }

    public static function addContextOption(ContextInterface $ctx, array $arr) {

        $default = stream_context_get_options(stream_context_get_default());

        $contextKey = "access." . $ctx->getRepository()->getAccessType();

        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $orig = [];
                if (isset($default[$contextKey][$key])) {
                    $orig = $default[$contextKey][$key];
                }
                $default[$contextKey][$key] = array_merge($orig, $value);
            } else {
                $default[$contextKey][$key] = $value;
            }
        }

        stream_context_set_default($default);
    }

    public static function getContextOption(ContextInterface $ctx, $key = null) {
        $default = stream_context_get_options(stream_context_get_default());

        $contextKey = "access." . $ctx->getRepository()->getAccessType();

        if ($key != null && isset($default[$contextKey][$key])) {
            return $default[$contextKey][$key];
        } elseif ($key == null) {
            return $default[$contextKey];
        }

        return null;
    }

    public function __toString()
    {
        if (!$this->resource) {
            return '';
        }

        $this->seek(0);

        return (string) stream_get_contents($this->resource);
    }

    public function getContents()
    {
        $uri = $this->getMetadata("uri");

        if (!is_file($uri)) {
            return $this->ls();
        } else {
            return $this->get();
        }
    }

    public function close()
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }

        $this->detach();
    }

    public function detach()
    {
        $result = $this->resource;
        $this->resource = $this->size = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    public function attach($stream) {
        $this->resource = $stream;
        $meta = stream_get_meta_data($this->resource);

        $this->seekable = $meta['seekable'];
        $this->readable = isset(self::$readWriteHash['read'][$meta['mode']]);
        $this->writable = isset(self::$readWriteHash['write'][$meta['mode']]);
    }

    /**
     * Returns the size of the limited subset of data
     * {@inheritdoc}
     */
    public function getSize() {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!$this->resource) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        $uri = $this->getMetadata("uri");
        if (isset($uri)) {
            clearstatcache(true, $uri);
        }

        $stat = $this->stat();

        if (isset($stat["size"])) {
            $this->size = (int) $stat["size"];
            return $this->size;
        }

        return null;
    }

    public function isFile() {
        if (!$this->resource) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        $uri = $this->getMetadata("uri");
        if (isset($uri)) {
            clearstatcache(true, $uri);
        }

        $stat = $this->stat();
        if (isset($stat["type"])) {
            return ($stat["type"] != "folder");
        }

        return null;
    }

    public function isReadable() {
        return $this->readable;
    }

    public function isWritable() {
        return $this->writable;
    }

    public function isSeekable() {
        return $this->seekable;
    }

    public function eof() {
        return !$this->resource || feof($this->resource);
    }

    /**
     * Allow for a bounded seek on the read limited stream
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET) {
        return $this->seekable
            ? fseek($this->resource, $offset, $whence) === 0
            : false;
    }

    /**
     * Give a relative tell()
     * {@inheritdoc}
     */
    public function tell() {
        return $this->resource ? ftell($this->resource) : false;
    }

    public function read($length) {
        return $this->readable ? fread($this->resource, $length) : false;
    }

    public function write($string) {
        // We can't know the size after writing anything
        $this->size = null;

        $this->resource->write($string);
    }

    public function getMetadata($key = null) {
        if (!$this->resource) {
            return $key ? null : [];
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        } elseif ($this->resource instanceof GuzzleStream) {
            return $this->resource->getMetadata($key);
        } elseif (!$key) {
            return $this->customMetadata + stream_get_meta_data($this->resource);
        }
    }

    private function ls() {

        $command = $this->client->getCommand('Ls', [
            'path' => $this->node
        ]);

        $result = $this->client->execute($command);

        return $result;
    }

    private function get() {
        $path = $this->node->getPath();
        if (!isset($path)) {
            $path = "";
        }

        $command = $this->client->getCommand('Get', [
            'path' => $path
        ]);

        $result = $this->client->execute($command);

        $this->detach();
        $this->attach(GuzzleStreamWrapper::getResource($result["body"]));

        return $result;
    }

    public function stat() {
        $path = $this->node->getPath();
        if (!isset($path)) {
            $path = "";
        }

        $command = $this->client->getCommand('Stat', [
            'path' => $this->node
        ]);

        try {
            $result = $this->client->execute($command);
        } catch (Exception $e) {
            return null;
        }

        return $result;
    }

    public function mkdir() {
        $path = $this->node->getPath();
        if (!isset($path)) {
            $path = "";
        }

        $command = $this->client->getCommand('Mkdir', [
            'path' => $path
        ]);

        $this->client->execute($command);

        return true;
    }

    public function rmdir() {
        $path = $this->node->getPath();
        if (!isset($path)) {
            $path = "";
        }

        $command = $this->client->getCommand('Rmdir', [
            'path' => $path
        ]);

        $this->client->execute($command);

        return true;
    }

    /*private function put($stream) {
        $path = $this->node->getPath();

        if (!isset($path)) {
            $path = "";
        }

        stream_copy_to_stream();
    }*/

    /*private function rename() {
        $path = $this->node->getPath();
        if (!isset($path)) {
            $path = "";
        }

        $command = $this->client->getCommand('Rmdir', [
            'path' => $path
        ]);

        $this->client->execute($command);

        return true;
    }*/
}
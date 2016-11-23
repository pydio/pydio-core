<?php
namespace Pydio\Access\Core\Stream;

use Exception;
use Guzzle\Service\Loader\JsonLoader;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
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
    private $lastModifiedTime;
    private $customMetadata;

    /** @var AJXP_Node $node */
    private $node;

    /** @var GuzzleClient $client */
    private $client;

    /** @var Client $httpClient */
    private $httpClient;

    /** @var Command $command */
    private $command;

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

        $this->node = $node;

        $ctx = $node->getContext();
        $repository = $ctx->getRepository();

        $this->customMetadata["uri"] = $node->getUrl();

        $this->attach($resource);

        $this->readable = isset($options['readable']) ? $options['readable'] : $this->readable;
        $this->writable = isset($options['writable']) ? $options['writable'] : $this->writable;

        $apiUrl = $repository->getContextOption($ctx, "API_URL");
        $host = $repository->getContextOption($ctx, "HOST");
        $uri = $repository->getContextOption($ctx, "URI");

        if ($apiUrl == "") {
            $apiUrl = $options["api_url"];

            if ($apiUrl == "") {
                $apiUrl = $host . $uri;
            }
        }

        $options["base_url"] = $apiUrl;
        $this->httpClient = new Client([
            "base_url" => $apiUrl,
            "defaults" => [
                "allow_redirects" => [
                    "max"       => 5,       // allow at most 10 redirects.
                    "strict"    => true,     // use "strict" RFC compliant redirects.
                    "referer"   => true,     // add a Referer header
                    "protocols" => ['http', 'https'] // only allow https URLs
                ]
            ]
        ]);

        $options["defaults"] = self::getContextOption($ctx);
        $resources = $options["defaults"]["resources"];

        $locator = new FileLocator([dirname($resources)]);
        $jsonLoader = new JsonLoader($locator);
        $description = $jsonLoader->load($locator->locate(basename($resources)));
        $description = new Description($description);
        $client = new GuzzleClient($this->httpClient, $description);

        foreach ($options["defaults"]["subscribers"] as $subscriber) {
            $client->getEmitter()->attach($subscriber);
        }

        $this->client = $client;
    }

    /**
     * @param string $resource
     * @param string $mode
     * @param array $options
     * @return GuzzleStream|Stream
     */
    public static function factory($resource = '', $mode = "r+", array $options = []) {
        if ($resource instanceof AJXP_Node) {

            $node = $resource;

            return new self(fopen('php://memory', $mode), $node, [
                "readable" => isset(self::$readWriteHash['read'][$mode]),
                "writable" => isset(self::$readWriteHash['write'][$mode])
            ]);
        }

        return GuzzleStream::factory($resource, $options);
    }

    /**
     * @param ContextInterface $ctx
     * @param array $arr
     */
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

    /**
     * @param ContextInterface $ctx
     * @param null $key
     * @param null $default
     * @return null
     */
    public static function getContextOption(ContextInterface $ctx, $key = null, $default = null) {
        $options = stream_context_get_options(stream_context_get_default());

        $contextKey = "access." . $ctx->getRepository()->getAccessType();

        if ($key != null && isset($options[$contextKey][$key])) {
            return $options[$contextKey][$key];
        } elseif ($key == null) {
            return $options[$contextKey];
        }

        return $default;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (!$this->resource) {
            return '';
        }

        $this->seek(0);

        return (string) stream_get_contents($this->resource);
    }

    /**
     * @return \GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
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

    /**
     * @return mixed
     */
    public function detach()
    {
        $result = $this->resource;
        $this->resource = $this->size = null;
        $this->readable = $this->writable = $this->seekable = false;

        return $result;
    }

    /**
     * @param resource $stream
     */
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

    /**
     * Returns the size of the limited subset of data
     * {@inheritdoc}
     */
    public function getLastModifiedTime() {
        if ($this->lastModifiedTime !== null) {
            return $this->lastModifiedTime;
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

        if (isset($stat["mtime"])) {
            $this->lastModifiedTime = (int) $stat["mtime"];
            return $this->lastModifiedTime;
        }

        return null;
    }

    /**
     * @return bool|null
     */
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

    /**
     * @return bool|mixed
     */
    public function isReadable() {
        return $this->readable;
    }

    /**
     * @return bool|mixed
     */
    public function isWritable() {
        return $this->writable;
    }

    /**
     * @return bool
     */
    public function isSeekable() {
        return $this->seekable;
    }

    /**
     * @return bool
     */
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

    /**
     * @param int $length
     * @return bool|string
     */
    public function read($length) {
        return $this->readable ? fread($this->resource, $length) : false;
    }

    /**
     * @param string $buffer
     * @return int|null
     */
    public function write($buffer) {
        // We can't know the size after writing anything
        $this->detach();

        $type = gettype($buffer);

        if ($type == "string") {
            $stream = Stream::factory($buffer);
        } else {
            $stream = $buffer;
            $stream->seek(0);
        }

        $this->attach(GuzzleStreamWrapper::getResource($stream));

        $this->size = $stream->getSize();

        $this->prepare('Put', [
            'headers' => [
                "Content-Length" => $this->size,
                "Content-Type" => "application/octet-stream"
            ],
            'body' => $this->resource
        ]);

        $this->client->execute($this->command);

        return $stream->getSize();
    }

    /**
     * @param null $key
     * @return array|mixed|null
     */
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

    /**
     * @param null $cmdName
     * @throws Exception
     */
    private function prepare($cmdName = null, $params = []) {

        $options = self::getContextOption($this->node->getContext());
        $options = array_intersect_key($options, ["subscribers" => "", "auth" => ""]);

        if (!isset($this->httpClient)) {
            $this->httpClient = new Client($options);
        } else {
            foreach ($options as $key => $option) {
                $this->httpClient->setDefaultOption($key, $option);
            }
        }

        if (!isset($cmdName)) {
            return;
        }

        $params = array_merge([
            'path' => $this->node
        ], $params);

        $this->command = $this->client->getCommand($cmdName, $params);
    }

    /**
     * @return \GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
    private function ls() {

        $this->prepare('Ls');

        $result = $this->client->execute($this->command);

        return $result;
    }

    /**
     * @return \GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
    private function get() {

        $this->prepare('Get');

        $result = $this->client->execute($this->command);

        $this->detach();

        $this->attach(GuzzleStreamWrapper::getResource($result["body"]));

        return $result;
    }

    /**
     * @return \GuzzleHttp\Ring\Future\FutureInterface|mixed|null
     */
    public function stat() {

        $this->prepare('Stat');

        try {
            $result = $this->client->execute($this->command);
        } catch (Exception $e) {
            return null;
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function mkdir() {

        $this->prepare('Mkdir');

        $this->client->execute($this->command);

        return true;
    }

    /**
     * @return bool
     */
    public function rmdir() {

        $this->prepare('Rmdir');

        $this->client->execute($this->command);

        return true;
    }

    /**
     * @return bool
     */
    public function delete() {

        $this->prepare('Delete');

        $this->client->execute($this->command);

        return true;
    }

    /**
     * @param $newNode
     * @return bool
     */
    public function rename($newNode) {

        $this->prepare('Rename', [
            'path'    => $this->node,
            'newPath' => $newNode
        ]);

        $this->client->execute($this->command);

        return true;
    }
}

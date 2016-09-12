<?php
namespace Pydio\Access\Core\Stream;
use GuzzleHttp\Stream\StreamInterface;


/**
 * Converts Guzzle streams into PHP stream resources.
 */
class PydioStreamWrapper
{
    /** @var resource */
    public $context;

    /** @var StreamInterface */
    private $stream;

    /** @var string r, r+, or w */
    private $mode;

    /**
     * Returns a resource representing the stream.
     *
     * @param StreamInterface $stream The stream to get a resource for
     *
     * @return resource
     * @throws \InvalidArgumentException if stream is not readable or writable
     */
    public static function getResource(StreamInterface $stream)
    {
        self::register();

        if ($stream->isReadable()) {
            $mode = $stream->isWritable() ? 'r+' : 'r';
        } elseif ($stream->isWritable()) {
            $mode = 'w';
        } else {
            throw new \InvalidArgumentException('The stream must be readable, '
                . 'writable, or both.');
        }

        return fopen('pydiostreamwrapper://stream', $mode, null, stream_context_create([
            'pydiostreamwrapper' => ['stream' => $stream]
        ]));
    }

    /**
     * Registers the stream wrapper if needed
     */
    public static function register()
    {
        if (!in_array('pydiostreamwrapper', stream_get_wrappers())) {
            stream_wrapper_register('pydiostreamwrapper', __CLASS__);
        }
    }

    /**
     * @param $path
     * @param $mode
     * @param $options
     * @param $opened_path
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $options = stream_context_get_options($this->context);

        if (!isset($options['pydiostreamwrapper']['stream'])) {
            return false;
        }

        $this->mode = $mode;
        $this->stream = $options['pydiostreamwrapper']['stream'];

        return true;
    }

    /**
     * @param $count
     * @return string
     */
    public function stream_read($count)
    {
        $data = $this->stream->read($count);
        return $data;
    }

    /**
     * @param $data
     * @return int
     */
    public function stream_write($data)
    {
        return (int) $this->stream->write($data);
    }

    /**
     * @return bool|int
     */
    public function stream_tell()
    {
        return $this->stream->tell();
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->stream->eof();
    }

    /**
     * @param $offset
     * @param $whence
     * @return bool
     */
    public function stream_seek($offset, $whence)
    {
        return $this->stream->seek($offset, $whence);
    }

    /**
     * @return array|bool
     */
    public function stream_stat()
    {
        $isFile = $this->stream->isFile();

        if ($isFile  === TRUE) {
            $mode = 0100000;
            $mode |= 0777;
        } elseif ($isFile  === FALSE) {
            $mode = 00040777;
        } else {
            return false;
        }

        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $mode,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => $this->stream->getSize() ?: 0,
            'atime'   => 0,
            'mtime'   => $this->stream->getLastModifiedTime() ?: 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0
        ];
    }
}

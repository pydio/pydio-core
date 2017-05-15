<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Http\Response;


use Psr\Http\Message\StreamInterface;
use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Utils\XMLHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class SerializableResponseStream
 * Transport stream for various data types that can be serialized to various formats
 * @package Pydio\Core\Http\Response
 */
class SerializableResponseStream implements StreamInterface
{
    const SERIALIZER_TYPE_XML = 'xml';
    const SERIALIZER_TYPE_JSON = 'json';
    const SERIALIZER_TYPE_CLI = 'cli';

    /**
     * @var string
     */
    protected $serializer = self::SERIALIZER_TYPE_XML;

    /**
     * @var array Additional context variable depending on serializer
     */
    protected $serializerContext;
    /**
     * @var SerializableResponseChunk[]
     */
    protected $data = [];
    /**
     * @var string SerializedContent
     */
    protected $serializedContent;

    /**
     * @var bool
     */
    protected $forceArray = false;

    private $streamStatus = 'open';

    /**
     * SerializableResponseStream constructor.
     * @param SerializableResponseChunk[]|SerializableResponseChunk $chunks
     */
    public function __construct($chunks = [])
    {
        if(is_object($chunks) && $chunks instanceof SerializableResponseChunk){
            $chunks = [$chunks];
        }
        if(count($chunks)){
            $this->data = $chunks;
        }
    }

    /**
     * @param string $serializer SERIALIZER_TYPE_XML|SERIALIZER_TYPE_JSON|SERIALIZER_TYPE_CLI
     * @param array $context Additional data for serializer
     */
    public function setSerializer($serializer, $context = null){
        $this->serializer = $serializer;
        if($context !== null){
            $this->serializerContext = $context;
        }
    }

    /**
     * Set forceArray flag
     */
    public function forceArray(){
        $this->forceArray = true;
    }

    /**
     * Test if at least one chunk of data can be serialized using this format
     * @param $serializer
     * @return bool
     */
    public function supportsSerializer($serializer){
        // We can always JSON serialize output
        if($serializer === self::SERIALIZER_TYPE_JSON){
            return true;
        }
        foreach($this->data as $chunk){
            if($serializer === self::SERIALIZER_TYPE_XML && $chunk instanceof XMLSerializableResponseChunk){
                return true;
            }else if($serializer === self::SERIALIZER_TYPE_CLI && $chunk instanceof CLISerializableResponseChunk){
                return true;
            }
        }
        return false;
    }

    /**
     * @param SerializableResponseChunk $chunk
     */
    public function addChunk($chunk){
        array_push($this->data, $chunk);
    }

    /**
     * @return SerializableResponseChunk[]
     */
    public function getChunks(){
        return $this->data;
    }

    /**
     * @return string
     */
    public function getContents()
    {
        if(isSet($this->serializedContent)){
            return $this->serializedContent;
        }
        return $this->serializeData($this->data, $this->serializer);
    }


    /**
     * @param SerializableResponseChunk[] $data
     * @param string $serializer
     * @return string
     * @throws \RuntimeException
     */
    protected function serializeData($data, $serializer){

        if($serializer === self::SERIALIZER_TYPE_JSON){
            $buffer = [];
            foreach ($data as $serializableItem){
                if($serializableItem instanceof JSONSerializableResponseChunk){
                    $key = $serializableItem->jsonSerializableKey();
                    if($key != null){
                        $buffer[$key] = $serializableItem->jsonSerializableData();
                    }else{
                        $buffer[] = $serializableItem->jsonSerializableData();
                    }
                }else{
                    $buffer[] = $serializableItem;
                }
            }
            $pretty = 0;
            if(isSet($this->serializerContext) && isSet($this->serializerContext["pretty"]) && $this->serializerContext["pretty"] === true){
                $pretty = JSON_PRETTY_PRINT;
            }
            if(count($buffer) === 1 && !$this->forceArray) {
                $json = json_encode(array_shift($buffer), $pretty);
            }else {
                $json = json_encode($buffer, $pretty);
            }
            if($json === null){
                $msg = json_last_error_msg();
                $error = json_last_error();
                $message = new UserMessage($msg. " ($error)", LOG_LEVEL_ERROR);
                return json_encode($message->jsonSerializableData(), $pretty);
            }else{
                return $json;
            }
        }else if($serializer === self::SERIALIZER_TYPE_XML){
            $wrap = true;
            $buffer = "";
            $charset = null;
            /** @var XMLDocSerializableResponseChunk[] $xmlDocs */
            $xmlDocs = array_filter($data, function($serial){
                return $serial instanceof XMLDocSerializableResponseChunk;
            });
            if(count($xmlDocs)){
                $buffer = $xmlDocs[0]->toXML();
                $charset = $xmlDocs[0]->getCharset();
                $wrap = false;
            }else{
                foreach ($data as $serializableItem){
                    if(!$serializableItem instanceof XMLSerializableResponseChunk){
                        continue;
                    }
                    $buffer .= $serializableItem->toXML();
                }
            }

            if($wrap){
                $output = XMLHelper::wrapDocument($buffer);
            }else{
                if(substr($buffer, 0, 5) !== "<?xml"){
                    $buffer = "<?xml version=\"1.0\" encoding=\"".$charset."\"?>".$buffer;
                }
                $output = $buffer;
            }
            if(isSet($this->serializerContext) && isSet($this->serializerContext["pretty"]) && $this->serializerContext["pretty"] === true){
                // Rewrite Doc with pretty printing
                $doc = new \DOMDocument("1.0", $charset);
                $doc->loadXML($output);
                $doc->preserveWhiteSpace = false;
                $doc->formatOutput = true;
                $output = $doc->saveXML();
            }
            return $output;

        }else if($serializer === self::SERIALIZER_TYPE_CLI){
            $buffer = "";
            $output = $this->serializerContext["output"];
            foreach($data as $serializableItem){
                if($serializableItem instanceof CLISerializableResponseChunk){
                    $buffer .= $serializableItem->render($output);
                }else{
                    // Default to JSON
                    $buffer .= json_encode($serializableItem);
                }
            }

        }
        return "";
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        return $this->getContents();
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        $this->streamStatus = 'closed';
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        return null;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize()
    {
        if(!empty($this->data) || $this->forceArray){
            $this->serializedContent = $this->getContents();
            return strlen($this->serializedContent);
        }else{
            return 0;
        }
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell()
    {
        return -1;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof()
    {
        return false;
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable()
    {
        return false;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET){}

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind(){}

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable(){
        return true;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string){
        return strlen($string);
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable(){
        return true;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length){
        return '';
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null){
        return null;
    }
}
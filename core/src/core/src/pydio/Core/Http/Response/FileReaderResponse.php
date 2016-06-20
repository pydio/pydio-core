<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Http\Response;

use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessWrapper;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\StatHelper;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Core\Utils\Utils;
use Pydio\Log\Core\AJXP_Logger;
use Zend\Diactoros\ServerRequestFactory;

defined('AJXP_EXEC') or die('Access not allowed');


class FileReaderResponse extends AsyncResponseStream
{
    /** @var  AJXP_Node */
    private $node;

    /** @var  string */
    private $file;

    /** @var string */
    private $data;

    /** @var  string */
    private $localName = "";

    /** @var  string */
    private $headerType = "force-download";

    /** @var  integer */
    private $offset = -1;

    /** @var  integer */
    private $length = -1;

    /** @var  callable */
    private $preRead;

    /** @var  callable */
    private $postRead;

    /**
     * FileReaderResponse constructor.
     * @param AJXP_Node|string $nodeOrFile
     * @param string $data
     */
    public function __construct($nodeOrFile = null, $data = null)
    {
        $callable = array($this, "readData");
        if($nodeOrFile instanceof  AJXP_Node){
            $this->node = $nodeOrFile;
        }else{
            $this->file = $nodeOrFile;
        }
        if($data !== null){
            $this->data = $data;
        }
        parent::__construct($callable);
    }

    protected function logDebug($message, $params = []){
        AJXP_Logger::getInstance()->logDebug("FileReader", $message, $params);
    }

    public function setHeaderType($headerType){
        $this->headerType = $headerType;
    }

    public function setLocalName($localName){
        $this->localName = $localName;
    }

    /**
     * @param integer $offset
     * @param integer $length
     */
    public function setPartial($offset, $length){
        $this->offset = $offset;
        $this->length = $length;
    }

    public function setPreReadCallback(callable $pre){
        $this->preRead = $pre;
    }

    public function setPostReadCallback(callable $post){
        $this->postRead = $post;
    }

    protected function readData(){
        if($this->preRead){
            call_user_func($this->preRead);
        }

        $this->readFile($this->node, $this->file, $this->data, $this->headerType, $this->localName, $this->offset, $this->length);

        if($this->postRead){
            call_user_func($this->postRead);
        }
    }

    /**
     * @param AJXP_Node|null $node
     * @param string $filePath
     * @param string|bool $data
     * @param string $headerType
     * @param string $localName
     * @param int $byteOffset
     * @param int $byteLength
     * @throws \Exception
     */
    public function readFile($node = null, $filePath = null, $data = null, $headerType="plain", $localName="", $byteOffset=-1, $byteLength=-1)
    {
        if($node !== null){
            $filePathOrData = $node->getUrl();
        }else{
            $filePathOrData = $filePath;
        }

        if(!$data === null && !file_exists($filePathOrData)){
            throw new \Exception("File $filePathOrData not found!");
        }
        $confGzip               = ConfService::getCoreConf("GZIP_COMPRESSION");
        $confGzipLimit          = ConfService::getCoreConf("GZIP_LIMIT");
        $confUseXSendFile       = ConfService::getCoreConf("USE_XSENDFILE");
        $confUseXAccelRedirect  = ConfService::getCoreConf("USE_XACCELREDIRECT");
        $fakeReq = ServerRequestFactory::fromGlobals();
        $serverParams = $fakeReq->getServerParams();

        if ($node !== null  && !$node->wrapperIsRemote()) {
            $originalFilePath = $filePathOrData;
            $filePathOrData = fsAccessWrapper::patchPathForBaseDir($filePathOrData);
        }
        session_write_close();

        restore_error_handler();
        restore_exception_handler();

        set_exception_handler('Pydio\Access\Driver\StreamProvider\FS\download_exception_handler');
        set_error_handler('Pydio\Access\Driver\StreamProvider\FS\download_exception_handler');
        // required for IE, otherwise Content-disposition is ignored
        if (ini_get('zlib.output_compression')) {
            Utils::safeIniSet('zlib.output_compression', 'Off');
        }

        $isFile = ($data !== null) && !$confGzip;
        if ($byteLength == -1) {
            if ($data !== null) {
                $size = strlen($data);
            } else if ($node === null) {
                $size = sprintf("%u", filesize($filePathOrData));
            } else {
                $size = filesize($filePathOrData);
            }
        } else {
            $size = $byteLength;
        }
        if ($confGzip && ($size > $confGzipLimit || !function_exists("gzencode") || (isSet($serverParams['HTTP_ACCEPT_ENCODING']) && strpos($serverParams['HTTP_ACCEPT_ENCODING'], 'gzip') === FALSE))) {
            $confGzip = false;
        }

        $localName = ($localName =="" ? basename((isSet($originalFilePath)?$originalFilePath:$filePathOrData)) : $localName);

        if ($headerType == "plain") {

            header("Content-type:text/plain");
            header("Content-Length: ".$size);

        } else if ($headerType == "image") {

            header("Content-Type: ". StatHelper::getImageMimeType(basename($filePathOrData)) ."; name=\"".$localName."\"");
            header("Content-Length: ".$size);
            header('Cache-Control: public');

        } else {

            if ($isFile) {
                header("Accept-Ranges: 0-$size");
                $this->logDebug("Sending accept range 0-$size");
            }

            // Check if we have a range header (we are resuming a transfer)
            if ( isset($serverParams['HTTP_RANGE']) && $isFile && $size != 0 ) {

                if ($headerType == "stream_content") {

                    if (extension_loaded('fileinfo')  && (( $node !== null && !$node->wrapperIsRemote()) || $filePath !== null)) {
                        $fInfo = new \fInfo( FILEINFO_MIME );
                        if($node !== null){
                            $realfile = $node->getRealFile();
                        }else{
                            $realfile = $filePathOrData;
                        }
                        $mimeType = $fInfo->file($realfile);
                        $splitChar = explode(";", $mimeType);
                        $mimeType = trim($splitChar[0]);
                        $this->logDebug("Detected mime $mimeType for $realfile");
                    } else {
                        $mimeType = StatHelper::getStreamingMimeType(basename($filePathOrData));
                    }
                    header('Content-type: '.$mimeType);
                }
                // multiple ranges, which can become pretty complex, so ignore it for now
                $ranges = explode('=', $_SERVER['HTTP_RANGE']);
                $offsets = explode('-', $ranges[1]);
                $offset = floatval($offsets[0]);

                $length = floatval($offsets[1]) - $offset;
                if (!$length) $length = $size - $offset;
                if ($length + $offset > $size || $length < 0) $length = $size - $offset;
                $this->logDebug('Content-Range: bytes ' . $offset . '-' . $length . '/' . $size);
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $offset . '-' . ($offset + $length) . '/' . $size);

                header("Content-Length: ". $length);
                $file = fopen($filePathOrData, 'rb');
                if(!is_resource($file)){
                    throw new \Exception("Failed opening file ".$filePathOrData);
                }
                fseek($file, 0);
                $relOffset = $offset;
                while ($relOffset > 2.0E9) {
                    // seek to the requested offset, this is 0 if it's not a partial content request
                    fseek($file, 2000000000, SEEK_CUR);
                    $relOffset -= 2000000000;
                    // This works because we never overcome the PHP 32 bit limit
                }
                fseek($file, $relOffset, SEEK_CUR);

                while(ob_get_level()) ob_end_flush();
                $readSize = 0.0;
                $bufferSize = 1024 * 8;
                while (!feof($file) && $readSize < $length && connection_status() == 0) {
                    $this->logDebug("dl reading $readSize to $length", ["httpRange" => $serverParams["HTTP_RANGE"]]);
                    echo fread($file, $bufferSize);
                    $readSize += $bufferSize;
                    flush();
                }

                fclose($file);
                return;

            } else {

                if ($confGzip) {

                    $gzippedData = ($data?gzencode($filePathOrData,9):gzencode(file_get_contents($filePathOrData), 9));
                    $size = strlen($gzippedData);

                }

                HTMLWriter::emitAttachmentsHeaders($localName, $size, $isFile, $confGzip);

                if ($confGzip && isSet($gzippedData)) {
                    print $gzippedData;
                    return;
                }
            }
        }

        if ($data !== null) {

            print($data);

        } else {

            if(($node !== null && !$node->wrapperIsRemote()) || $filePath !== null){

                if ($confUseXSendFile) {
                    if($node != null) {
                        $filePathOrData = $node->getRealFile();
                    }
                    $filePathOrData = str_replace("\\", "/", $filePathOrData);
                    $server_name = $serverParams["SERVER_SOFTWARE"];
                    $regex = '/^(lighttpd\/1.4).([0-9]{2}$|[0-9]{3}$|[0-9]{4}$)+/';
                    if(preg_match($regex, $server_name))
                        $header_sendfile = "X-LIGHTTPD-send-file";
                    else
                        $header_sendfile = "X-Sendfile";


                    header($header_sendfile.": ".TextEncoder::toUTF8($filePathOrData));
                    header("Content-type: application/octet-stream");
                    header('Content-Disposition: attachment; filename="' . basename($filePathOrData) . '"');
                    return;
                }
                if ($confUseXAccelRedirect && array_key_exists("X-Accel-Mapping", $serverParams)) {
                    if($node !== null) {
                        $filePathOrData = $node->getRealFile();
                    }
                    $filePathOrData = str_replace("\\", "/", $filePathOrData);
                    $filePathOrData = TextEncoder::toUTF8($filePathOrData);
                    $mapping = explode('=',$serverParams['X-Accel-Mapping']);
                    $replacecount = 0;
                    $accelfile = str_replace($mapping[0],$mapping[1],$filePathOrData,$replacecount);
                    if ($replacecount == 1) {
                        header("X-Accel-Redirect: $accelfile");
                        header("Content-type: application/octet-stream");
                        header('Content-Disposition: attachment; filename="' . basename($accelfile) . '"');
                    } else {
                        $this->logDebug("X-Accel-Redirect: Problem with X-Accel-Mapping for file $filePathOrData");
                    }
                    return;
                }
            }

            $stream = fopen("php://output", "a");

            if ($node == null) {

                $this->logDebug("realFS!", array("file"=>$filePathOrData));
                $fp = fopen($filePathOrData, "rb");
                if(!is_resource($fp)){
                    throw new \Exception("Failed opening file ".$filePathOrData);
                }
                if ($byteOffset != -1) {
                    fseek($fp, $byteOffset);
                }
                $sentSize = 0;
                $readChunk = 4096;
                while (!feof($fp)) {
                    if ( $byteLength != -1 &&  ($sentSize + $readChunk) >= $byteLength) {
                        // compute last chunk and break after
                        $readChunk = $byteLength - $sentSize;
                        $break = true;
                    }
                    $data = fread($fp, $readChunk);
                    $dataSize = strlen($data);
                    fwrite($stream, $data, $dataSize);
                    $sentSize += $dataSize;
                    if (isSet($break)) {
                        break;
                    }
                }
                fclose($fp);
            } else {

                AJXP_MetaStreamWrapper::copyFileInStream($filePathOrData, $stream);

            }
            fflush($stream);
            fclose($stream);

        }
    }


}
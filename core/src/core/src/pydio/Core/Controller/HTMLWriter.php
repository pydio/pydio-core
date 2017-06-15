<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Core\Controller;

use Psr\Http\Message\ResponseInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Static functions for generating HTML.
 * @package Pydio
 * @subpackage Core
 */
class HTMLWriter
{

    /**
     * Replace the doc files keywords
     * @static
     * @param string $docFileName
     * @return string
     */
    public static function getDocFile($docFileName)
    {
        $realName = AJXP_DOCS_FOLDER."/".$docFileName.".txt";
        if (is_file($realName)) {
            $content = implode("<br>", file($realName));
            $content = preg_replace("(http:\/\/[a-z|.|\/|\-|0-9]*)", "<a target=\"_blank\" href=\"$0\">$0</a>", $content);
            $content = preg_replace("(\[(.*)\])", "<div class=\"title\">$1</div>", $content);
            $content = preg_replace("(\+\+ (.*) \+\+)", "<div class=\"subtitle\">$1</div>", $content);
            $content = str_replace("__AJXP_VERSION__", AJXP_VERSION, $content);
            $content = str_replace("__AJXP_VERSION_DATE__", AJXP_VERSION_DATE, $content);
            return $content;
        }
        return "File not found : ".$docFileName;
    }

    /**
     * Send a simple Content-type header
     * @static
     */
    public static function internetExplorerMainDocumentHeader(ResponseInterface &$response = null)
    {
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE 9.")) {
            if(isSet($response)){
                $response = $response->withHeader("X-UA-Compatible", "IE=9");
            }else{
                header("X-UA-Compatible: IE=9");
            }
        } else if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE 10.")) {
            if(isSet($response)){
                $response = $response->withHeader("X-UA-Compatible", "IE=Edge, chrome=1");
            }else{
                header("X-UA-Compatible: IE=Edge,chrome=1");
            }
        }
        if(isSet($response)){
            $response = $response->withHeader("Content-type", "text/html; charset=utf-8");
        }
    }

    /**
     * Send a simple Content-type header
     * @static
     * @param string $type
     * @param string $charset
     * @return void
     */
    public static function charsetHeader($type = 'text/html', $charset='UTF-8')
    {
        header("Content-type:$type; charset=$charset");
    }

    /**
     * Write directly an error as a javascript instruction
     * @static
     * @param $errorType
     * @param $errorMessage
     */
    public static function javascriptErrorHandler($errorType, $errorMessage)
    {
        // Handle "@" case!
        if(error_reporting() == 0) return ;
        restore_error_handler();
        die("<script language='javascript'>parent.ajaxplorer.displayMessage('ERROR', '".str_replace("'", "\'", $errorMessage)."');</script>");
    }


    /**
     * @param ResponseInterface $response
     * @param $attachName
     * @param $fileSize
     * @param $mimeType
     * @return ResponseInterface
     */
    public static function responseWithInlineHeaders(ResponseInterface $response, $attachName, $fileSize, $mimeType){
        return self::generateInlineHeaders($attachName, $fileSize, $mimeType, $response);
    }

    /**
     * @param ResponseInterface $response
     * @param $attachmentName
     * @param $dataSize
     * @param bool $isFile
     * @param bool $gzip
     * @return null|ResponseInterface
     */
    public static function responseWithAttachmentsHeaders(ResponseInterface $response, &$attachmentName, $dataSize, $isFile=true, $gzip=false){
        return self::generateAttachmentsHeader($attachmentName, $dataSize, $isFile, $gzip, $response);
    }


    /**
     * @param $attachName
     * @param $fileSize
     * @param $mimeType
     */
    public static function emitInlineHeaders($attachName, $fileSize, $mimeType){
        self::generateInlineHeaders($attachName, $fileSize, $mimeType);
    }

    /**
     * @param $attachmentName
     * @param $dataSize
     * @param bool $isFile
     * @param bool $gzip
     */
    public static function emitAttachmentsHeaders(&$attachmentName, $dataSize, $isFile=true, $gzip=false){
        self::generateAttachmentsHeader($attachmentName, $dataSize, $isFile, $gzip);
    }



    /**
     * Correctly encode name for attachment header
     * @param string $name
     * @return string
     */
    private static function encodeAttachmentName($name){
        if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])
            || preg_match('/ WebKit /',$_SERVER['HTTP_USER_AGENT'])
            || preg_match('/ Trident/',$_SERVER['HTTP_USER_AGENT'])) {
            $name = str_replace("+", " ", urlencode(TextEncoder::toUTF8($name)));
        }
        return $name;
    }

    /**
     * @static
     * @param string $attachmentName
     * @param int $dataSize
     * @param bool $isFile
     * @param bool $gzip If true, make sure the $dataSize is the size of the ENCODED data.
     * @param ResponseInterface $response Response to update instead of generating headers
     * @return ResponseInterface|null Update response interface if passed by argument
     */
    private static function generateAttachmentsHeader(&$attachmentName, $dataSize, $isFile=true, $gzip=false, ResponseInterface $response = null)
    {
        $attachmentName = self::encodeAttachmentName($attachmentName);

        $headers = [];
        $headers["Content-Type"] = "application/force-download; name=\"".$attachmentName."\"";
        $headers["Content-Transfer-Encoding"] = "binary";
        if ($gzip) {
            $headers["Content-Encoding"]  = "gzip";
        }
        $headers["Content-Length"] = $dataSize;
        if ($isFile && ($dataSize != 0)) {
            $headers["Content-Range"] = "bytes 0-" . ($dataSize- 1) . "/" . $dataSize . ";";
        }
        $headers["Content-Disposition"] = "attachment; filename=\"".$attachmentName."\"";
        $headers["Expires"] = "0";
        $headers["Cache-Control"] = "no-cache, must-revalidate";
        $headers["Pragma"] = "no-cache";

        if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
            $headers["Pragma"] = "public";
            $headers["Cache-Control"] = "max_age=0";
        }

        // IE8 is dumb
        if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
            $headers["Pragma"] = "public";
            $headers["Cache-Control"] = "private, must-revalidate, post-check=0, pre-check=0";
        }

        // For SSL websites there is a bug with IE see article KB 323308
        // therefore we must reset the Cache-Control and Pragma Header
        if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
            $headers["Pragma"] = "";
            $headers["Cache-Control"] = "";
        }

        foreach($headers as $headerName => $headerValue){
            if($response !== null){
                $response = $response->withHeader($headerName, (string) $headerValue);
            }else{
                header($headerName.": ".$headerValue);
            }
        }

        return $response;
    }

    /**
     * Generate correct headers
     * @param string $attachName
     * @param int $fileSize
     * @param string $mimeType
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private static function generateInlineHeaders($attachName, $fileSize, $mimeType, ResponseInterface $response = null)
    {
        $attachName = self::encodeAttachmentName($attachName);

        $headers = [];
        $headers["Content-Type"] =  $mimeType . "; name=\"" . $attachName . "\"";
        $headers["Content-Disposition"] = "inline; filename=\"" . $attachName . "\"";
        // changed header for IE 7 & 8
        if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
            $headers["Expires"] = "0";
            $headers["Pragma"] = "public";
            $headers["Cache-Control"] = "private, must-revalidate, post-check=0, pre-check=0";
        } else {
            $headers["Cache-Control"] = "public";
        }
        $headers["Content-Length"] = $fileSize;

        // Neccessary for IE 8 and xx
        if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
            $headers["Pragma"] = "";
            $headers["Cache-Control"] = "";
        }

        foreach($headers as $headerName => $headerValue){
            if($response !== null){
                $response = $response->withHeader($headerName, (string) $headerValue);
            }else{
                header($headerName.": ".$headerValue);
            }
        }

        return $response;

    }

}

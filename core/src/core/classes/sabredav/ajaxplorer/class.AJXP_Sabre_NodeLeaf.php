<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer
 * @subpackage SabreDav
 */
class AJXP_Sabre_NodeLeaf extends AJXP_Sabre_Node implements Sabre\DAV\IFile
{

    /**
     * Updates the data
     *
     * The data argument is a readable stream resource.
     *
     * After a succesful put operation, you may choose to return an ETag. The
     * etag must always be surrounded by double-quotes. These quotes must
     * appear in the actual string you're returning.
     *
     * Clients may use the ETag from a PUT request to later on make sure that
     * when they update the file, the contents haven't changed in the mean
     * time.
     *
     * If you don't plan to store the file byte-by-byte, and you return a
     * different object on a subsequent GET you are strongly recommended to not
     * return an ETag, and just return null.
     *
     * @param resource $data
     * @return string|null
     */
    public function put($data)
    {
        // Warning, passed by ref
        $p = $this->path;

        if (!AuthService::getLoggedUser()->canWrite($this->repository->getId())) {
            throw new \Sabre\DAV\Exception\Forbidden() ;
        }
        $this->getAccessDriver()->nodeWillChange($p, intval($_SERVER["CONTENT_LENGTH"]));

        $stream = fopen($this->getUrl(), "w");
        stream_copy_to_stream($data, $stream);
        fclose($stream);

        $toto = null;
        $this->getAccessDriver()->nodeChanged($toto, $p);

        return $this->getETag();
    }

    /**
     * Returns the data
     *
     * This method may either return a string or a readable stream resource
     *
     * @return mixed
     */
    public function get()
    {
        $ajxpNode = new AJXP_Node($this->getUrl());
        AJXP_Controller::applyHook("node.read", array(&$ajxpNode));
        return fopen($this->getUrl(), "r");
    }

    /**
     * Returns the mime-type for a file
     *
     * If null is returned, we'll assume application/octet-stream
     *
     * @return void|string
     */
    public function getContentType()
    {
         //Get mimetype with fileinfo PECL extension
        $fp = fopen($this->getUrl(), "r");
        $fileMime = null;
        if (class_exists("finfo")) {
            $finfo = new finfo(FILEINFO_MIME);
            $fileMime = $finfo->buffer(fread($fp, 100));
        } elseif (function_exists("mime_content_type")) {
            $fileMime = @mime_content_type($fp);
        } else {
            $fileExt = substr(strrchr(basename($this->getUrl()), '.'), 1);
            if(empty($fileExt))
                $fileMime = "application/octet-stream";
            else {
                $regex = "/^([\w\+\-\.\/]+)\s+(\w+\s)*($fileExt\s)/i";
                $lines = file( AJXP_CONF_PATH ."/mime.types");
                foreach ($lines as $line) {
                    if(substr($line, 0, 1) == '#')
                        continue; // skip comments
                    $line = rtrim($line) . " ";
                    if(!preg_match($regex, $line, $matches))
                        continue; // no match to the extension
                    $fileMime = $matches[1];
                }
            }
        }
        fclose($fp);
        return $fileMime;
        /*
        if ( $this->options->useMimeExts && ezcBaseFeatures::hasExtensionSupport( 'fileinfo' ) ) {
            $fInfo = new fInfo( FILEINFO_MIME );
            $mimeType = $fInfo->file( $this->getUrl() );

            // The documentation tells to do this, but it does not work with a
            // current version of pecl/fileinfo
            // $fInfo->close();

            return $mimeType;
        }

        // Check if extension ext/mime-magic is usable.
        if ( $this->options->useMimeExts &&
            ezcBaseFeatures::hasExtensionSupport( 'mime_magic' ) &&
            ( $mimeType = mime_content_type( $this->getUrl() ) ) !== false )
        {
            return $mimeType;
        }
        */
    }

    /**
     * Returns the ETag for a file
     *
     * An ETag is a unique identifier representing the current version of the file. If the file changes, the ETag MUST change.
     *
     * Return null if the ETag can not effectively be determined
     *
     * @return String|null
     */
    public function getETag()
    {
        clearstatcache();
        return '"'.md5(
            $this->path
                . $this->getSize()
                . date( 'c', $this->getLastModified() )
        ).'"';
    }

    /**
     * Returns the size of the node, in bytes
     *
     * @return int
     */
    public function getSize()
    {
        return filesize($this->getUrl());
    }




}

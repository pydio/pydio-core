<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 */
class AJXP_Sabre_NodeLeaf extends AJXP_Sabre_Node implements Sabre_DAV_IFile
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
    function put($data){

        // Warning, passed by ref
        $p = $this->path;

        $this->accessDriver->nodeWillChange($p, intval($_SERVER["CONTENT_LENGTH"]));

        $stream = fopen($this->url, "w");
        stream_copy_to_stream($data, $stream);
        fclose($stream);

        $toto = null;
        $this->accessDriver->nodeChanged($toto, $p);

        return $this->getETag();
    }

    /**
     * Returns the data
     *
     * This method may either return a string or a readable stream resource
     *
     * @return mixed
     */
    function get(){
        return fopen($this->url, "r");
    }

    /**
     * Returns the mime-type for a file
     *
     * If null is returned, we'll assume application/octet-stream
     *
     * @return void
     */
    function getContentType(){
        /*
        if ( $this->options->useMimeExts && ezcBaseFeatures::hasExtensionSupport( 'fileinfo' ) )
        {
            $fInfo = new fInfo( FILEINFO_MIME );
            $mimeType = $fInfo->file( $this->url );

            // The documentation tells to do this, but it does not work with a
            // current version of pecl/fileinfo
            // $fInfo->close();

            return $mimeType;
        }

        // Check if extension ext/mime-magic is usable.
        if ( $this->options->useMimeExts &&
            ezcBaseFeatures::hasExtensionSupport( 'mime_magic' ) &&
            ( $mimeType = mime_content_type( $this->url ) ) !== false )
        {
            return $mimeType;
        }
        */
        return null;
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
    function getETag(){
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
    function getSize(){
        return filesize($this->url);
    }




}

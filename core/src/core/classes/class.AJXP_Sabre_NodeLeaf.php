<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 02/08/12
 * Time: 18:04
 * To change this template use File | Settings | File Templates.
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

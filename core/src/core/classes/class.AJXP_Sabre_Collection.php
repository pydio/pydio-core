<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 02/08/12
 * Time: 17:36
 * To change this template use File | Settings | File Templates.
 */
class AJXP_Sabre_Collection extends AJXP_Sabre_Node implements Sabre_DAV_ICollection
{

    protected $children;

    /**
     * Creates a new file in the directory
     *
     * Data will either be supplied as a stream resource, or in certain cases
     * as a string. Keep in mind that you may have to support either.
     *
     * After succesful creation of the file, you may choose to return the ETag
     * of the new file here.
     *
     * The returned ETag must be surrounded by double-quotes (The quotes should
     * be part of the actual string).
     *
     * If you cannot accurately determine the ETag, you should not return it.
     * If you don't store the file exactly as-is (you're transforming it
     * somehow) you should also not return an ETag.
     *
     * This means that if a subsequent GET to this new file does not exactly
     * return the same contents of what was submitted here, you are strongly
     * recommended to omit the ETag.
     *
     * @param string $name Name of the file
     * @param resource|string $data Initial payload
     * @return null|string
     */
    function createFile($name, $data = null){

        try{
            $name = ltrim($name, "/");
            AJXP_Logger::debug("CREATE FILE $name");

            AJXP_Controller::findActionAndApply("mkfile", array(
                "dir" => $this->path,
                "filename" => $name
            ), array());

            if( $data != null && is_file($this->url."/".$name)){

                $p = $this->path."/".$name;
                $this->accessDriver->nodeWillChange($p, intval($_SERVER["CONTENT_LENGTH"]));
                AJXP_Logger::debug("Should now copy stream or string in ".$this->url."/".$name);
                if(is_resource($data)){
                    $stream = fopen($this->url."/".$name, "w");
                    stream_copy_to_stream($data, $stream);
                    fclose($stream);
                }else if(is_string($data)){
                    file_put_contents($data, $this->url."/".$name);
                }

                $toto = null;
                $this->accessDriver->nodeChanged($toto, $p);

            }
            $node = new AJXP_Sabre_NodeLeaf($this->path."/".$name, $this->repository, $this->accessDriver);
            if(isSet($this->children)){
                $this->children = null;
            }
            return $node->getETag();

        }catch (Exception $e){
            AJXP_Logger::debug("Error ".$e->getMessage(), $e->getTraceAsString());
            return null;
        }


    }

    /**
     * Creates a new subdirectory
     *
     * @param string $name
     * @return void
     */
    function createDirectory($name){

        if(isSet($this->children)){
            $this->children = null;
        }

        AJXP_Controller::findActionAndApply("mkdir", array(
            "dir" => $this->path,
            "dirname" => $name
        ), array());

    }

    /**
     * Returns a specific child node, referenced by its name
     *
     * @param string $name
     * @return Sabre_DAV_INode
     */
    function getChild($name){

        foreach($this->getChildren() as $child) {

            if ($child->getName()==$name) return $child;

        }
        throw new Sabre_DAV_Exception_NotFound('File not found: ' . $name);

    }

    /**
     * Returns an array with all the child nodes
     *
     * @return Sabre_DAV_INode[]
     */
    function getChildren(){

        if(isSet($this->children)) {
            return $this->children;
        }


        $contents = array();
        $errors = array();

        $nodes = scandir($this->url);

        foreach ( $nodes as $file )
        {
            if($file == "." || $file == "..") {
                continue;
            }
            if ( !$this->repository->getOption("SHOW_HIDDEN_FILES") && AJXP_Utils::isHidden($file)){
                continue;
            }
            if ( is_dir( $this->url . "/" . $file ) )
            {
                // Add collection without any children
                $contents[] = new AJXP_Sabre_Collection($this->path."/".$file, $this->repository, $this->accessDriver);
            }
            else
            {
                // Add files without content
                $contents[] = new AJXP_Sabre_NodeLeaf($this->path."/".$file, $this->repository, $this->accessDriver);
            }
        }
        $this->children = $contents;
        return $contents;

    }

    /**
     * Checks if a child-node with the specified name exists
     *
     * @param string $name
     * @return bool
     */
    function childExists($name){

        foreach($this->getChildren() as $child) {

            if ($child->getName()==$name) return true;

        }
        return false;

    }
}

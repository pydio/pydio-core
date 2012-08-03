<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 02/08/12
 * Time: 17:37
 * To change this template use File | Settings | File Templates.
 */
class AJXP_Sabre_Node implements Sabre_DAV_INode/*, Sabre_DAV_IProperties*/
{

    /**
     * @var Repository
     */
    protected $repository;
    /**
     * @var AjxpWebdavProvider
     */
    protected $accessDriver;

    /**
     * @var String
     */
    protected $url;
    protected $path;

    function __construct($path, $repository, $accessDriver = null){
        $this->repository = $repository;
        if($accessDriver == null){
            ConfService::switchRootDir($repository->getUniqueId());
            ConfService::getConfStorageImpl();
            $this->accessDriver = ConfService::loadRepositoryDriver();
            if(!$this->accessDriver instanceof AjxpWebdavProvider){
                throw new ezcBaseFileNotFoundException( $this->repository->getUniqueId() );
            }
            $this->accessDriver->detectStreamWrapper(true);
        }else{
            $this->accessDriver = $accessDriver;
        }
        $this->path = $path;
        $this->url = $this->accessDriver->getRessourceUrl($path);
    }

    /**
     * Deleted the current node
     *
     * @return void
     */
    function delete(){



        AJXP_Logger::debug("Delete? ".$this->path);
        ob_start();
        try{
            AJXP_Controller::findActionAndApply("delete", array(
                "dir"       => dirname($this->path),
                "file_0"    => $this->path
            ), array());
        }catch(Exception $e){

        }
        $result = ob_get_flush();
        AJXP_Logger::debug("RESULT : ".$result);

    }

    /**
     * Returns the name of the node.
     *
     * This is used to generate the url.
     *
     * @return string
     */
    function getName(){
        return basename($this->url);
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    function setName($name){
        AJXP_Controller::findActionAndApply("rename", array(
            "filename_new"      => $name,
            "dir"               => dirname($this->path),
            "file"              => $this->path
        ), array());

    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified(){
        return filemtime($this->url);
    }


    /**
     * Updates properties on this node,
     *
     * The properties array uses the propertyName in clark-notation as key,
     * and the array value for the property value. In the case a property
     * should be deleted, the property value will be null.
     *
     * This method must be atomic. If one property cannot be changed, the
     * entire operation must fail.
     *
     * If the operation was successful, true can be returned.
     * If the operation failed, false can be returned.
     *
     * Deletion of a non-existent property is always successful.
     *
     * Lastly, it is optional to return detailed information about any
     * failures. In this case an array should be returned with the following
     * structure:
     *
     * array(
     *   403 => array(
     *      '{DAV:}displayname' => null,
     *   ),
     *   424 => array(
     *      '{DAV:}owner' => null,
     *   )
     * )
     *
     * In this example it was forbidden to update {DAV:}displayname.
     * (403 Forbidden), which in turn also caused {DAV:}owner to fail
     * (424 Failed Dependency) because the request needs to be atomic.
     *
     * @param array $mutations
     * @return bool|array
     */
    /*
    function updateProperties($mutations){

        AJXP_Logger::debug("UPDATE PROPERTIES", $mutations);
        return true;
    }
    */

    /**
     * Returns a list of properties for this nodes.
     *
     * The properties list is a list of propertynames the client requested,
     * encoded in clark-notation {xmlnamespace}tagname
     *
     * If the array is empty, it means 'all properties' were requested.
     *
     * @param array $properties
     * @return void
     */
    /*
    function getProperties($properties){
        AJXP_Logger::debug("GET PROPERTIES", $properties);
        return array();
    }
    */
}

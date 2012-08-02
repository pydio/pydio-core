<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 02/08/12
 * Time: 17:37
 * To change this template use File | Settings | File Templates.
 */
class AJXP_Sabre_Node implements Sabre_DAV_INode
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
}

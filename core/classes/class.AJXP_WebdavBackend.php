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
/**
 * AjaXplorer implementation of the EZC WebDAV backend library
 */
class AJXP_WebdavBackend extends ezcWebdavSimpleBackend implements ezcWebdavLockBackend {
	
	/**
	 * @var Repository
	 */
	protected $repository;
	/**
	 * @param AjxpWebdavProvider
	 */
	protected $accessDriver;
	/**
	 * @var string
	 */
	protected $wrapperClassName;

   /**
    * Keeps track of the lock level.
    *
    * Each time the lock() method is called, this counter is raised by 1. if
    * it was 0 before, the actual locking mechanism gets into action,
    * otherwise just the counter is raised. The lock is physically only freed,
    * if this counter is 0.
    *
    * This mechanism allows nested locking, as it is necessary, if the lock
    * plugin locks this backend external, but interal locking needs still to
    * be supported.
    *
    * @var int
    */
    protected $lockLevel = 0;

    protected $handledLiveProperties = array( 
        'getcontentlength', 
        'getlastmodified', 
        'creationdate', 
        'displayname', 
        'getetag', 
        'getcontenttype', 
        'resourcetype',
        'supportedlock',
        'lockdiscovery',
    );	
    
    protected $statCache = array();

	public function __construct($repository){
		$repositoryId = ($repository->isWriteable()?$repository->getUniqueId():$repository->getId());
		ConfService::switchRootDir($repositoryId);
		$this->repository = ConfService::getRepository();
						
		$this->options = new ezcWebdavFileBackendOptions();
        $this->options['noLock']                 = false;
        $this->options['waitForLock']            = 200000;
        $this->options['lockTimeout']            = 2;
        $this->options['lockFileName']           = '.ezc_lock';
        $this->options['propertyStoragePath']    = '.ezc';
        $this->options['directoryMode']          = 0755;
        $this->options['fileMode']               = 0644;
        $this->options['useMimeExts']            = false;
        $this->options['hideDotFiles']           = true;
		
	}

    /**
     * @return AjxpWebdavProvider
     * @throws ezcBaseFileNotFoundException
     */
	protected function getAccessDriver(){
		if(!isset($this->accessDriver)){
            $confDriver = ConfService::getConfStorageImpl();
            $this->accessDriver = ConfService::loadRepositoryDriver();
            if(!$this->accessDriver instanceof AjxpWebdavProvider){
                throw new ezcBaseFileNotFoundException( $this->repository->getUniqueId() );
            }
            $wrapperData = $this->accessDriver->detectStreamWrapper(true);
            $this->wrapperClassName = $wrapperData["classname"];
		}
		return $this->accessDriver;
	}
		
	protected function fixPath($path){
		if ($path == "\\") $path = "";
		//AJXP_Logger::debug("fixPath called ".$path);
		/*
		$bt = debug_backtrace();
		$calls = array();
		foreach($bt as $trace){
			$calls[] = $trace["file"]."::".$trace["function"];
		}
		AJXP_Logger::debug("fixPath : $path => ".$calls["1"]);
		*/
		if(strstr($path, "%")){
			$path = urldecode($path);
		}
		$path = SystemTextEncoding::fromUTF8($path, true);
		return $path;
	}
	
    /**
     * Create a new collection.
     *
     * Creates a new collection at the given $path.
     * 
     * @param string $path 
     * @return void
     */
     protected function createCollection( $path ){
     	 $path = $this->fixPath($path);
     	 //$this->getAccessDriver()->mkDir($this->safeDirname($path), $this->safeBasename($path));
         AJXP_Controller::findActionAndApply("mkdir", array(
             "dir" => $this->safeDirname($path),
             "dirname" => $this->safeBasename($path)
         ), array());
     }

    /**
     * Create a new resource.
     *
     * Creates a new resource at the given $path, optionally with the given
     * $content.
     * 
     * @param string $path 
     * @param string $content 
     * @return void
     */
    protected function createResource( $path, $content = null ){
    	$path = $this->fixPath($path);
    	//AJXP_Logger::debug("AJXP_WebdavBackend :: createResource ($path)");
    	//$this->getAccessDriver()->createEmptyFile($this->safeDirname($path), $this->safeBasename($path));
        $params = array(
            "dir" => $this->safeDirname($path),
            "filename" => $this->safeBasename($path)
        );
        if($content != null) $params["content"] = $content;
        AJXP_Controller::findActionAndApply("mkfile", $params, array());
    }

    /**
     * Changes contents of a resource.
     *
     * This method is used to change the contents of the resource identified by
     * $path to the given $content.
     * 
     * @param string $path 
     * @param string $content 
     * @return void
     */
    protected function setResourceContents( $path, $content ){
        $path = $this->fixPath($path);
        AJXP_Logger::debug("AJXP_WebdavBackend :: putResourceContent ($path)");
        $this->getAccessDriver()->nodeWillChange($path, intval($_SERVER["CONTENT_LENGTH"]));

        $fp=fopen($this->getAccessDriver()->getRessourceUrl($path),"w");
		$in = fopen( 'php://input', 'r' );
        while ( $data = fread( $in, 1024 ) )
        {
            fputs($fp, $data, strlen($data));
        }
        fclose($in);
        fclose($fp);
    	$toto = null;
        $this->getAccessDriver()->nodeChanged($toto, $path);
    }

    /**
     * Returns the content of a resource.
     *
     * Returns the content of the resource identified by $path.
     * 
     * @param string $path 
     * @return string
     */
     protected function getResourceContents( $path ){
     	$path = $this->fixPath($path);
     	AJXP_Logger::debug("AJXP_WebdavBackend :: getResourceContent ($path)");
     	$wrapperClassName = $this->getAccessDriver()->getWrapperClassName();
     	$tmp = call_user_func(array($wrapperClassName, "getRealFSReference"), $this->getAccessDriver()->getRessourceUrl($path));
     	if(call_user_func(array($wrapperClassName, "isRemote"))){
     		register_shutdown_function("unlink", $tmp);
     	}
     	return file_get_contents($tmp);
     }

    /**
     * @return MetaStoreProvider|bool
     */
    protected function getMetastore(){
        $metaStore = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($metaStore === false) return false;
        $metaStore->initMeta($this->getAccessDriver());
        return $metaStore;
    }

    /**
     * Manually sets a property on a resource.
     *
     * Sets the given $propertyBackup for the resource identified by $path.
     * 
     * @param string $path 
     * @param ezcWebdavProperty $property
     * @return bool
     */
    public function setProperty( $path, ezcWebdavProperty $property ){
        if($property->name == "lockdiscovery" || $property->namespace != "DAV:"){
            $metaStore = $this->getMetastore();
            if($metaStore == false) return true;
            $node = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($this->fixPath($path)));
            $existingMeta = $metaStore->retrieveMetadata($node, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
            if(is_array($existingMeta)){
                $existingMeta[$property->name] = base64_encode(serialize($property));
            }else{
                $existingMeta = array($property->name => base64_encode(serialize($property)));
            }
            $metaStore->setMetadata(
                $node,
                "ezcWEBDAV",
                $existingMeta,
                false,
                AJXP_METADATA_SCOPE_GLOBAL
            );
            AJXP_Logger::debug("DAVLOCK Saved property".$property->name);
        }
		return true;
    }

    /**
     * Manually removes a property from a resource.
     *
     * Removes the given $property form the resource identified by $path.
     * 
     * @param string $path 
     * @param ezcWebdavProperty $property
     * @return bool
     */
    public function removeProperty( $path, ezcWebdavProperty $property ){
        if($property->name == "lockdiscovery" || $property->namespace != "DAV:"){
            AJXP_Logger::debug("DAVLOCK Clearing property ? ".$property->name);
            $metaStore = $this->getMetastore();
            if($metaStore == false) {
                AJXP_Logger::debug("DAVLOCK > no store");
                return true;
            }
            $node = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($this->fixPath($path)));
            $existingMeta = $metaStore->retrieveMetadata($node, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
            if(!is_array($existingMeta) || !isSet($existingMeta[$property->name])) {
                AJXP_Logger::debug("DAVLOCK > not found for ".$node->getUrl());
                return true;
            }
            unset($existingMeta[$property->name]);
            $metaStore->setMetadata($node, "ezcWEBDAV",$existingMeta,false,AJXP_METADATA_SCOPE_GLOBAL);
            AJXP_Logger::debug("DAVLOCK Cleared property ".$property->name);
        }
    	return true;
    }

    /**
     * Resets the property storage for a resource.
     *
     * Discardes the current {@link ezcWebdavPropertyStorage} of the resource
     * identified by $path and replaces it with the given $properties.
     * 
     * @param string $path 
     * @param ezcWebdavPropertyStorage $properties
     * @return bool
     */
    public function resetProperties( $path, ezcWebdavPropertyStorage $properties ){
        $metaStore = $this->getMetastore();
        if($metaStore == false) return true;
        $metaStore->removeMetadata(
            new AJXP_Node($this->getAccessDriver()->getRessourceUrl($this->fixPath($path))),
            "ezcWEBDAV",
            false,
            AJXP_METADATA_SCOPE_GLOBAL
        );
        AJXP_Logger::debug("DAVLOCK Clearing properties");
        if($properties != null){
            foreach($properties->getAllProperties() as $pName => $property){
                $this->setProperty($path, $property);
            }
        }
		return true;
    }

    /**
     * Returns a property of a resource.
     * 
     * Returns the property with the given $propertyName, from the resource
     * identified by $path. You may optionally define a $namespace to receive
     * the property from.
     *
     * @param string $path 
     * @param string $propertyName 
     * @param string $namespace 
     * @return ezcWebdavProperty
     */
    public function getProperty( $path, $propertyName, $namespace = 'DAV:' ){
    	$path = $this->fixPath($path);
    	$url = $this->getAccessDriver()->getRessourceUrl($path);
        $storage = $this->getPropertyStorage( $path );

        // Handle dead propreties
        if ( $namespace !== 'DAV:' )
        {
            $metaStore = $this->getMetastore();
            if($metaStore == false) return true;
            $node = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($this->fixPath($path)));
            $existingMeta = $metaStore->retrieveMetadata($node, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
            if(is_array($existingMeta) && isSet($existingMeta[$propertyName])) {
                return unserialize(base64_decode($existingMeta[$propertyName]));
            }
            $properties = $storage->getAllProperties();
            return $properties[$namespace][$propertyName];
        }

        // Handle live properties
        switch ( $propertyName )
        {
            case 'getcontentlength':
                $property = new ezcWebdavGetContentLengthProperty();
                $property->length = $this->getContentLength($path);
                return $property;

            case 'getlastmodified':
            	$property = new ezcWebdavGetLastModifiedProperty();
            	$property->date = new ezcWebdavDateTime( '@' . filemtime( $url ) );
                return $property;

            case 'creationdate':
                $property = new ezcWebdavCreationDateProperty();
                $property->date = new ezcWebdavDateTime( '@' . filectime( $url ) );
                return $property;

            case 'displayname':
                $property = new ezcWebdavDisplayNameProperty();
                //$property->displayName = urldecode( $this->safeBasename( $path ) );
                $property->displayName = SystemTextEncoding::toUTF8( urldecode( $this->safeBasename( $path )), true);
                return $property;

            case 'getcontenttype':
                $property = new ezcWebdavGetContentTypeProperty(
                    $this->getMimeType( $path )
                );
                return $property;

            case 'getetag':
                $property = new ezcWebdavGetEtagProperty();
                $property->etag = $this->getETag( $path );
                return $property;

            case 'resourcetype':
                $property = new ezcWebdavResourceTypeProperty();
                $property->type = $this->isCollection( $path ) ?
                    ezcWebdavResourceTypeProperty::TYPE_COLLECTION : 
                    ezcWebdavResourceTypeProperty::TYPE_RESOURCE;
                return $property;

            case 'supportedlock':
                $property = new ezcWebdavSupportedLockProperty();
                return $property;

            case 'lockdiscovery':
                $metaStore = $this->getMetastore();
                $property = new ezcWebdavLockDiscoveryProperty();
                if($metaStore == false){
                    return $property;
                }
                $metadata = $metaStore->retrieveMetadata(
                    new AJXP_Node($this->getAccessDriver()->getRessourceUrl($this->fixPath($path))),
                    "ezcWEBDAV",
                    false,
                    AJXP_METADATA_SCOPE_GLOBAL
                );
                if(isSet($metadata["lockdiscovery"])){
                    $property = unserialize(base64_decode($metadata["lockdiscovery"]));
                    AJXP_Logger::debug("DAVLOCK Found Property : ".$propertyName." for url ".$url);
                }
                return $property;

            default:
                $metaStore = $this->getMetastore();
                if($metaStore == false) return true;
                $node = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($this->fixPath($path)));
                $existingMeta = $metaStore->retrieveMetadata($node, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
                if(is_array($existingMeta) && isSet($existingMeta[$propertyName])) {
                    return unserialize(base64_decode($existingMeta[$propertyName]));
                }
                // Handle all other live properties like dead properties
                $properties = $storage->getAllProperties();
                return $properties[$namespace][$propertyName];
        }
    	
    }

    /**
     * Returns the etag representing the current state of $path.
     * 
     * Calculates and returns the ETag for the resource represented by $path.
     * The ETag is calculated from the $path itself and the following
     * properties, which are concatenated and md5 hashed:
     *
     * <ul>
     *  <li>getcontentlength</li>
     *  <li>getlastmodified</li>
     * </ul>
     *
     * This method can be overwritten in custom backend implementations to
     * access the information needed directly without using the way around
     * properties.
     *
     * Custom backend implementations are encouraged to use the same mechanism
     * (or this method itself) to determine and generate ETags.
     * 
     * @param mixed $path 
     * @return void
     */
    protected function getETag( $path )
    {
    	$path = $this->fixPath($path);    	
        clearstatcache();
        $mtime = filemtime( $this->getAccessDriver()->getRessourceUrl($path) );
        //AJXP_Logger::debug("Getting etag ".$path);
        return md5(
            $path
            . $this->getContentLength( $path )
            . date( 'c', $mtime )
        );
    }
    
    protected function getContentLength($path){
    	$path = $this->fixPath($path);
        $length = ezcWebdavGetContentLengthProperty::COLLECTION;
        if ( !$this->isCollection( $path ) )
        {
            $length = (string) filesize( $this->getAccessDriver()->getRessourceUrl($path) );
        }                
        return $length;    	
    }
    
    
    /**
     * Returns the mime type of a resource.
     *
     * Return the mime type of the resource identified by $path. If a mime type
     * extension is available it will be used to read the real mime type,
     * otherwise the original mime type passed by the client when uploading the
     * file will be returned. If no mimetype has ever been associated with the
     * file, the method will just return 'application/octet-stream'.
     * 
     * @param string $path 
     * @return string
     */
    protected function getMimeType( $path )
    {
    	$path = $this->fixPath($path);
    	$url = $this->getAccessDriver()->getRessourceUrl($path);
        // Check if extension pecl/fileinfo is usable.
        if ( $this->options->useMimeExts && ezcBaseFeatures::hasExtensionSupport( 'fileinfo' ) )
        {
            $fInfo = new fInfo( FILEINFO_MIME );
            $mimeType = $fInfo->file( $url );

            // The documentation tells to do this, but it does not work with a
            // current version of pecl/fileinfo
            // $fInfo->close();

            return $mimeType;
        }

        // Check if extension ext/mime-magic is usable.
        if ( $this->options->useMimeExts && 
             ezcBaseFeatures::hasExtensionSupport( 'mime_magic' ) &&
             ( $mimeType = mime_content_type( $url ) ) !== false )
        {
            return $mimeType;
        }

        // Check if some browser submitted mime type is available.
        /*
        $storage = $this->getPropertyStorage( $path );
        $properties = $storage->getAllProperties();

        if ( isset( $properties['DAV:']['getcontenttype'] ) )
        {
            return $properties['DAV:']['getcontenttype']->mime;
        }
        */

        // Default to 'application/octet-stream' if nothing else is available.
        return 'application/octet-stream';
    }    
    
    /**
     * Returns all properties for a resource.
     * 
     * Returns all properties for the resource identified by $path as a {@link
     * ezcWebdavBasicPropertyStorage}.
     *
     * @param string $path 
     * @return ezcWebdavPropertyStorage
     */
    public function getAllProperties( $path ){
    	//$path = utf8_decode($path);
    	$path = $this->fixPath($path);
        $storage = $this->getPropertyStorage( $path );
        $metaStore = $this->getMetastore();
        if($metaStore != false) {
            $node = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($path));
            $existingMeta = $metaStore->retrieveMetadata($node, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
            if(is_array($existingMeta) && count($existingMeta)) {
                foreach($existingMeta as $pName => $serialized){
                    $storage->attach(unserialize(base64_decode($serialized)));
                }
            }
        }
        // Add all live properties to stored properties
        foreach ( $this->handledLiveProperties as $property )
        {
            $storage->attach(
                $this->getProperty( $path, $property )
            );
        }

        return $storage;    	
    }
    
    /**
     * Returns the property storage for a resource.
     *
     * Returns the {@link ezcWebdavPropertyStorage} instance containing the
     * properties for the resource identified by $path.
     * 
     * @param string $path 
     * @return ezcWebdavBasicPropertyStorage
     */
    protected function getPropertyStorage( $path )
    {
    	return new ezcWebdavBasicPropertyStorage();
    }    

    /**
     * Copies resources recursively from one path to another.
     *
     * Copies the resourced identified by $fromPath recursively to $toPath with
     * the given $depth, where $depth is one of {@link
     * ezcWebdavRequest::DEPTH_ZERO}, {@link ezcWebdavRequest::DEPTH_ONE},
     * {@link ezcWebdavRequest::DEPTH_INFINITY}.
     *
     * Returns an array with {@link ezcWebdavErrorResponse}s for all subtrees,
     * where the copy operation failed. Errors for subsequent resources in a
     * subtree should be ommitted.
     *
     * If an empty array is return, the operation has been completed
     * successfully.
     * 
     * @param string $fromPath 
     * @param string $toPath 
     * @param int $depth
     * @return array(ezcWebdavErrorResponse)
     */
    protected function performCopy( $fromPath, $toPath, $depth = ezcWebdavRequest::DEPTH_INFINITY ){
    	$fromPath = $this->fixPath($fromPath);
    	$toPath = $this->fixPath($toPath);
    	$error = array();
    	$success = array();
        ob_start();
        try{
            if($this->safeDirname($toPath) == $this->safeDirname($fromPath)){
                AJXP_Controller::findActionAndApply("rename", array(
                    "filename_new"      => basename($toPath),
                    "dir"               => dirname($fromPath),
                    "file"              => $fromPath
                ), array());
                //rename($this->getAccessDriver()->getRessourceUrl($fromPath), $this->getAccessDriver()->getRessourceUrl($toPath));
            }else{
                AJXP_Controller::findActionAndApply("copy", array(
                    "dest"      => dirname($toPath),
                    "dir"       => dirname($fromPath),
                    "file_0"    => $fromPath
                ), array());
                //$this->getAccessDriver()->copyOrMoveFile( $this->safeDirname($toPath), $fromPath, $error, $success, $move = false);
            }
        }catch(Exception $e){
            AJXP_Logger::debug("ERROR : ".$e->getMessage());
        }

        $result = ob_get_flush();
        AJXP_Logger::debug("RESULT : ".$result, $error);

        $metaStore = $this->getMetastore();
        if($metaStore != false){
            $fromNode = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($fromPath));
            $toNode = new AJXP_Node($this->getAccessDriver()->getRessourceUrl($toPath));
            $existingMeta = $metaStore->retrieveMetadata($fromNode, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
            if(is_array($existingMeta)){
                $metaStore->removeMetadata($fromNode, "ezcWEBDAV", false, AJXP_METADATA_SCOPE_GLOBAL);
                foreach($existingMeta as $name => $serialized){
                    if($name == "lockdiscovery") unset($existingMeta[$name]);
                }
                $metaStore->setMetadata($toNode, "ezcWEBDAV", $existingMeta, false, AJXP_METADATA_SCOPE_GLOBAL);
            }
        }
    	return $error;
    }

    /**
     * Deletes everything below a path.
     *
     * Deletes the resource identified by $path recursively. Returns an
     * instance of {@link ezcWebdavMultistatusResponse} if the deletion failed,
     * and null on success.
     * 
     * @param string $path 
     * @return ezcWebdavMultitstatusResponse|null
     */
    protected function performDelete( $path ){
    	$path = $this->fixPath($path);
    	$logs = array();
    	//$this->getAccessDriver()->delete(array($path), $logs);
        ob_start();
        try{
            AJXP_Controller::findActionAndApply("delete", array(
                "dir"       => dirname($path),
                "file_0"    => $path
            ), array());
        }catch(Exception $e){

        }
        $result = ob_get_flush();
        AJXP_Logger::debug("RESULT : ".$result);

        $metaStore = $this->getMetastore();
        if($metaStore == false) return;
        $metaStore->removeMetadata(
            new AJXP_Node($this->getAccessDriver()->getRessourceUrl($path)),
            "ezcWEBDAV",
            false,
            AJXP_METADATA_SCOPE_GLOBAL
        );
        return null;
    }

    /**
     * Returns if a resource exists.
     *
     * Returns if a the resource identified by $path exists.
     * 
     * @param string $path 
     * @return bool
     */
    protected function nodeExists( $path ){
    	$path = $this->fixPath($path);
    	if(isset($this->statCache[$path]["node_exists"])){
    		return $this->statCache[$path]["node_exists"];
    	}
    	$url = $this->getAccessDriver()->getRessourceUrl($path);
    	$result = file_exists( $url );
	    AJXP_Logger::debug("nodeExists($path, $url): $result");
	    $this->statCache[$path]["node_exists"] = $result;
    	return $result;    	
    }

    /**
     * Returns if resource is a collection.
     *
     * Returns if the resource identified by $path is a collection resource
     * (true) or a non-collection one (false).
     * 
     * @param string $path 
     * @return bool
     */
    protected function isCollection( $path ){
    	$path = $this->fixPath($path);
    	if(isset($this->statCache[$path]["is_collection"])){
    		return $this->statCache[$path]["is_collection"];
    	}
	    $url = $this->getAccessDriver()->getRessourceUrl($path);
	    //AJXP_Logger::debug("isCollection($path, $url)");
	    $result = is_dir( $url );
	    $this->statCache[$path]["is_collection"] = $result;
    	return $result;
    }

    /**
     * Returns members of collection.
     *
     * Returns an array with the members of the collection identified by $path.
     * The returned array can contain {@link ezcWebdavCollection}, and {@link
     * ezcWebdavResource} instances and might also be empty, if the collection
     * has no members.
     * 
     * @param string $path 
     * @return array(ezcWebdavResource|ezcWebdavCollection)
     */
    protected function getCollectionMembers( $path ){
    	$path = $this->fixPath($path);
    	$url = $this->getAccessDriver()->getRessourceUrl($path);
        $contents = array();
        $errors = array();

        $nodes = scandir($url);
        
        AJXP_Logger::debug("getCollectionMembers ".$path);
		
        foreach ( $nodes as $file )
        {
			if ( isset($this->options->hideDotFiles) && $this->options->hideDotFiles !== false && AJXP_Utils::isHidden($file)){
				continue;
			}
			if ( is_dir( $url . "/" . $file ) )
            {
                // Add collection without any children
                $contents[] = new ezcWebdavCollection( $this->urlEncodePath( SystemTextEncoding::toUTF8($path, true) .($path== "/"?"":"/"). SystemTextEncoding::toUTF8($file, true) ) );
            }
            else
            {
                // Add files without content
                $contents[] = new ezcWebdavResource( $this->urlEncodePath( SystemTextEncoding::toUTF8($path, true) .($path== "/"?"":"/"). SystemTextEncoding::toUTF8($file, true) ));
            }
        }
        return $contents;

    }
    
    
    /**
     * Serves GET requests.
     *
     * The method receives a {@link ezcWebdavGetRequest} object containing all
     * relevant information obout the clients request and will return an {@link
     * ezcWebdavErrorResponse} instance on error or an instance of {@link
     * ezcWebdavGetResourceResponse} or {@link ezcWebdavGetCollectionResponse}
     * on success, depending on the type of resource that is referenced by the
     * request.
     *
     * @param ezcWebdavGetRequest $request
     * @return ezcWebdavResponse
     */
    public function get( ezcWebdavGetRequest $request )
    {
        $source = $request->requestUri;

        // Check authorization
        if ( !ezcWebdavServer::getInstance()->isAuthorized( $source, $request->getHeader( 'Authorization' ) ) )
        {
            return $this->createUnauthorizedResponse(
                $source,
                $request->getHeader( 'Authorization' )
            );
        }

        try {
            // Check if resource is available
            if ( !$this->nodeExists( $source ) )
            {
                return new ezcWebdavErrorResponse(
                    ezcWebdavResponse::STATUS_404,
                    $source
                );
            }
        }catch(Exception $e){

            return new ezcWebdavErrorResponse(
                ezcWebdavResponse::STATUS_500,
                $source,
                $e->getMessage()
            );

        }

        // Verify If-[None-]Match headers
        if ( ( $res = $this->checkIfMatchHeaders( $request, $source ) ) !== null )
        {
            return $res;
        }
        
        $res = null; // Init
        if ( !$this->isCollection( $source ) )
        {
            // Just deliver file
            $res = new ezcWebdavGetResourceResponse(
                new ezcWebdavResource(
                    $source,
                    $this->getAllProperties( $source )/*,
                    $this->getResourceContents( $source )*/
                )
            );
            $res->setHeader("AJXP-Send-File", $this->getAccessDriver()->getRessourceUrl($this->fixPath($source)));
            $res->setHeader("AJXP-Wrapper", $this->wrapperClassName);
        }
        else
        {
            // Return collection with contained children
            $res = new ezcWebdavGetCollectionResponse(
                new ezcWebdavCollection(
                    $source,
                    $this->getAllProperties( $source ),
                    $this->getCollectionMembers( $source )
                )
            );
        }

        // Add ETag header
        $res->setHeader( 'ETag', $this->getETag( $source ) );

        // Deliver response
        return $res;
    }
    
    
	
    /**
     * Locks the backend.
     *
     * Tries to lock the backend. If the lock is already owned by this process,
     * locking is successful. If $timeout is reached before a lock could be
     * acquired, an {@link ezcWebdavLockTimeoutException} is thrown. Waits
     * $waitTime microseconds between attempts to lock the backend.
     *
     * @param int $waitTime
     * @param int $timeout
     * @return void
     */
    public function lock( $waitTime, $timeout )
    {
        /*
        // Check and raise lockLevel counter
        if ( $this->lockLevel > 0 )
        {
            // Lock already acquired
            ++$this->lockLevel;
            return;
        }

        $lockStart = microtime( true );

        $lockFileName = AJXP_SHARED_CACHE_DIR."/davlocks/".$this->options->lockFileName;
        if(!is_dir(AJXP_SHARED_CACHE_DIR."/davlocks")){
            mkdir(AJXP_SHARED_CACHE_DIR."/davlocks", 0644, true);
        }

        if ( is_file( $lockFileName ) && !is_writable( $lockFileName )
             || !is_file( $lockFileName ) && !is_writable(dirname( $lockFileName ) ) )
        {
            throw new ezcBaseFilePermissionException(
                $lockFileName,
                ezcBaseFileException::WRITE,
                'Cannot be used as lock file.'
            );
        }

        // fopen in mode 'x' will only open the file, if it does not exist yet.
        // Even this is is expected it will throw a warning, if the file
        // exists, which we need to silence using the @
        while ( ( $fp = @fopen( $lockFileName, 'x' ) ) === false )
        {
            // This is untestable.
            if ( microtime( true ) - $lockStart > $timeout )
            {
                // Release timed out lock
                unlink( $lockFileName );
                $lockStart = microtime( true );
            }
            else
            {
                usleep( $waitTime );
            }
        }

        // Store random bit in file ... the microtime for example - might prove
        // useful some time.
        fwrite( $fp, microtime() );
        fclose( $fp );

        // Add first lock
        ++$this->lockLevel;
        */
    }

    /**
     * Removes the lock.
     *
     * @return void
     */
    public function unlock()
    {
        /*
        if ( --$this->lockLevel === 0 )
        {
            // Remove the lock file
            $lockFileName = AJXP_SHARED_CACHE_DIR."/davlocks/".$this->options->lockFileName;
            unlink( $lockFileName );
        }
        */
    }

    
    protected function urlEncodePath($path){
        //AJXP_Logger::debug("User Agent : ".$_SERVER["HTTP_USER_AGENT"]);
        if(strstr($_SERVER["HTTP_USER_AGENT"], "GoodReader") !== false){
            return $path;
        }
        if(strstr($_SERVER["HTTP_USER_AGENT"], "MiniRedir") !== false){
            return $path;
        }
        if(strstr($_SERVER["HTTP_USER_AGENT"], "WebDAVFS") !== false){
            return $path;
        }
        if(strstr($_SERVER["HTTP_USER_AGENT"], "PEAR::HTTP_WebDAV_Client") !== false){
            return $path;
        }
        return rawurlencode($path);
    }


	protected function safeDirname($path){
		return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", dirname($path)): dirname($path));
	}
	
	protected function safeBasename($path){
		return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", basename($path)): basename($path));
	}
    
}


?>
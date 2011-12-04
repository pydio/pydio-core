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
		AJXP_Logger::debug("fixPath called ".$path);
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
     	$this->getAccessDriver()->mkDir($this->safeDirname($path), $this->safeBasename($path));
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
    	AJXP_Logger::debug("AJXP_WebdavBackend :: createResource ($path)");
    	$this->getAccessDriver()->createEmptyFile($this->safeDirname($path), $this->safeBasename($path));
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
     	$fp=fopen($this->getAccessDriver()->getRessourceUrl($path),"w");
		fputs ($fp,$content);
		fclose($fp);     	
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
     * Manually sets a property on a resource.
     *
     * Sets the given $propertyBackup for the resource identified by $path.
     * 
     * @param string $path 
     * @param ezcWebdavProperty $property
     * @return bool
     */
    public function setProperty( $path, ezcWebdavProperty $property ){
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
	    //AJXP_Logger::debug("Getting Property : ".$propertyName." for url ".$url);	
        $storage = $this->getPropertyStorage( $path );

        // Handle dead propreties
        if ( $namespace !== 'DAV:' )
        {
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
                $property = new ezcWebdavLockDiscoveryProperty();
                return $property;

            default:
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
    	// Handle duplicate
    	if($this->safeDirname($toPath) == $this->safeDirname($fromPath)){
    		rename($this->getAccessDriver()->getRessourceUrl($fromPath), $this->getAccessDriver()->getRessourceUrl($toPath));
    	}else{
			$this->getAccessDriver()->copyOrMoveFile( $this->safeDirname($toPath), $fromPath, $error, $success, $move = false);
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
    	$this->getAccessDriver()->delete(array($path), $logs);
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

        // Check if resource is available
        if ( !$this->nodeExists( $source ) )
        {
            return new ezcWebdavErrorResponse(
                ezcWebdavResponse::STATUS_404,
                $source
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
     * Acquire a backend lock.
     *
     * This method must acquire an exclusive lock of the backend. If the
     * backend is already locked by a different request, the must must retry to
     * acquire the lock continously and wait between each retry $waitTime micro
     * seconds. If $timeout microseconds have passed since the method was
     * called, it must throw an exception of type {@link
     * ezcWebdavLockTimeoutException}.
     * 
     * @param int $waitTime Microseconds.
     * @param int $timeout Microseconds.
     * @return void
     */
    public function lock( $waitTime, $timeout ){
    	
    }

    /**
     * Release the backend lock.
     *
     * This method is called to unlock the backend. The lock that was acquired
     * using {@link lock()} must be released, so that the backend can be locked
     * by another request.
     * 
     * @return void
     */
    public function unlock(){
    	
    } 
    
    protected function urlEncodePath($path){
        if(strstr($_SERVER["HTTP_USER_AGENT"], "WebDAVFS") === false && strstr($_SERVER["HTTP_USER_AGENT"], "PEAR::HTTP_WebDAV_Client") === false){
            return rawurlencode($path);
        }
        return $path;
    }


	protected function safeDirname($path){
		return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", dirname($path)): dirname($path));
	}
	
	protected function safeBasename($path){
		return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", basename($path)): basename($path));
	}
    
}


?>
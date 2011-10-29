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
define('AJXP_METADATA_SHAREDUSER', 'AJXP_METADATA_SHAREDUSER');

define('AJXP_METADATA_SCOPE_GLOBAL', 1);
define('AJXP_METADATA_SCOPE_REPOSITORY', 2);
/**
 * @package info.ajaxplorer.plugins
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 */
class SerialMetaStore extends AJXP_Plugin {
	
	private static $currentMetaName;
	private static $metaCache;
	private static $fullMetaCache;

	public $accessDriver;
	
	public function init($options){
		$this->options = $options;		
	}

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
    }


    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])){
            self::$metaCache[$nameSpace] = array();
        }
        self::$metaCache[$nameSpace] = array_merge(self::$metaCache[$nameSpace], $metaData);
        $this->saveMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
    }

    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])) return;
        unset(self::$metaCache[$nameSpace]);
        $this->saveMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
    }

    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY){
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?AuthService::getLoggedUser()->getId():AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])) return array();
        else return self::$metaCache[$nameSpace];
    }


	/*
	public function editMeta($actionName, $httpVars, $fileVars){
		if(!isSet($this->actions[$actionName])) return;
		if(is_a($this->accessDriver, "demoAccessDriver")){
			throw new Exception("Write actions are disabled in demo mode!");
		}
		$repo = $this->accessDriver->repository;
		$user = AuthService::getLoggedUser();
		if(!$user->canWrite($repo->getId())){
			throw new Exception("You have no right on this action.");
		}
		$selection = new UserSelection();
		$selection->initFromHttpVars();
		$currentFile = $selection->getUniqueFile();
		$wrapperData = $this->accessDriver->detectStreamWrapper(false);
		$urlBase = $wrapperData["protocol"]."://".$this->accessDriver->repository->getId();

		
		$newValues = array();
		$def = $this->getMetaDefinition();
		foreach ($def as $key => $label){
			if(isSet($httpVars[$key])){
				$newValues[$key] = AJXP_Utils::decodeSecureMagic($httpVars[$key]);
			}else{
				if(!isset($original)){
					$original = array();
					$this->loadMetaFileData($urlBase.$currentFile);
					$base = basename($currentFile);
					if(is_array(self::$metaCache) && array_key_exists($base, self::$metaCache)){
						$original = self::$metaCache[$base];
					}					
				}
				if(isSet($original) && isset($original[$key])){
					$newValues[$key] = $original[$key];
				}
			}
		}		
		$this->addMeta($urlBase.$currentFile, $newValues);
        $ajxpNode = new AJXP_Node($urlBase.$currentFile);
        AJXP_Controller::applyHook("node.change", array(null, &$ajxpNode));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::reloadDataNode("", SystemTextEncoding::toUTF8($currentFile), true);	
		AJXP_XMLWriter::close();
	}



	public function extractMeta(&$ajxpNode){
		$currentFile = $ajxpNode->getUrl();
		$metadata = $ajxpNode->metadata;
		$base = basename($currentFile);
		$this->loadMetaFileData($currentFile);		
		if(is_array(self::$metaCache) && array_key_exists($base, self::$metaCache)){
			$encodedMeta = array_map(array("SystemTextEncoding", "toUTF8"), self::$metaCache[$base]);
			$metadata = array_merge($metadata, $encodedMeta);
		}
		// NOT OPTIMAL AT ALL 
		$metadata["meta_fields"] = $this->options["meta_fields"];
		$metadata["meta_labels"] = $this->options["meta_labels"];
		$ajxpNode->metadata = $metadata;
	}
	

	public function updateMetaLocation($oldFile, $newFile = null, $copy = false){
		if($oldFile == null) return;
		
		$this->loadMetaFileData($oldFile->getUrl());
		$oldKey = basename($oldFile->getUrl());
		if(!array_key_exists($oldKey, self::$metaCache)){
			return;
		}
		$oldData = self::$metaCache[$oldKey];
		// If it's a move or a delete, delete old data
		if(!$copy){
			unset(self::$metaCache[$oldKey]);
			$this->saveMetaFileData($oldFile->getUrl());
		}
		// If copy or move, copy data.
		if($newFile != null){
			$this->addMeta($newFile->getUrl(), $oldData);
		}
	}
	
	public function addMeta($currentFile, $dataArray){
		$this->loadMetaFileData($currentFile);
		self::$metaCache[basename($currentFile)] = $dataArray;
		$this->saveMetaFileData($currentFile);
	}

	*/

    
    /**
     * @param AJXP_Node $ajxpNode
     * @param String $scope
     * @param String $userId
     * @return void
     */
	protected function loadMetaFileData($ajxpNode, $scope, $userId){
        $currentFile = $ajxpNode->getUrl();
        $fileKey = $ajxpNode->getPath();
        if($scope == AJXP_METADATA_SCOPE_GLOBAL){
            $metaFile = dirname($currentFile)."/".$this->pluginConf["METADATA_FILE"];
            //if(self::$currentMetaName == $metaFile && is_array(self::$metaCache))return;
            // Cannot store metadata inside zips...
            if(preg_match("/\.zip\//",$currentFile)){
                self::$fullMetaCache = array();
                self::$metaCache = array();
                return ;
            }
            $fileKey = basename($fileKey);
        }else{
            // already loaded?
            //if(is_array(self::$fullMetaCache)) return;
            $metaFile = AJXP_DATA_PATH."/plugins/metastore.serial/".$this->pluginConf["METADATA_FILE"]."_".$ajxpNode->getRepositoryId();
        }
		if(is_file($metaFile) && is_readable($metaFile)){
            self::$currentMetaName = $metaFile;
			$rawData = file_get_contents($metaFile);
            self::$fullMetaCache = unserialize($rawData);
            if(isSet(self::$fullMetaCache[$fileKey][$userId])){
                self::$metaCache = self::$fullMetaCache[$fileKey][$userId];
            }else{
                self::$metaCache = array();
            }
		}else{
            self::$fullMetaCache = array();
			self::$metaCache = array();
		}
	}

    /**
     * @param AJXP_Node $ajxpNode
     * @param String $scope
     * @param String $userId
     */
	protected function saveMetaFileData($ajxpNode, $scope, $userId){
        $currentFile = $ajxpNode->getUrl();
        $repositoryId = $ajxpNode->getRepositoryId();
        $fileKey = $ajxpNode->getPath();
        if($scope == AJXP_METADATA_SCOPE_GLOBAL){
            $metaFile = dirname($currentFile)."/".$this->pluginConf["METADATA_FILE"];
            $fileKey = basename($fileKey);
        }else{
            if(!is_dir(AJXP_DATA_PATH."/plugins/metastore.serial/")){
                mkdir(AJXP_DATA_PATH."/plugins/metastore.serial/", 0666, true);
            }
            $metaFile = AJXP_DATA_PATH."/plugins/metastore.serial/".$this->pluginConf["METADATA_FILE"]."_".$repositoryId;
        }
		if((is_file($metaFile) && call_user_func(array($this->accessDriver, "isWriteable"), $metaFile)) || call_user_func(array($this->accessDriver, "isWriteable"), dirname($metaFile)) || ($scope=="repository") ){
            if(!isset(self::$fullMetaCache[$fileKey])){
                self::$fullMetaCache[$fileKey] = array();
            }
            if(!isset(self::$fullMetaCache[$fileKey][$userId])){
                self::$fullMetaCache[$fileKey][$userId] = array();
            }
            self::$fullMetaCache[$fileKey][$userId] = self::$metaCache;
			$fp = fopen($metaFile, "w");
            if($fp !== false){
                @fwrite($fp, serialize(self::$fullMetaCache), strlen(serialize(self::$fullMetaCache)));
                @fclose($fp);
            }
			AJXP_Controller::applyHook("version.commit_file", $metaFile);
		}
	}
	
}

?>
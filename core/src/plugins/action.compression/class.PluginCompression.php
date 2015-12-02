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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Plugin to compress to TAR or TAR.GZ or TAR.BZ2... He can also extract your archives
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PluginCompression extends AJXP_Plugin
{
    /**
     * @param String $action
     * @param Array $httpVars
     * @param Array $fileVars
     * @throws Exception
     */
    public function receiveAction($action, $httpVars, $fileVars)
    {
        //VAR CREATION OUTSIDE OF ALL CONDITIONS, THEY ARE "MUST HAVE" VAR !!
        $messages = ConfService::getMessages();
        $repository = ConfService::getRepository();
        $userSelection = new UserSelection($repository, $httpVars);
        $nodes = $userSelection->buildNodes();
        $currentDirPath = AJXP_Utils::safeDirname($userSelection->getUniqueNode()->getPath());
        $currentDirPath = rtrim($currentDirPath, "/") . "/";
        $currentDirUrl = $userSelection->currentBaseUrl().$currentDirPath;
        if (empty($httpVars["compression_id"])) {
            $compressionId = sha1(rand());
            $httpVars["compression_id"] = $compressionId;
        } else {
            $compressionId = $httpVars["compression_id"];
        }
        $progressCompressionFileName = $this->getPluginCacheDir(false, true) . DIRECTORY_SEPARATOR . "progressCompressionID-" . $compressionId . ".txt";
        if (empty($httpVars["extraction_id"])) {
            $extractId = sha1(rand());
            $httpVars["extraction_id"] = $extractId;
        } else {
            $extractId = $httpVars["extraction_id"];
        }
            $progressExtractFileName = $this->getPluginCacheDir(false, true) . DIRECTORY_SEPARATOR . "progressExtractID-" . $extractId . ".txt";
        if ($action == "compression") {
            $archiveName = AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
            $archiveFormat = $httpVars["type_archive"];
            $tabTypeArchive = array(".tar", ".tar.gz", ".tar.bz2");
            $acceptedExtension = false;
            foreach ($tabTypeArchive as $extensionArchive) {
                if ($extensionArchive == $archiveFormat) {
                    $acceptedExtension = true;
                    break;
                }
            }
            if ($acceptedExtension == false) {
                file_put_contents($progressCompressionFileName, "Error : " . $messages["compression.16"]);
                throw new AJXP_Exception($messages["compression.16"]);
            }
            $typeArchive = $httpVars["type_archive"];
            //if we can run in background we do it
            if (ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()) {
                $archivePath = $currentDirPath.$archiveName;
                file_put_contents($progressCompressionFileName, $messages["compression.5"]);
                AJXP_Controller::applyActionInBackground($repository->getId(), "compression", $httpVars);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_compression_status", array(
                    "repository_id" => $repository->getId(),
                    "compression_id" => $compressionId,
                    "archive_path" => SystemTextEncoding::toUTF8($archivePath)
                ), $messages["compression.5"], true, 2);
                AJXP_XMLWriter::close();
                return null;
            } else {
                $maxAuthorizedSize = 4294967296;
                $currentDirUrlLength = strlen($currentDirUrl);
                $tabFolders = array();
                $tabAllRecursiveFiles = array();
                $tabFilesNames = array();
                foreach ($nodes as $node) {
                    $nodeUrl = $node->getUrl();
                    if (is_file($nodeUrl) && filesize($nodeUrl) < $maxAuthorizedSize) {
                        array_push($tabAllRecursiveFiles, $nodeUrl);
                        array_push($tabFilesNames, substr($nodeUrl, $currentDirUrlLength));
                    }
                    if (is_dir($nodeUrl)) {
                        array_push($tabFolders, $nodeUrl);
                    }
                }
                //DO A FOREACH OR IT'S GONNA HAVE SOME SAMES FILES NAMES
                foreach ($tabFolders as $value) {
                    $dossiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($value));
                    foreach ($dossiers as $file) {
                        if ($file->isDir()) {
                            continue;
                        }
                        array_push($tabAllRecursiveFiles, $file->getPathname());
                        array_push($tabFilesNames, substr($file->getPathname(), $currentDirUrlLength));
                    }
                }
                //WE STOP IF IT'S JUST AN EMPTY FOLDER OR NO FILES
                if (empty($tabFilesNames)) {
                    file_put_contents($progressCompressionFileName, "Error : " . $messages["compression.17"]);
                    throw new AJXP_Exception($messages["compression.17"]);
                }
                try {
                    $tmpArchiveName = tempnam(AJXP_Utils::getAjxpTmpDir(), "tar-compression") . ".tar";
                    $archive = new PharData($tmpArchiveName);
                } catch (Exception $e) {
                    file_put_contents($progressCompressionFileName, "Error : " . $e->getMessage());
                    throw $e;
                }
                $counterCompression = 0;
                //THE TWO ARRAY ARE MERGED FOR THE FOREACH LOOP
                $tabAllFiles = array_combine($tabAllRecursiveFiles, $tabFilesNames);
                foreach ($tabAllFiles as $fullPath => $fileName) {
                    try {
                        $archive->addFile(AJXP_MetaStreamWrapper::getRealFSReference($fullPath), $fileName);
                        $counterCompression++;
                        file_put_contents($progressCompressionFileName, sprintf($messages["compression.6"], round(($counterCompression / count($tabAllFiles)) * 100, 0, PHP_ROUND_HALF_DOWN) . " %"));
                    } catch (Exception $e) {
                        unlink($tmpArchiveName);
                        file_put_contents($progressCompressionFileName, "Error : " . $e->getMessage());
                        throw $e;
                    }
                }
                $finalArchive = $tmpArchiveName;
                if ($typeArchive != ".tar") {
                    $archiveTypeCompress = substr(strrchr($typeArchive, "."), 1);
                    file_put_contents($progressCompressionFileName, sprintf($messages["compression.7"], strtoupper($archiveTypeCompress)));
                    if ($archiveTypeCompress == "gz") {
                        $archive->compress(Phar::GZ);
                    } elseif ($archiveTypeCompress == "bz2") {
                        $archive->compress(Phar::BZ2);
                    }
                    $finalArchive = $tmpArchiveName . "." . $archiveTypeCompress;
                }
                $destArchive = AJXP_MetaStreamWrapper::getRealFSReference($currentDirUrl . $archiveName);
                rename($finalArchive, $destArchive);
                AJXP_Controller::applyHook("node.before_create", array($destArchive, filesize($destArchive)));
                if (file_exists($tmpArchiveName)) {
                    unlink($tmpArchiveName);
                    unlink(substr($tmpArchiveName, 0, -4));
                }
                $newNode = new AJXP_Node($currentDirUrl . $archiveName);
                AJXP_Controller::applyHook("node.change", array(null, $newNode, false));
                file_put_contents($progressCompressionFileName, "SUCCESS");
            }
        }
    elseif ($action == "check_compression_status") {
        $archivePath = AJXP_Utils::decodeSecureMagic($httpVars["archive_path"]);
        $progressCompression = file_get_contents($progressCompressionFileName);
        $substrProgressCompression = substr($progressCompression, 0, 5);
        if ($progressCompression != "SUCCESS" && $substrProgressCompression != "Error") {
            AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_compression_status", array(
                    "repository_id" => $repository->getId(),
                    "compression_id" => $compressionId,
                    "archive_path" => SystemTextEncoding::toUTF8($archivePath)
                ), $progressCompression, true, 5);
                AJXP_XMLWriter::close();
            } elseif ($progressCompression == "SUCCESS") {
                $newNode = new AJXP_Node($userSelection->currentBaseUrl() . $archivePath);
                $nodesDiffs = array("ADD" => array($newNode), "REMOVE" => array(), "UPDATE" => array());
                AJXP_Controller::applyHook("node.change", array(null, $newNode, false));
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($messages["compression.8"], null);
                AJXP_XMLWriter::writeNodesDiff($nodesDiffs, true);
                AJXP_XMLWriter::close();
                if (file_exists($progressCompressionFileName)) {
                    unlink($progressCompressionFileName);
                }
            } elseif ($substrProgressCompression == "Error") {
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage(null, $progressCompression);
                AJXP_XMLWriter::close();
                if (file_exists($progressCompressionFileName)) {
                    unlink($progressCompressionFileName);
                }
            }
        }
        elseif ($action == "extraction") {
            $fileArchive = AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic($httpVars["file"]), AJXP_SANITIZE_DIRNAME);
            $fileArchive = substr(strrchr($fileArchive, DIRECTORY_SEPARATOR), 1);
            $authorizedExtension = array("tar" => 4, "gz" => 7, "bz2" => 8);
            $acceptedArchive = false;
            $extensionLength = 0;
            $counterExtract = 0;
            $currentAllPydioPath = $currentDirUrl . $fileArchive;
            $pharCurrentAllPydioPath = "phar://" . AJXP_MetaStreamWrapper::getRealFSReference($currentAllPydioPath);
            $pathInfoCurrentAllPydioPath = pathinfo($currentAllPydioPath, PATHINFO_EXTENSION);
            //WE TAKE ONLY TAR, TAR.GZ AND TAR.BZ2 ARCHIVES
            foreach ($authorizedExtension as $extension => $strlenExtension) {
                if ($pathInfoCurrentAllPydioPath == $extension) {
                    $acceptedArchive = true;
                    $extensionLength = $strlenExtension;
                    break;
                }
            }
            if ($acceptedArchive == false) {
                file_put_contents($progressExtractFileName, "Error : " . $messages["compression.15"]);
                throw new AJXP_Exception($messages["compression.15"]);
            }
            $onlyFileName = substr($fileArchive, 0, -$extensionLength);
            $lastPosOnlyFileName =  strrpos($onlyFileName, "-");
            $tmpOnlyFileName = substr($onlyFileName, 0, $lastPosOnlyFileName);
            $counterDuplicate = substr($onlyFileName, $lastPosOnlyFileName + 1);
            if ($lastPosOnlyFileName == false) {
                $tmpOnlyFileName = $onlyFileName;
                $counterDuplicate = 1;
            }
            while (file_exists($currentDirUrl . $onlyFileName)) {
                $onlyFileName = $tmpOnlyFileName . "-" . $counterDuplicate;
                $counterDuplicate++;
            }
            if (ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()) {
                file_put_contents($progressExtractFileName, $messages["compression.12"]);
                AJXP_Controller::applyActionInBackground($repository->getId(), "extraction", $httpVars);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_extraction_status", array(
                    "repository_id" => $repository->getId(),
                    "extraction_id" => $extractId,
                    "currentDirUrl" => $currentDirUrl,
                    "onlyFileName" => $onlyFileName
                ), $messages["compression.12"], true, 2);
                AJXP_XMLWriter::close();
                return null;
            }
            mkdir($currentDirUrl . $onlyFileName, 0777, true);
            chmod(AJXP_MetaStreamWrapper::getRealFSReference($currentDirUrl . $onlyFileName), 0777);
            try {
                $archive = new PharData(AJXP_MetaStreamWrapper::getRealFSReference($currentAllPydioPath));
                $fichiersArchive = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pharCurrentAllPydioPath));
                foreach ($fichiersArchive as $file) {
                    $fileGetPathName = $file->getPathname();
                    if($file->isDir()) {
                        continue;
                    }
                    $fileNameInArchive = substr(strstr($fileGetPathName, $fileArchive), strlen($fileArchive) + 1);
                    try {
                        $archive->extractTo(AJXP_MetaStreamWrapper::getRealFSReference($currentDirUrl . $onlyFileName), $fileNameInArchive, false);
                    } catch (Exception $e) {
                        file_put_contents($progressExtractFileName, "Error : " . $e->getMessage());
                        throw new AJXP_Exception($e);
                    }
                    $newNode = new AJXP_Node($currentDirUrl . $onlyFileName . DIRECTORY_SEPARATOR . $fileNameInArchive);
                    AJXP_Controller::applyHook("node.change", array(null, $newNode, false));
                    $counterExtract++;
                    file_put_contents($progressExtractFileName, sprintf($messages["compression.13"], round(($counterExtract / $archive->count()) * 100, 0, PHP_ROUND_HALF_DOWN) . " %"));
                }
            } catch (Exception $e) {
                file_put_contents($progressExtractFileName, "Error : " . $e->getMessage());
                throw new AJXP_Exception($e);
            }
            file_put_contents($progressExtractFileName, "SUCCESS");
        }
        elseif ($action == "check_extraction_status") {
            $currentDirUrl = $httpVars["currentDirUrl"];
            $onlyFileName = $httpVars["onlyFileName"];
            $progressExtract = file_get_contents($progressExtractFileName);
            $substrProgressExtract = substr($progressExtract, 0, 5);
            if ($progressExtract != "SUCCESS" && $substrProgressExtract != "Error") {
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_extraction_status", array(
                    "repository_id" => $repository->getId(),
                    "extraction_id" => $extractId,
                    "currentDirUrl" => $currentDirUrl,
                    "onlyFileName" => $onlyFileName
                ), $progressExtract, true, 5);
                AJXP_XMLWriter::close();
            } elseif ($progressExtract == "SUCCESS") {
                $newNode = new AJXP_Node($currentDirUrl . $onlyFileName);
                $nodesDiffs = array("ADD" => array($newNode), "REMOVE" => array(), "UPDATE" => array());
                AJXP_Controller::applyHook("node.change", array(null, $newNode, false));
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage(sprintf($messages["compression.14"], $onlyFileName), null);
                AJXP_XMLWriter::writeNodesDiff($nodesDiffs, true);
                AJXP_XMLWriter::close();
                if (file_exists($progressExtractFileName)) {
                    unlink($progressExtractFileName);
                }
            } elseif ($substrProgressExtract == "Error") {
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage(null, $progressExtract);
                AJXP_XMLWriter::close();
                if (file_exists($progressExtractFileName)) {
                    unlink($progressExtractFileName);
                }
            }
        }
    }
}

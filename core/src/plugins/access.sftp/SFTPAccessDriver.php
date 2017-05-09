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
 * The latest code can be found at <https://pydio.com>.
 *
 */
namespace Pydio\Access\Driver\StreamProvider\SFTP;

use DOMNode;
use PclZip;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Vars\UrlUtils;


defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access an ftp server over SSH
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class SFTPAccessDriver extends FsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        if (!function_exists('ssh2_connect')) {
            throw new \Exception("You must have the php ssh2 extension active!");
        }
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $path       = $contextInterface->getRepository()->getContextOption($contextInterface, "PATH");
        $recycle    = $contextInterface->getRepository()->getContextOption($contextInterface, "RECYCLE_BIN");
        $this->urlBase = $contextInterface->getUrlBase();
        restore_error_handler();
        if (!file_exists($contextInterface->getUrlBase())) {
            if ($contextInterface->getRepository()->getContextOption($contextInterface, "CREATE")) {
                $test = @mkdir($contextInterface->getUrlBase());
                if (!$test) {
                    throw new PydioException("Cannot create path ($path) for your repository! Please check the configuration.");
                }
            } else {
                throw new PydioException("Cannot find base path ($path) for your repository! Please check the configuration!");
            }
        }
        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }
    }

    /**
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    /**
     * @param String $srcFile url of source file
     * @param String $destFile url of destination file
     */
    protected function filecopy($srcFile, $destFile)
    {
        if (MetaStreamWrapper::nodesUseSameWrappers($srcFile, $destFile)) {
            $srcFilePath = UrlUtils::safeParseUrl($srcFile)["path"];
            $destFilePath = UrlUtils::safeParseUrl($destFile)["path"];
            $destDirPath = dirname($destFilePath);
            list($connection, $remote_base_path) = SFTPAccessWrapper::getSshConnection(AJXP_Node::contextFromUrl($srcFile));
            $remoteSrc = $remote_base_path.$srcFilePath;
            $remoteDest = $remote_base_path.$destDirPath;
            $this->logDebug("SSH2 CP", array("cmd" => 'cp '.$remoteSrc.' '.$remoteDest));
            ssh2_exec($connection, 'cp '.$remoteSrc.' '.$remoteDest);
            Controller::applyHook("node.change", array(new AJXP_Node($srcFile), new AJXP_Node($destFile), true));
        } else {
            parent::filecopy($srcFile, $destFile);
        }
    }


    /**
     * @param UserSelection $selection
     * @param $dest
     * @param $basedir
     * @throws \Exception
     * @return PclZip Zip Archive
     */
    public function makeZip (UserSelection $selection, $dest, $basedir, $taskId = null)
    {
        @set_time_limit(60);
        require_once(AJXP_BIN_FOLDER."/lib/pclzip.lib.php");
        $filePaths = array();

        $uniqid = uniqid();
        $uniqfolder = '/tmp/ajaxplorer-zip-'.$uniqid;
        mkdir($uniqfolder);

        $nodes = $selection->buildNodes();
        foreach ($nodes as $node) {
            $item = $node->getPath();
            $basedir = trim(dirname($item));
            $basename = basename($item);
            $uniqpath = $uniqfolder.'/'.$basename;
            $this->full_copy($node->getUrl(), $uniqpath);
            $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $uniqpath,
                                 PCLZIP_ATT_FILE_NEW_SHORT_NAME => $basename);
        }
        $this->logDebug("Pathes", $filePaths);
        $this->logDebug("Basedir", array($basedir));
        $archive = new PclZip($dest);
        $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $uniqfolder, PCLZIP_OPT_NO_COMPRESSION);
        $this->recursiveRmdir($uniqfolder);
        if (!$vList) {
            throw new \Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
        }
        return $vList;
    }

    /**
     * @param $source
     * @param $destination
     */
    public function full_copy($source, $destination )
    {
        if ( is_dir( $source ) ) {
            @mkdir( $destination );
            $directory = dir( $source );
            while ( FALSE !== ( $readdirectory = $directory->read() ) ) {
                if ($readdirectory == '.' || $readdirectory == '..') {
                    continue;
                }
                $PathDir = $source . '/' . $readdirectory;
                if ( is_dir( $PathDir ) ) {
                    $this->full_copy( $PathDir, $destination . '/' . $readdirectory );
                    continue;
                }
                copy( $PathDir, $destination . '/' . $readdirectory );
            }

            $directory->close();
        } else {
            copy( $source, $destination );
        }
    }

    /**
     * @param $path
     * @return bool
     */
    public function recursiveRmdir($path)
    {
        if (is_dir($path)) {
            $path = rtrim($path, '/');
            $subdir = dir($path);
            while (($file = $subdir->read()) !== false) {
                if ($file != '.' && $file != '..') {
                (!is_link("$path/$file") && is_dir("$path/$file")) ? $this->recursiveRmdir("$path/$file") : unlink("$path/$file");
                }
            }
            $subdir->close();
            rmdir($path);
            return true;
        }
        return false;
    }


    /**
     * Specific isWriteable implementation
     * @param AJXP_Node $node
     * @return bool
     */
    public function isWriteable(AJXP_Node $node)
    {
        return true;
    }

}

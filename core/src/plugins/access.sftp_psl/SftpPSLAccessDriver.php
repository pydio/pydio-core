<?php
/*
 * Copyright 2013 Nikita ROUSSEAU <warhawk3407@gmail.com>
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 */
namespace Pydio\Access\Driver\StreamProvider\SFTP_PSL;


use DOMNode;
use PclZip;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die( 'Access not allowed' );




/**
 * Plugin to access a remote server using SSH File Transfer Protocol (SFTP) with phpseclib ( http://phpseclib.sourceforge.net/ )
 *
 * @author	warhawk3407 <warhawk3407@gmail.com>
 * @author	Charles du Jeu <contact (at) cdujeu.me>
 * @version	Release: 1.0.2
 */
class SftpPSLAccessDriver extends FsAccessDriver
{

    /**
    * @var \Pydio\Access\Core\Model\Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * @param ContextInterface $contextInterface
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        ConfService::setConf("PROBE_REAL_SIZE", false);

        require_once($this->getBaseDir()."/SFTPPSL_StreamWrapper.php");
        
        $this->urlBase = $contextInterface->getUrlBase();
    }

    /**
     * @param bool $register
     * @param ContextInterface|null $ctx
     * @return array|bool
     */
    public function detectStreamWrapper($register = false, ContextInterface $ctx = null)
    {
        if ($register) {
            require_once($this->getBaseDir()."/SFTPPSL_StreamWrapper.php");
        }
        return parent::detectStreamWrapper($register, $ctx);
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
     * @param UserSelection $selection
     * @param string $dest
     * @param string $basedir
     * @param string $taskId
     * @return PclZip zipfile
     * @throws \Exception
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

    public function full_copy( $source, $destination )
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
     * @param AJXP_Node $node
     * @return bool
     */
    public function isWriteable(AJXP_Node $node)
    {
        return is_writable($node->getUrl());
    }
}

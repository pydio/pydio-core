<?php
/*
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
namespace Pydio\Editor\Image;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocalCache;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class ImagePreviewer
 * Generate an image thumbnail and send the thumb/full version to the browser
 * @package Pydio\Editor\Image
 */
class ImagePreviewer extends Plugin
{
    private $currentDimension;

    /**
     * @param $action
     * @param $httpVars
     * @param $filesVars
     * @param ContextInterface $contextInterface
     * @throws \Exception
     */
    public function switchAction($action, $httpVars, $filesVars, ContextInterface $contextInterface)
    {
        if (!isSet($this->pluginConf)) {
            $this->pluginConf = array("GENERATE_THUMBNAIL"=>false);
        }
        $selection = UserSelection::fromContext($contextInterface, $httpVars);
        $destStreamURL = $selection->currentBaseUrl();

        if ($action == "preview_data_proxy") {
            $file = $selection->getUniqueFile();
            if (!file_exists($destStreamURL.$file) || !is_readable($destStreamURL.$file)) {
                header("Content-Type: ". StatHelper::getImageMimeType(basename($file)) ."; name=\"".basename($file)."\"");
                header("Content-Length: 0");
                return;
            }
            $this->logInfo('Preview', 'Preview content of '.$file, array("files" =>$selection->getUniqueFile()));
            if (isSet($httpVars["get_thumb"]) && $httpVars["get_thumb"] == "true" && $this->getContextualOption($contextInterface, "GENERATE_THUMBNAIL")) {
                $dimension = 200;
                if(isSet($httpVars["dimension"]) && is_numeric($httpVars["dimension"])) $dimension = $httpVars["dimension"];
                $this->currentDimension = $dimension;
                //$cacheItem = LocalCache::getItem("diaporama_".$dimension, $destStreamURL.$file, array($this, "generateThumbnail"));
                $cacheItem = LocalCache::getItem("diaporama_".$dimension, $destStreamURL.$file, function($masterFile, $targetFile) use ($contextInterface){
                    return $this->generateThumbnail($contextInterface, $masterFile, $targetFile);
                });
                $data = $cacheItem->getData();
                $cId = $cacheItem->getId();

                header("Content-Type: ". StatHelper::getImageMimeType(basename($cId)) ."; name=\"".basename($cId)."\"");
                header("Content-Length: ".strlen($data));
                header('Cache-Control: public');
                header("Pragma:");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                print($data);

            } else {

                $node = new AJXP_Node($destStreamURL.$file);

                $fp = fopen($destStreamURL.$file, "r");
                $stat = fstat($fp);
                $filesize = $stat["size"];
                header("Content-Type: ". StatHelper::getImageMimeType(basename($file)) ."; name=\"".basename($file)."\"");
                header("Content-Length: ".$filesize);
                header('Cache-Control: public');
                header("Pragma:");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");

                $stream = fopen("php://output", "a");
                MetaStreamWrapper::copyFileInStream($destStreamURL.$file, $stream);
                fflush($stream);
                fclose($stream);
                Controller::applyHook("node.read", array($node));
            }
        }
    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldFile
     * @param \Pydio\Access\Core\Model\AJXP_Node $newFile
     * @param Boolean $copy
     */
    public function removeThumbnail($oldFile, $newFile = null, $copy = false)
    {
        if($oldFile == null) return ;
        if(!$this->handleMime($oldFile->getUrl())) return;
        if ($newFile == null || $copy == false) {
            $diapoFolders = glob((defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/diaporama_*",GLOB_ONLYDIR);
            if ($diapoFolders !== false && is_array($diapoFolders)) {
                foreach ($diapoFolders as $f) {
                    $f = basename($f);
                    $this->logDebug("GLOB ".$f);
                    LocalCache::clearItem($f, $oldFile->getUrl());
                }
            }
        }
    }

    /**
     * @param \Pydio\Core\Model\ContextInterface $ctx
     * @param $masterFile
     * @param $targetFile
     * @return bool
     */
    public function generateThumbnail(ContextInterface $ctx, $masterFile, $targetFile)
    {
        $size = $this->currentDimension;
        require_once(AJXP_INSTALL_PATH."/plugins/editor.diaporama/PThumb.lib.php");
        $pThumb = new PThumb($this->getContextualOption($ctx, "THUMBNAIL_QUALITY"), $this->getContextualOption($ctx, "EXIF_ROTATION"));

        if (!$pThumb->isError()) {
            $pThumb->remote_wrapper = "Pydio\\Access\\Core\\MetaStreamWrapper";
            //$this->logDebug("Will fit thumbnail");
            $sizes = $pThumb->fit_thumbnail($masterFile, $size, -1, 1, true);
            //$this->logDebug("Will print thumbnail");
            $pThumb->print_thumbnail($masterFile,$sizes[0],$sizes[1],false, false, $targetFile);
            //$this->logDebug("Done");
            if ($pThumb->isError()) {
                print_r($pThumb->error_array);
                $this->logError("ImagePreviewer", $pThumb->error_array);
                return false;
            }
        } else {
            print_r($pThumb->error_array);
            $this->logError("ImagePreviewer", $pThumb->error_array);
            return false;
        }
        return true;
    }

    //public function extractImageMetadata($currentNode, &$metadata, $wrapperClassName, &$realFile){
    /**
     * Enrich node metadata
     * @param AJXP_Node $ajxpNode
     */
    public function extractImageMetadata(AJXP_Node &$ajxpNode)
    {
        $currentPath = $ajxpNode->getUrl();
        $wrapperClassName = $ajxpNode->wrapperClassName;
        $isImage = StatHelper::basenameIsImage($currentPath);
        $ajxpNode->is_image = $isImage;
        if(!$isImage) return;

        $context = $ajxpNode->getContext();
        $setRemote = false;
        $remoteWrappers = $this->getContextualOption($context, "META_EXTRACTION_REMOTEWRAPPERS");
        if (is_string($remoteWrappers)) {
            $remoteWrappers = explode(",",$remoteWrappers);
        }
        $remoteThreshold = $this->getContextualOption($context, "META_EXTRACTION_THRESHOLD");
        if (in_array($wrapperClassName, $remoteWrappers)) {
            if ($remoteThreshold != 0 && isSet($ajxpNode->bytesize)) {
                $setRemote = ($ajxpNode->bytesize > $remoteThreshold);
            } else {
                $setRemote = true;
            }
        }
        if ($isImage) {
            if ($setRemote) {
                $ajxpNode->image_type = "N/A";
                $ajxpNode->image_width = "N/A";
                $ajxpNode->image_height = "N/A";
                $ajxpNode->readable_dimension = "";
            } else {
                $realFile = $ajxpNode->getRealFile();
                list($width, $height, $type, $attr) = @getimagesize($realFile);

                if($this->getContextualOption($context, "EXIF_ROTATION")){
                    require_once(AJXP_INSTALL_PATH."/plugins/editor.diaporama/PThumb.lib.php");
                    $pThumb = new \Pydio\Editor\Image\PThumb($this->getContextualOption($context, "THUMBNAIL_QUALITY"),$this->getContextualOption($context, "EXIF_ROTATION"));
                    $orientation = $pThumb->exiforientation($realFile, false);
                    if ($pThumb->rotationsupported($orientation))
                    {
                        $ajxpNode->image_exif_orientation = $orientation;
                        if ($orientation>4)
                        {
                            $tmp=$height;
                            $height=$width;
                            $width=$tmp;
                        }
                    }
                }

                $ajxpNode->image_type = image_type_to_mime_type($type);
                $ajxpNode->image_width = $width;
                $ajxpNode->image_height = $height;
                $ajxpNode->readable_dimension = $width."px X ".$height."px";
            }
        }
        //$this->logDebug("CURRENT NODE IN EXTRACT IMAGE METADATA ", $ajxpNode);
    }

    /**
     * @param $filename
     * @return bool
     */
    protected function handleMime($filename)
    {
        $mimesAtt = explode(",", $this->getXPath()->query("@mimes")->item(0)->nodeValue);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $mimesAtt);
    }

}

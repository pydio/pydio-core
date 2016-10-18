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
 */
namespace Pydio\Editor\Image;

use Pydio\Access\Core\Exception\FileNotFoundException;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Services\LocalCache;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class IMagickPreviewer
 * Encapsulates calls to Image Magick to extract JPG previews of PDF, PSD, TIFF, etc.
 *
 * @package Pydio\Editor\Image
 */
class IMagickPreviewer extends Plugin
{
    protected $extractAll = false;
    protected $onTheFly = false;
    protected $useOnTheFly = false;

    protected $imagickExtensions = array("pdf", "svg", "tif", "tiff", "psd", "xcf", "eps", "cr2","ai");
    protected $unoconvExtensios = array("xls", "xlt", "xlsx", "xltx", "ods", "doc", "dot", "docx", "dotx", "odt", "ppt", "pptx", "odp", "rtf");

    /**
     * Load the configs passed as parameter. This method will
     * + Parse the config definitions and load the default values
     * + Merge these values with the $configData parameter
     * + Publish their value in the manifest if the global_param is "exposed" to the client.
     * @param array $configsData
     * @return void
     */
    public function loadConfigs($configsData)
    {
        parent::loadConfigs($configsData);
        if (isSet($configsData["UNOCONV"]) && !empty($configsData["UNOCONV"])) {
            // APPEND THE UNOCONV SUPPORTED EXTENSIONS
            $this->manifestDoc->documentElement->setAttribute("mimes", implode(",", array_merge($this->imagickExtensions,$this->unoconvExtensios)));
        } else {
            $this->manifestDoc->documentElement->setAttribute("mimes", implode(",", $this->imagickExtensions));
        }
    }

    /**
     * @param $action
     * @param $httpVars
     * @param $filesVars
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws FileNotFoundException
     */
    public function switchAction($action, $httpVars, $filesVars, \Pydio\Core\Model\ContextInterface $contextInterface)
    {
        $convert = $this->getContextualOption($contextInterface, "IMAGE_MAGICK_CONVERT");
        if (empty($convert)) {
            return ;
        }
        $flyThreshold = 1024*1024*intval($this->getContextualOption($contextInterface, "ONTHEFLY_THRESHOLD"));
        $selection = UserSelection::fromContext($contextInterface, $httpVars);
        $destStreamURL = $selection->currentBaseUrl();

        if ($action == "imagick_data_proxy") {
            $this->extractAll = false;
            $file = $selection->getUniqueNode()->getUrl();
            if(!file_exists($file) || !is_readable($file)){
                throw new FileNotFoundException($file);
            }
            if(isSet($httpVars["all"])) {
                $this->logInfo('Preview', 'Preview content of '.$file, array("files" => $file));
                $this->extractAll = true;
            }

            if (($size = filesize($file)) === false) {
                return ;
            } else {
                if($size > $flyThreshold) $this->useOnTheFly = true;
                else $this->useOnTheFly = false;
            }

            if ($this->extractAll) {
                $node = new AJXP_Node($file);
                Controller::applyHook("node.read", array($node));
            }

            $cache = LocalCache::getItem("imagick_".($this->extractAll?"full":"thumb"), $file, function($masterFile, $targetFile) use ($contextInterface){
                return $this->generateJpegsCallback($contextInterface, $masterFile, $targetFile);
            });
            session_write_close();
            $cacheData = $cache->getData();

            if (!$this->useOnTheFly && $this->extractAll) { // extract all on first view
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $prefix = str_replace(".$ext", "", $cache->getId());
                $files = $this->listExtractedJpg($file, $prefix);
                header("Content-Type: application/json");
                print(json_encode($files));
                return ;
            } else if ($this->extractAll) { // on the fly extract mode
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $prefix = str_replace(".$ext", "", $cache->getId());
                $files = $this->listPreviewFiles($contextInterface, $file, $prefix);
                header("Content-Type: application/json");
                print(json_encode($files));
                return ;
            } else {
                header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
                header("Content-Length: ".strlen($cacheData));
                header('Cache-Control: public');
                header("Pragma:");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                print($cacheData);
                return ;
            }

        } else if ($action == "get_extracted_page" && isSet($httpVars["file"])) {

            $file = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/imagick_full/". InputFilter::decodeSecureMagic($httpVars["file"]);
            if (!is_file($file)) {
                $srcfile = InputFilter::decodeSecureMagic($httpVars["src_file"]);
                if($contextInterface->getRepository()->hasContentFilter()){
                    $contentFilter = $contextInterface->getRepository()->getContentFilter();
                    $srcfile = $contentFilter->filterExternalPath($srcfile);
                }
                $size = filesize($destStreamURL."/".$srcfile);
                if($size > $flyThreshold) $this->useOnTheFly = true;
                else $this->useOnTheFly = false;

                if($this->useOnTheFly) $this->onTheFly = true;
                $this->generateJpegsCallback($contextInterface, $destStreamURL.$srcfile, $file);

            }
            if(!is_file($file)) return ;
            header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
            header("Content-Length: ".filesize($file));
            header('Cache-Control: public');
            readfile($file);

        } else if ($action == "delete_imagick_data" && !$selection->isEmpty()) {
            /*
            $files = $this->listExtractedJpg(AJXP_CACHE_DIR."/".$httpVars["file"]);
            foreach ($files as $file) {
                if(is_file(AJXP_CACHE_DIR."/".$file["file"])) unlink(AJXP_CACHE_DIR."/".$file["file"]);
            }
            */
        }
        
    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param Boolean $copy
     */
    public function deleteImagickCache($oldNode, $newNode = null, $copy = false)
    {
        if($oldNode == null) return;
        $oldFile = $oldNode->getUrl();
        // Should remove imagick cache file
        if(!$this->handleMime($oldFile)) return;
        if ($newNode == null || $copy == false) {

            // Main Thumb
            LocalCache::clearItem("imagick_thumb", $oldFile);

            // Unoconv small PDF
            $thumbCache = LocalCache::getItem("imagick_thumb", $oldFile, false);
            $unoFile = pathinfo($thumbCache->getId(), PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($thumbCache->getId(), PATHINFO_FILENAME)."_unoconv.pdf";
            if(file_exists($unoFile)){
                unlink($unoFile);
            }

            $cache = LocalCache::getItem("imagick_full", $oldFile, false);
            // Unoconv full pdf
            $unoFile = pathinfo($cache->getId(), PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($cache->getId(), PATHINFO_FILENAME)."_unoconv.pdf";
            if(file_exists($unoFile)){
                unlink($unoFile);
            }
            $prefix = str_replace(".".pathinfo($cache->getId(), PATHINFO_EXTENSION), "", $cache->getId());
            // Additional Extracted pages
            $files = $this->listExtractedJpg($oldFile, $prefix);
            $cacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/imagick_full";
            foreach ($files as $file) {
                if(is_file($cacheDir."/".$file["file"])) {
                    unlink($cacheDir."/".$file["file"]);
                }
            }
        }
    }

    /**
     * @param $file
     * @param $prefix
     * @return array
     */
    protected function listExtractedJpg($file, $prefix)
    {
        $n = new AJXP_Node($file);
        $path = $n->getPath();
        $files = array();
        $index = 0;
        while (is_file($prefix."-".$index.".jpg")) {
            $extract = $prefix."-".$index.".jpg";
            list($width, $height, $type, $attr) = @getimagesize($extract);
            $files[] = array(
                "file" => basename($extract),
                "width"=>$width,
                "height"=>$height,
                "rest"  => "/get_extracted_page/".basename($extract).str_replace("%2F", "/", urlencode($path))
            );
            $index ++;
        }
        if (is_file($prefix.".jpg")) {
            $extract = $prefix.".jpg";
            list($width, $height, $type, $attr) = @getimagesize($extract);
            $files[] = array(
                "file" => basename($extract),
                "width"=>$width,
                "height"=>$height,
                "rest"  => "/get_extracted_page/".basename($extract).str_replace("%2F", "/", urlencode($path))
            );
        }
        return $files;
    }

    /**
     * @param ContextInterface $ctx
     * @param $file
     * @param $prefix
     * @return array
     */
    protected function listPreviewFiles(ContextInterface $ctx, $file, $prefix)
    {
        $files = array();
        $index = 0;
        $unoconv =  $this->getContextualOption($ctx, "UNOCONV");
        if (!empty($unoconv)) {
            $officeExt = array('xls', 'xlsx', 'ods', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'rtf');
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array(strtolower($extension), $officeExt)) {
                $unoDoc = $prefix."_unoconv.pdf";
                if(is_file($unoDoc)) $file = $unoDoc;
            }
        }
        $count = $this->countPages($file);
        $n = new AJXP_Node($file);
        $path = $n->getPath();

        while ($index < $count) {
            $extract = $prefix."-".$index.".jpg";
            list($width, $height, $type, $attr) = @getimagesize($extract);
            $files[] = array(
                "file" => basename($extract),
                "width"=>$width,
                "height"=>$height,
                "rest"  => "/get_extracted_page/".basename($extract).str_replace("%2F", "/", urlencode($path))
            );
            $index ++;
        }
        if (is_file($prefix.".jpg")) {
            $extract = $prefix.".jpg";
            list($width, $height, $type, $attr) = @getimagesize($extract);
            $files[] = array(
                "file" => basename($extract),
                "width"=>$width,
                "height"=>$height,
                "rest"  => "/get_extracted_page/".basename($extract).str_replace("%2F", "/", urlencode($path))
            );
        }
        return $files;
    }

    /**
     * @param ContextInterface $ctx
     * @param $masterFile
     * @param $targetFile
     * @return bool
     * @throws PydioException
     */
    public function generateJpegsCallback(ContextInterface $ctx, $masterFile, $targetFile)
    {
        $unoconv =  $this->getContextualOption($ctx, "UNOCONV");
        if (!empty($unoconv)) {
            $officeExt = array('xls', 'xlsx', 'ods', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'rtf');
        } else {
            $unoconv = false;
            $officeExt = [];
        }

        $extension = pathinfo($masterFile, PATHINFO_EXTENSION);
        $node = new AJXP_Node($masterFile);
        $masterFile = $node->getRealFile();

        if (DIRECTORY_SEPARATOR == "\\") {
            $masterFile = str_replace("/", "\\", $masterFile);
        }
        $wrappers = stream_get_wrappers();
        $wrappers_re = '(' . join('|', $wrappers) . ')';
        $isStream = (preg_match( "!^$wrappers_re://!", $targetFile ) === 1);
        if ($isStream) {
            $backToStreamTarget = $targetFile;
            $targetFile = tempnam(ApplicationState::getTemporaryFolder(), "imagick_").".pdf";
        }else{
            $backToStreamTarget = null;
        }
        $workingDir = dirname($targetFile);
        $out = array();
        $return = 0;
        $tmpFileThumb =  str_replace(".$extension", ".jpg", $targetFile);
        if (DIRECTORY_SEPARATOR == "\\") {
            $tmpFileThumb =  str_replace("/", "\\", $tmpFileThumb);
        }
        if (!$this->extractAll) {
            //register_shutdown_function("unlink", $tmpFileThumb);
        } else {
            @set_time_limit(90);
        }
        chdir($workingDir);
        if ($unoconv !== false && in_array(strtolower($extension), $officeExt)) {
            $unoDoc = preg_replace("/(-[0-9]+)?\\.jpg/", "_unoconv.pdf", $tmpFileThumb);
            if (!is_file($unoDoc)  || (is_file($unoDoc) && (filesize($unoDoc) == 0))) {
                $timelimit = 'timeout 60 ';
                if (stripos(PHP_OS, "win") === 0) {
                    $unoconv = $this->pluginConf["UNOCONV"]." -o ".escapeshellarg(basename($unoDoc))." -f pdf ".escapeshellarg($masterFile);
                    $unoconv = $timelimit.$unoconv;
                } else {
                    $unoconv = $timelimit.$unoconv;
                    $unoconv =  "HOME=". ApplicationState::getTemporaryFolder() ." ".$unoconv." --stdout -f pdf ".escapeshellarg($masterFile)." > ".escapeshellarg(basename($unoDoc));
                }
                if(defined('AJXP_LOCALE')){
                    putenv('LC_CTYPE='.AJXP_LOCALE);
                }
                $this->logDebug("Unoconv Command : $unoconv");
                exec($unoconv, $out, $return);
            }
            if (is_file($unoDoc)) {
                $masterFile = basename($unoDoc);
            }
        }

        if ($this->onTheFly) {
            $pageNumber = strrchr($targetFile, "-");
            $pageNumber = str_replace(array(".jpg","-"), "", $pageNumber);
            $pageLimit = "[".$pageNumber."]";
            $this->extractAll = true;
        } else {
            if (!$this->useOnTheFly) {
                $pageLimit = ($this->extractAll?"":"[0]");
            } else {
                $pageLimit = "[0]";
                if($this->extractAll) $tmpFileThumb = str_replace(".jpg", "-0.jpg", $tmpFileThumb);
            }
        }

        $customOptions = $this->getContextualOption($ctx, "IM_CUSTOM_OPTIONS");
        $customEnvPath = $this->getContextualOption($ctx, "ADDITIONAL_ENV_PATH");
        $viewerQuality = $this->getContextualOption($ctx, "IM_VIEWER_QUALITY");
        $thumbQuality = $this->getContextualOption($ctx, "IM_THUMB_QUALITY");
        if (empty($customOptions)) {
            $customOptions = "";
        }
        if (!empty($customEnvPath)) {
            putenv("PATH=".getenv("PATH").":".$customEnvPath);
        }
        $params = $customOptions." ".( $this->extractAll? $viewerQuality : $thumbQuality );
        $cmd = $this->getContextualOption($ctx, "IMAGE_MAGICK_CONVERT")." ".$params." ".escapeshellarg(($masterFile).$pageLimit)." ".escapeshellarg($tmpFileThumb);
        $timelimit = 'timeout 30 ';
        $cmd = $timelimit.$cmd;
        $this->logDebug("IMagick Command : $cmd");
        session_write_close(); // Be sure to give the hand back
        exec($cmd, $out, $return);
        if (is_array($out) && count($out)) {
            throw new PydioException(implode("\n", $out));
        }
        if(!(is_file($tmpFileThumb) || is_file(str_replace(".jpg", "-0.jpg", $tmpFileThumb)))){
            throw new PydioException("Error while converting PDF file to JPG thumbnail. Return code '$return'. Command used '".$this->getContextualOption($ctx, "IMAGE_MAGICK_CONVERT")."': is the binary at the correct location? Is the server allowed to use it?");
        }
        if (!$this->extractAll) {
            rename($tmpFileThumb, $targetFile);
            if ($isStream) {
                $this->logDebug("Copy preview file to remote", $backToStreamTarget);
                copy($targetFile, $backToStreamTarget);
                unlink($targetFile);
            }
        } else {
            if ($isStream) {
                if (is_file(str_replace(".$extension", "", $targetFile))) {
                    $targetFile = str_replace(".$extension", "", $targetFile);
                }
                if (is_file($targetFile)) {
                    $this->logDebug("Copy preview file to remote", $backToStreamTarget);
                    copy($targetFile, $backToStreamTarget);
                    unlink($targetFile);
                }
                $this->logDebug("Searching for ", str_replace(".jpg", "-0.jpg", $tmpFileThumb));
                $i = 0;
                while (file_exists(str_replace(".jpg", "-$i.jpg", $tmpFileThumb))) {
                    $page = str_replace(".jpg", "-$i.jpg", $tmpFileThumb);
                    $remote_page = str_replace(".$extension", "-$i.jpg", $backToStreamTarget);
                    $this->logDebug("Copy preview file to remote", $remote_page);
                    copy($page, $remote_page);
                    unlink($page);
                    $i++;
                }
            }
        }
        return true;
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

    /**
     * @param $file
     * @return int|null
     */
    protected function countPages($file)
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if($ext != "pdf") return 20;
        if(!file_exists($file))return null;
        if (!$fp = @fopen($file,"r"))return null;
        $max=0;
        while (!feof($fp)) {
            $line = fgets($fp, 255);
            if (preg_match('/\/Count [0-9]+/', $line, $matches)) {
                            preg_match('/[0-9]+/',$matches[0], $matches2);
                            if ($max<$matches2[0]) $max=$matches2[0];
            }
        }
        fclose($fp);
        return (int) $max;
    }


}

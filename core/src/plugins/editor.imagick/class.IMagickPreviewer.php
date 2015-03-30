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

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsulates calls to Image Magick to extract JPG previews of PDF, PSD, TIFF, etc.
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class IMagickPreviewer extends AJXP_Plugin
{
    protected $extractAll = false;
    protected $onTheFly = false;
    protected $useOnTheFly = false;

    protected $imagickExtensions = array("pdf", "svg", "tif", "tiff", "psd", "xcf", "eps", "cr2","ai");
    protected $unoconvExtensios = array("xls", "xlt", "xlsx", "xltx", "ods", "doc", "dot", "docx", "dotx", "odt", "ppt", "pptx", "odp", "rtf");

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

    public function switchAction($action, $httpVars, $filesVars)
    {
        if(!isSet($this->actions[$action])) return false;

        $repository = ConfService::getRepository();
        if (!$repository->detectStreamWrapper(true)) {
            return false;
        }
        $convert = $this->getFilteredOption("IMAGE_MAGICK_CONVERT");
        if (empty($convert)) {
            return false;
        }
        $streamData = $repository->streamData;
        $destStreamURL = $streamData["protocol"]."://".$repository->getId();
        $flyThreshold = 1024*1024*intval($this->getFilteredOption("ONTHEFLY_THRESHOLD", $repository->getId()));
        $selection = new UserSelection($repository);
        $selection->initFromHttpVars($httpVars);

        if ($action == "imagick_data_proxy") {
            $this->extractAll = false;
            $file = $selection->getUniqueFile();
            if(isSet($httpVars["all"])) {
                $this->logInfo('Preview', 'Preview content of '.$file);
                $this->extractAll = true;
            }

            if (($size = filesize($destStreamURL.$file)) === false) {
                return false;
            } else {
                if($size > $flyThreshold) $this->useOnTheFly = true;
                else $this->useOnTheFly = false;
            }

            if ($this->extractAll) {
                $node = new AJXP_Node($destStreamURL.$file);
                AJXP_Controller::applyHook("node.read", array($node));
            }

            $cache = AJXP_Cache::getItem("imagick_".($this->extractAll?"full":"thumb"), $destStreamURL.$file, array($this, "generateJpegsCallback"));
            $cacheData = $cache->getData();

            if (!$this->useOnTheFly && $this->extractAll) { // extract all on first view
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $prefix = str_replace(".$ext", "", $cache->getId());
                $files = $this->listExtractedJpg($destStreamURL.$file, $prefix);
                header("Content-Type: application/json");
                print(json_encode($files));
                return false;
            } else if ($this->extractAll) { // on the fly extract mode
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $prefix = str_replace(".$ext", "", $cache->getId());
                $files = $this->listPreviewFiles($destStreamURL.$file, $prefix);
                header("Content-Type: application/json");
                print(json_encode($files));
                return false;
            } else {
                header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
                header("Content-Length: ".strlen($cacheData));
                header('Cache-Control: public');
                header("Pragma:");
                header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                print($cacheData);
                return false;
            }

        } else if ($action == "get_extracted_page" && isSet($httpVars["file"])) {
            $file = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/imagick_full/".AJXP_Utils::decodeSecureMagic($httpVars["file"]);
            if (!is_file($file)) {
                $srcfile = AJXP_Utils::decodeSecureMagic($httpVars["src_file"]);
                if($repository->hasContentFilter()){
                    $contentFilter = $repository->getContentFilter();
                    $srcfile = $contentFilter->filterExternalPath($srcfile);
                }
                $size = filesize($destStreamURL."/".$srcfile);
                if($size > $flyThreshold) $this->useOnTheFly = true;
                else $this->useOnTheFly = false;

                if($this->useOnTheFly) $this->onTheFly = true;
                $this->generateJpegsCallback($destStreamURL.$srcfile, $file);

            }
            if(!is_file($file)) return false;
            header("Content-Type: image/jpeg; name=\"".basename($file)."\"");
            header("Content-Length: ".filesize($file));
            header('Cache-Control: public');
            readfile($file);
            exit(1);
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
     * @param AJXP_Node $oldNode
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
            AJXP_Cache::clearItem("imagick_thumb", $oldFile);
            $cache = AJXP_Cache::getItem("imagick_full", $oldFile, false);
            $prefix = str_replace(".".pathinfo($cache->getId(), PATHINFO_EXTENSION), "", $cache->getId());
            $files = $this->listExtractedJpg($oldFile, $prefix);
            foreach ($files as $file) {
                if(is_file((defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/".$file["file"])) unlink(AJXP_CACHE_DIR."/".$file["file"]);
            }
        }
    }

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

    protected function listPreviewFiles($file, $prefix)
    {
        $files = array();
        $index = 0;
        $unoconv =  $this->getFilteredOption("UNOCONV");
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

    public function generateJpegsCallback($masterFile, $targetFile)
    {
        $unoconv =  $this->getFilteredOption("UNOCONV");
        if (!empty($unoconv)) {
            $officeExt = array('xls', 'xlsx', 'ods', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'rtf');
        } else {
            $unoconv = false;
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
            $targetFile = tempnam(AJXP_Utils::getAjxpTmpDir(), "imagick_").".pdf";
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
            if (!is_file($unoDoc)) {
                if (stripos(PHP_OS, "win") === 0) {
                    $unoconv = $this->pluginConf["UNOCONV"]." -o ".escapeshellarg(basename($unoDoc))." -f pdf ".escapeshellarg($masterFile);
                } else {
                    $unoconv =  "HOME=/tmp ".$unoconv." --stdout -f pdf ".escapeshellarg($masterFile)." > ".escapeshellarg(basename($unoDoc));
                }
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

        $customOptions = $this->getFilteredOption("IM_CUSTOM_OPTIONS");
        $customEnvPath = $this->getFilteredOption("ADDITIONAL_ENV_PATH");
        $viewerQuality = $this->getFilteredOption("IM_VIEWER_QUALITY");
        $thumbQuality = $this->getFilteredOption("IM_THUMB_QUALITY");
        if (empty($customOptions)) {
            $customOptions = "";
        }
        if (!empty($customEnvPath)) {
            putenv("PATH=".getenv("PATH").":".$customEnvPath);
        }
        $params = $customOptions." ".( $this->extractAll? $viewerQuality : $thumbQuality );
        $cmd = $this->getFilteredOption("IMAGE_MAGICK_CONVERT")." ".escapeshellarg(($masterFile).$pageLimit)." ".$params." ".escapeshellarg($tmpFileThumb);
        $this->logDebug("IMagick Command : $cmd");
        session_write_close(); // Be sure to give the hand back
        exec($cmd, $out, $return);
        if (is_array($out) && count($out)) {
            throw new AJXP_Exception(implode("\n", $out));
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

    protected function handleMime($filename)
    {
        $mimesAtt = explode(",", $this->xPath->query("@mimes")->item(0)->nodeValue);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $mimesAtt);
    }

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

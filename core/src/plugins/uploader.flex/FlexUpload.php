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
namespace Pydio\Uploader\Processor;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\StatHelper;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Legacy Flash plugin for upload
 * @package AjaXplorer_Plugins
 * @subpackage Uploader
 */
class FlexUpload extends Plugin
{
    private static $active = false;

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function getTemplate(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response){
        
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $confMaxSize = StatHelper::convertBytes(ConfService::getContextConf($ctx, "UPLOAD_MAX_SIZE", "uploader"));
        $UploadMaxSize = min(StatHelper::convertBytes(ini_get('upload_max_filesize')), StatHelper::convertBytes(ini_get('post_max_size')));
        if($confMaxSize != 0) $UploadMaxSize = min ($UploadMaxSize, $confMaxSize);
        $confTotalNumber = ConfService::getContextConf($ctx, "UPLOAD_MAX_NUMBER", "uploader");
        $confTotalSize = ConfService::getContextConf($ctx, "UPLOAD_MAX_SIZE_TOTAL", "uploader");
        $confTotalNumber = ConfService::getContextConf($ctx, "UPLOAD_MAX_NUMBER", "uploader");
        $maxLength = ConfService::getContextConf($ctx, "NODENAME_MAX_LENGTH");

        $FlashVar = '&totalUploadSize='.$confTotalSize.'&fileSizeLimit='.$UploadMaxSize.'&maxFileNumber='.$confTotalNumber.'&maxFilenameLength='.$maxLength;
        $pluginConfigs = $this->getConfigs();
        include($this->getBaseDir()."/flash_tpl.html");

    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function preProcess(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response)
    {
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");

        //------------------------------------------------------------
        // SPECIAL HANDLING FOR FLEX UPLOADER RIGHTS FOR THIS ACTION
        //------------------------------------------------------------
        if (UsersService::usersEnabled()) {
            $loggedUser = $ctx->getUser();
            if ($request->getAttribute("action") == "upload" &&
                ($loggedUser == null || !$loggedUser->canWrite($ctx->getRepositoryId().""))
                && isSet($request->getUploadedFiles()['Filedata'])) {
                $response = $response->withStatus(410, "Not authorized");
                return;
            }
        }

        $fileVars = $request->getUploadedFiles();
        if (isSet($fileVars["Filedata"])) {
            self::$active = true;
            $httpVars = $request->getParsedBody();
            $this->logDebug("Dir before base64", $httpVars);
            $httpVars["dir"] = base64_decode(urldecode($httpVars["dir"]));
            $request = $request->withParsedBody($httpVars);

            $existingUp = $_FILES["Filedata"];
            $filename = $httpVars["Filename"];
            // Rebuild UploadedFile object
            $request = $request->withUploadedFiles(["userfile_0" => new \Zend\Diactoros\UploadedFile($existingUp["tmp_name"], $existingUp["size"], $existingUp["error"], $filename)]);
            $this->logDebug("Setting FlexProc active");
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Exception
     */
    public function postProcess(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response)
    {
        if (!self::$active) {
            return;
        }
        $result = $request->getAttribute("upload_process_result");
        if (isSet($result["SUCCESS"]) && $result["SUCCESS"] === true) {
            $response = new \Zend\Diactoros\Response\EmptyResponse(200);
            if (iSset($result["UPDATED_NODE"])) {
                Controller::applyHook("node.change", array($result["UPDATED_NODE"], $result["UPDATED_NODE"], false));
            } else {
                Controller::applyHook("node.change", array(null, $result["CREATED_NODE"], false));
            }
        } else if (isSet($result["ERROR"]) && is_array($result["ERROR"])) {
            $code = $result["ERROR"]["CODE"];
            $message = $result["ERROR"]["MESSAGE"];
            $response = $response->withStatus($code, $message);
        }
    }
}

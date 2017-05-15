<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Access\Driver\DataProvider\Provisioning;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Filter\AJXP_PermissionMask;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Core\Model\Repository;
use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Message\XMLDocMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Utils\XMLHelper;
use Pydio\Tests\AbstractTest;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class RepositoriesManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class RepositoriesManager extends AbstractManager
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     * @throws PydioException
     * @throws \Exception
     */
    public function repositoriesActions(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface){

        $action     = $requestInterface->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        $httpVars   = $requestInterface->getParsedBody();
        $mess       = LocaleService::getMessages();
        $currentAdminBasePath = "/";
        $loggedUser = $ctx->getUser();
        if ($loggedUser!=null && $loggedUser->getGroupPath()!=null) {
            $currentAdminBasePath = $loggedUser->getGroupPath();
        }

        switch ($action){

            // REPOSITORIES
            case "get_drivers_definition":

                $buffer = "<drivers allowed='".($this->currentUserIsGroupAdmin() ? "false" : "true")."'>";
                $buffer .= XMLFilter::resolveKeywords(self::availableDriversToXML("param", "", true));
                $buffer .= "</drivers>";
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new XMLDocMessage($buffer)));

                break;

            case "get_templates_definition":

                $buffer = "<repository_templates>";
                $count = 0;
                $repositories = RepositoryService::listRepositoriesWithCriteria(array(
                    "isTemplate" => '1'
                ), $count);
                foreach ($repositories as $repo) {
                    if(!$repo->isTemplate()) continue;
                    $repoId = $repo->getUniqueId();
                    $repoLabel = $repo->getDisplay();
                    $repoType = $repo->getAccessType();
                    $buffer .= "<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">";
                    foreach ($repo->getOptionsDefined() as $optionName) {
                        $buffer .= "<option name=\"$optionName\"/>";
                    }
                    $buffer .= "</template>";
                }
                $buffer .= "</repository_templates>";
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new XMLDocMessage($buffer)));

                break;

            case "create_repository" :

                $repDef = $httpVars;
                $isTemplate = isSet($httpVars["sf_checkboxes_active"]);
                unset($repDef["get_action"]);
                unset($repDef["sf_checkboxes_active"]);
                if (isSet($httpVars["json_data"])) {
                    $repDef = json_decode(InputFilter::magicDequote($httpVars["json_data"]), true);
                    $options = $repDef["DRIVER_OPTIONS"];
                } else {
                    $options = array();
                    $this->parseParameters($ctx, $repDef, $options, true);
                }
                if (count($options)) {
                    $repDef["DRIVER_OPTIONS"] = $options;
                    unset($repDef["DRIVER_OPTIONS"]["AJXP_GROUP_PATH_PARAMETER"]);
                    if(isSet($options["AJXP_SLUG"])){
                        $repDef["AJXP_SLUG"] = $options["AJXP_SLUG"];
                        unset($repDef["DRIVER_OPTIONS"]["AJXP_SLUG"]);
                    }
                }
                if (strstr($repDef["DRIVER"], "ajxp_template_") !== false) {
                    $templateId = substr($repDef["DRIVER"], 14);
                    $templateRepo = RepositoryService::getRepositoryById($templateId);
                    $newRep = $templateRepo->createTemplateChild($repDef["DISPLAY"], $repDef["DRIVER_OPTIONS"], $ctx->getUser()->getId());
                    if(isSet($repDef["AJXP_SLUG"])){
                        $newRep->setSlug($repDef["AJXP_SLUG"]);
                    }
                } else {
                    if ($this->currentUserIsGroupAdmin()) {
                        throw new \Exception("You are not allowed to create a workspace from a driver. Use a template instead.");
                    }
                    $pServ = PluginsService::getInstance($ctx);
                    $driver = $pServ->getPluginByTypeName("access", $repDef["DRIVER"]);

                    $newRep = RepositoryService::createRepositoryFromArray(0, $repDef);
                    $testFile = $driver->getBaseDir()."/test.".$newRep->getAccessType()."Access.php";
                    if (!$isTemplate && is_file($testFile)) {
                        //chdir(AJXP_TESTS_FOLDER."/plugins");
                        $className = "\\Pydio\\Tests\\".$newRep->getAccessType()."AccessTest";
                        if (!class_exists($className))
                            include($testFile);
                        $class = new $className();
                        $result = $class->doRepositoryTest($newRep);
                        if (!$result) {
                            throw new PydioException($class->failedInfo);
                        }
                    }
                    // Apply default metasource if any
                    if ($driver != null && $driver->getConfigs()!=null ) {
                        $confs = $driver->getConfigs();
                        if (!empty($confs["DEFAULT_METASOURCES"])) {
                            $metaIds = InputFilter::parseCSL($confs["DEFAULT_METASOURCES"]);
                            $metaSourceOptions = array();
                            foreach ($metaIds as $metaID) {
                                $metaPlug = $pServ->getPluginById($metaID);
                                if($metaPlug == null) continue;
                                $pNodes = $metaPlug->getManifestRawContent("//param[@default]", "nodes");
                                $defaultParams = array();
                                /** @var \DOMElement $domNode */
                                foreach ($pNodes as $domNode) {
                                    $defaultParams[$domNode->getAttribute("name")] = $domNode->getAttribute("default");
                                }
                                $metaSourceOptions[$metaID] = $defaultParams;
                            }
                            $newRep->addOption("META_SOURCES", $metaSourceOptions);
                        }
                    }
                }

                if ($this->repositoryExists($newRep->getDisplay())) {
                    throw new PydioException($mess["ajxp_conf.50"]);
                }
                if ($isTemplate) {
                    $newRep->isTemplate = true;
                }
                if ($this->currentUserIsGroupAdmin()) {
                    $newRep->setGroupPath($ctx->getUser()->getGroupPath());
                } else if (!empty($options["AJXP_GROUP_PATH_PARAMETER"])) {
                    $value = InputFilter::securePath(rtrim($currentAdminBasePath, "/") . "/" . ltrim($options["AJXP_GROUP_PATH_PARAMETER"], "/"));
                    $newRep->setGroupPath($value);
                }

                $res = RepositoryService::addRepository($newRep);

                if ($res == -1) {
                    throw new PydioException($mess["ajxp_conf.51"]);

                }

                $defaultRights = $newRep->getDefaultRight();
                if(!empty($defaultRights)){
                    $groupRole = RolesService::getOrCreateRole("AJXP_GRP_" . $currentAdminBasePath, $currentAdminBasePath);
                    $groupRole->setAcl($newRep->getId(), $defaultRights);
                }
                $loggedUser = $ctx->getUser();
                $loggedUser->getPersonalRole()->setAcl($newRep->getUniqueId(), "rw");
                $loggedUser->recomputeMergedRole();
                $loggedUser->save("superuser");
                AuthService::updateSessionUser($loggedUser);

                $message = new UserMessage($mess["ajxp_conf.52"]);
                $reload = new ReloadMessage("", $newRep->getUniqueId());
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([$message, $reload]));

                break;

            case "post_repository":
                $jsonDataCreateWorkspace = json_decode($httpVars["payload"], true);
                if ($jsonDataCreateWorkspace === null) {
                    throw new PydioException("Invalid JSON !!");
                }
                if(!isSet($jsonDataCreateWorkspace["isTemplate"])) {
                    $jsonDataCreateWorkspace["isTemplate"] = false;
                }
                if(!isSet($jsonDataCreateWorkspace["id"])) {
                    $jsonDataCreateWorkspace["id"] = 0;
                }
                if(!isSet($jsonDataCreateWorkspace["parameters"]["CREATE"])) {
                    $jsonDataCreateWorkspace["parameters"]["CREATE"] = true;
                }
                $repo = new Repository($jsonDataCreateWorkspace["id"], $jsonDataCreateWorkspace["display"], $jsonDataCreateWorkspace["accessType"]);
                foreach($jsonDataCreateWorkspace["parameters"] as $name => $value) {
                    $repo->addOption($name, $value);
                }
                $pluginService = PluginsService::getInstance($ctx);
                $driver = $pluginService->getPluginByTypeName("access", $jsonDataCreateWorkspace["accessType"]);
                $testFile = $driver->getBaseDir()."/test.".$repo->getAccessType()."Access.php";
                if (!$jsonDataCreateWorkspace["isTemplate"] && is_file($testFile)) {
                    $className = "\\Pydio\\Tests\\".$repo->getAccessType()."AccessTest";
                    if (!class_exists($className))
                        include($testFile);
                    /** @var AbstractTest $class */
                    $class = new $className();
                    $result = $class->doRepositoryTest($repo);
                    if (!$result) {
                        throw new PydioException($class->failedInfo);
                    }
                }
                if ($driver != null && $driver->getConfigs() != null) {
                    $arrayDefaultMetasources = array();
                    $arrayPluginToOverWrite = array();
                    $metaSourceOptions = array();
                    $configsDriver = $driver->getConfigs();
                    if (!empty($configsDriver["DEFAULT_METASOURCES"])) {
                        $arrayDefaultMetasources = InputFilter::parseCSL($configsDriver["DEFAULT_METASOURCES"]);
                        foreach ($arrayDefaultMetasources as $metaID) {
                            $metaPlug = $pluginService->getPluginById($metaID);
                            if($metaPlug == null) continue;
                            $pNodes = $metaPlug->getManifestRawContent("//param[@default]", "nodes");
                            $defaultParams = array();
                            /** @var \DOMElement $domNode */
                            foreach ($pNodes as $domNode) {
                                $defaultParams[$domNode->getAttribute("name")] = $domNode->getAttribute("default");
                            }
                            $metaSourceOptions[$metaID] = $defaultParams;
                        }
                    }
                    if(isSet($jsonDataCreateWorkspace["features"])) {
                        foreach($arrayDefaultMetasources as $defaultPluginName) {
                            foreach($jsonDataCreateWorkspace["features"] as $pluginName => $arrayPluginValue) {
                                if ($defaultPluginName === $pluginName) {
                                    $arrayPluginToOverWrite[$pluginName] = $arrayPluginValue;
                                    unset($jsonDataCreateWorkspace["features"][$pluginName]);
                                }
                            }
                        }
                        $arrayPluginToAdd = $jsonDataCreateWorkspace["features"];
                        foreach($arrayPluginToOverWrite as $pluginName => $arrayPlugin) {
                            if(!empty($arrayPlugin)) {
                                foreach($arrayPlugin as $name => $value) {
                                    $metaSourceOptions[$pluginName][$name] = $value;
                                }
                            }
                        }
                        $metaSourceOptions = array_merge($metaSourceOptions, $arrayPluginToAdd);
                    }
                    $repo->addOption("META_SOURCES", $metaSourceOptions);
                }
                if ($this->repositoryExists($repo->getDisplay())) {
                    throw new PydioException($mess["ajxp_conf.50"]);
                }
                if ($jsonDataCreateWorkspace["isTemplate"]) {
                    $repo->isTemplate = true;
                }
                if ($this->currentUserIsGroupAdmin()) {
                    $repo->setGroupPath($ctx->getUser()->getGroupPath());
                } else if (!empty($options["AJXP_GROUP_PATH_PARAMETER"])) {
                    $value = InputFilter::securePath(rtrim($currentAdminBasePath, "/") . "/" . ltrim($options["AJXP_GROUP_PATH_PARAMETER"], "/"));
                    $repo->setGroupPath($value);
                }
                $res = RepositoryService::addRepository($repo);
                if ($res == -1) {
                    throw new PydioException($mess["ajxp_conf.51"]);
                }
                $defaultRights = $repo->getDefaultRight();
                if(!empty($defaultRights)){
                    $groupRole = RolesService::getOrCreateRole("AJXP_GRP_" . $currentAdminBasePath, $currentAdminBasePath);
                    $groupRole->setAcl($repo->getId(), $defaultRights);
                }
                $loggedUser = $ctx->getUser();
                $loggedUser->getPersonalRole()->setAcl($repo->getUniqueId(), "rw");
                $loggedUser->recomputeMergedRole();
                $loggedUser->save("superuser");
                AuthService::updateSessionUser($loggedUser);
                #@TODO: create different message because this action is doing via API
                $message = new UserMessage($mess["ajxp_conf.52"]);
                $reload = new ReloadMessage("", $repo->getUniqueId());
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([$message, $reload]));
                break;

            case "patch_repository":
                $jsonDataEditWorkspace = json_decode($httpVars["payload"], true);
                if ($jsonDataEditWorkspace === null) {
                    throw new PydioException("Invalid JSON !!");
                }
                $workspaceId = $httpVars["workspaceId"];
                $repo = RepositoryService::findRepositoryByIdOrAlias($workspaceId);
                if ($repo === null) {
                    throw new PydioException("Workspace not found !!");
                }
                foreach ($jsonDataEditWorkspace as $name => $value) {
                    if($name !== "parameters" && $name !== "features") {
                        $repo->$name = $value;
                    }
                }
                foreach($jsonDataEditWorkspace["parameters"] as $name => $value) {
                    $repo->addOption($name, $value);
                }
                $pluginService = PluginsService::getInstance($ctx);
                $driver = $pluginService->getPluginByTypeName("access", $repo->getAccessType());
                if ($driver != null && $driver->getConfigs() != null) {
                    $arrayPluginToOverWrite = array();
                    $arrayWorkspaceMetasources = $repo->getSafeOption("META_SOURCES");
                    if(isSet($jsonDataEditWorkspace["features"])) {
                        foreach($arrayWorkspaceMetasources as $metaSourcePluginName => $metaSourcePluginArray) {
                            foreach($jsonDataEditWorkspace["features"] as $pluginName => $arrayPluginValue) {
                                if ($metaSourcePluginName === $pluginName) {
                                    $arrayPluginToOverWrite[$pluginName] = $arrayPluginValue;
                                    unset($jsonDataEditWorkspace["features"][$pluginName]);
                                }
                            }
                        }
                        $arrayPluginToAdd = $jsonDataEditWorkspace["features"];
                        foreach($arrayPluginToOverWrite as $pluginName => $arrayPlugin) {
                            if(!empty($arrayPlugin)) {
                                foreach($arrayPlugin as $name => $value) {
                                    $arrayWorkspaceMetasources[$pluginName][$name] = $value;
                                }
                            }
                        }
                        $arrayWorkspaceMetasources = array_merge($arrayWorkspaceMetasources, $arrayPluginToAdd);
                    }
                    $repo->addOption("META_SOURCES", $arrayWorkspaceMetasources);
                }
                RepositoryService::replaceRepository($workspaceId, $repo);
                $message = new UserMessage("Workspace successfully edited !!");
                $reload = new ReloadMessage("", $repo->getUniqueId());
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([$message, $reload]));
                break;

            case "edit_repository_label" :
            case "edit_repository_data" :

                $repId                  = $httpVars["repository_id"];
                $repo                   = RepositoryService::getRepositoryById($repId);
                $initialDefaultRights   = $repo->getDefaultRight();

                if(!$repo->isWriteable()){

                    if (isSet($httpVars["permission_mask"]) && !empty($httpVars["permission_mask"])){

                        $mask = json_decode($httpVars["permission_mask"], true);
                        $rootGroup = RolesService::getRole("AJXP_GRP_/");
                        if(count($mask)){
                            $perm = new AJXP_PermissionMask($mask);
                            $rootGroup->setMask($repId, $perm);
                        }else{
                            $rootGroup->clearMask($repId);
                        }
                        RolesService::updateRole($rootGroup);

                        $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new UserMessage("The permission mask was updated for this workspace")));
                        break;

                    }else{

                        throw new PydioException("This workspace is not writeable. Please edit directly the conf/bootstrap_repositories.php file.");

                    }
                }

                $res = 0;
                if (isSet($httpVars["newLabel"])) {
                    $newLabel = InputFilter::sanitize(InputFilter::securePath($httpVars["newLabel"]), InputFilter::SANITIZE_HTML);
                    if ($this->repositoryExists($newLabel)) {
                        throw new PydioException($mess["ajxp_conf.50"]);
                    }
                    $repo->setDisplay($newLabel);
                    $res = RepositoryService::replaceRepository($repId, $repo);
                } else {
                    $options = array();
                    $existing = $repo->getOptionsDefined();
                    $existingValues = array();
                    if(!$repo->isTemplate()){
                        foreach($existing as $exK) {
                            $existingValues[$exK] = $repo->getSafeOption($exK);
                        }
                    }
                    $this->parseParameters($ctx, $httpVars, $options, true, $existingValues);
                    if (count($options)) {
                        foreach ($options as $key=>$value) {
                            if ($key == "AJXP_SLUG") {
                                $repo->setSlug($value);
                                continue;
                            } else if ($key == "WORKSPACE_LABEL" || $key == "TEMPLATE_LABEL"){
                                $newLabel = InputFilter::sanitize($value, InputFilter::SANITIZE_HTML);
                                if($repo->getDisplay() != $newLabel){
                                    if ($this->repositoryExists($newLabel)) {
                                        throw new \Exception($mess["ajxp_conf.50"]);
                                    }else{
                                        $repo->setDisplay($newLabel);
                                    }
                                }
                            } elseif ($key == "AJXP_GROUP_PATH_PARAMETER") {
                                $value = InputFilter::securePath(rtrim($currentAdminBasePath, "/") . "/" . ltrim($value, "/"));
                                $repo->setGroupPath($value);
                                continue;
                            }
                            $repo->addOption($key, $value);
                        }
                    }
                    if($repo->isTemplate()){
                        foreach($existing as $definedOption){
                            if($definedOption == "META_SOURCES" || $definedOption == "CREATION_TIME" || $definedOption == "CREATION_USER"){
                                continue;
                            }
                            if(!isSet($options[$definedOption]) && isSet($repo->options[$definedOption])){
                                unset($repo->options[$definedOption]);
                            }
                        }
                    }
                    /*
                     * THIS SEEM TO BE DUPLICATED LOWER IN THE CODE!
                    if ($repo->getContextOption($ctx, "DEFAULT_RIGHTS")) {
                        $gp = $repo->getGroupPath();
                        if (empty($gp) || $gp == "/") {
                            $defRole = RolesService::getRole("AJXP_GRP_/");
                        } else {
                            $defRole = RolesService::getOrCreateRole("AJXP_GRP_" . $gp, $currentAdminBasePath);
                        }
                        if ($defRole !== false) {
                            $defRole->setAcl($repId, $repo->getContextOption($ctx, "DEFAULT_RIGHTS"));
                            RolesService::updateRole($defRole);
                        }
                    }
                    */
                    if (is_file(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php")) {
                        chdir(AJXP_TESTS_FOLDER."/plugins");
                        include(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php");
                        $className = "ajxp_".$repo->getAccessType();
                        /** @var AbstractTest $class */
                        $class = new $className();
                        $result = $class->doRepositoryTest($repo);
                        if (!$result) {
                            throw new PydioException($class->failedInfo);
                        }
                    }

                    $rootGroup = RolesService::getOrCreateRole("AJXP_GRP_" . $currentAdminBasePath, $currentAdminBasePath);
                    if (isSet($httpVars["permission_mask"]) && !empty($httpVars["permission_mask"])){
                        $mask = json_decode($httpVars["permission_mask"], true);
                        if(count($mask)){
                            $perm = new AJXP_PermissionMask($mask);
                            $rootGroup->setMask($repId, $perm);
                        }else{
                            $rootGroup->clearMask($repId);
                        }
                        RolesService::updateRole($rootGroup);
                    }
                    $defaultRights = $repo->getDefaultRight();
                    if($defaultRights != $initialDefaultRights){
                        $currentDefaultRights = $rootGroup->getAcl($repId);
                        if(!empty($defaultRights) || !empty($currentDefaultRights)){
                            $rootGroup->setAcl($repId, empty($defaultRights) ? "" : $defaultRights);
                            RolesService::updateRole($rootGroup);
                        }
                    }
                    RepositoryService::replaceRepository($repId, $repo);
                }
                if ($res == -1) {
                    throw new PydioException($mess["ajxp_conf.53"]);
                }

                $chunks = [];
                $chunks[] = new UserMessage($mess["ajxp_conf.54"]);
                if (isSet($httpVars["newLabel"])) {
                    $chunks[] = new ReloadMessage("", $repId);
                }
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream($chunks));

                break;

            case "edit_repository" :

                if(isSet($httpVars["workspaceId"])){
                    $repId = $httpVars["workspaceId"];
                }else{
                    $repId = $httpVars["repository_id"];
                }
                $format = isSet($httpVars["format"]) && $httpVars["format"] == "json" ? "json" : "xml";

                $repository = RepositoryService::findRepositoryByIdOrAlias($repId);
                if ($repository == null) {
                    throw new \Exception("Cannot find workspace with id $repId");
                }
                if ($ctx->hasUser() && !$ctx->getUser()->canAdministrate($repository)) {
                    throw new \Exception("You are not allowed to edit this workspace!");
                }
                $pServ = PluginsService::getInstance($ctx);
                /** @var AbstractAccessDriver $plug */
                $plug = $pServ->getPluginById("access.".$repository->getAccessType());
                if ($plug == null) {
                    throw new \Exception("Cannot find access driver (".$repository->getAccessType().") for workspace!");
                }
                $slug = $repository->getSlug();
                if ($slug == "" && $repository->isWriteable()) {
                    $repository->setSlug();
                    RepositoryService::replaceRepository($repId, $repository);
                }
                $ctxUser = $ctx->getUser();
                if ($ctxUser!=null && $ctxUser->getGroupPath() != null) {
                    $rgp = $repository->getGroupPath();
                    if($rgp == null) $rgp = "/";
                    if (strlen($rgp) < strlen($ctxUser->getGroupPath())) {
                        $repository->setWriteable(false);
                    }
                }

                $definitions = $plug->getConfigsDefinitions();
                if($format === "json"){
                    $data = $this->serializeRepositoryToJSON($ctx, $repository, $definitions, $currentAdminBasePath);
                    if(isSet($httpVars["load_fill_values"]) && $httpVars["load_fill_values"] === "true"){
                        $data["PARAMETERS_INFO"] = $this->serializeRepositoryDriverInfos($pServ, $format, $plug, $repository);
                    }
                    $responseInterface = new JsonResponse($data);
                }else{
                    $buffer = "<admin_data>";
                    $buffer .= $this->serializeRepositoryToXML($ctx, $repository, $definitions, $currentAdminBasePath);
                    $buffer .= $this->serializeRepositoryDriverInfos($pServ, $format, $plug, $repository);
                    $buffer .= "</admin_data>";
                    $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new XMLDocMessage($buffer)));
                }

                break;

            case "meta_source_add" :

                $repId      = InputFilter::sanitize(isSet($httpVars["workspaceId"]) ? $httpVars["workspaceId"] : $httpVars["repository_id"]);
                $metaId     = InputFilter::sanitize(isSet($httpVars["metaId"]) ? $httpVars["metaId"] : $httpVars["new_meta_source"]);
                $repo       = RepositoryService::findRepositoryByIdOrAlias($repId);

                if (!is_object($repo)) {
                    throw new PydioException("Invalid workspace id! $repId");
                }
                list($type, $name) = explode(".", $metaId);
                if(PluginsService::findPluginWithoutCtxt($type, $name) === false){
                    throw new PydioException("Cannot find plugin with id $metaId");
                }
                if(isSet($httpVars["request_body"])){
                    $options = $httpVars["request_body"];
                }else if (isSet($httpVars["json_data"])) {
                    $options = json_decode(InputFilter::magicDequote($httpVars["json_data"]), true);
                } else {
                    $options = array();
                    $this->parseParameters($ctx, $httpVars, $options, true);
                }

                $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                if (is_array($repoOptions) && isSet($repoOptions[$metaId])) {
                    throw new PydioException($mess["ajxp_conf.55"]);
                }
                if (!is_array($repoOptions)) {
                    $repoOptions = array();
                }
                $repoOptions[$metaId] = $options;
                uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                $repo->addOption("META_SOURCES", $repoOptions);
                RepositoryService::replaceRepository($repId, $repo);

                $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new UserMessage($mess["ajxp_conf.56"])));

                break;

            case "meta_source_delete" :

                $repId        = InputFilter::sanitize(isSet($httpVars["workspaceId"]) ? $httpVars["workspaceId"] : $httpVars["repository_id"]);
                $metaSourceId = InputFilter::sanitize(isSet($httpVars["metaId"]) ? $httpVars["metaId"] : $httpVars["plugId"]);
                $repo         = RepositoryService::findRepositoryByIdOrAlias($repId);
                if (!is_object($repo)) {
                    throw new PydioException("Invalid workspace id! $repId");
                }

                $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                if (is_array($repoOptions) && array_key_exists($metaSourceId, $repoOptions)) {
                    unset($repoOptions[$metaSourceId]);
                    uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                    $repo->addOption("META_SOURCES", $repoOptions);
                    RepositoryService::replaceRepository($repId, $repo);
                }else{
                    throw new PydioException("Cannot find meta source ".$metaSourceId);
                }

                $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new UserMessage($mess["ajxp_conf.57"])));

                break;

            case "meta_source_edit" :

                $repId        = InputFilter::sanitize(isSet($httpVars["workspaceId"]) ? $httpVars["workspaceId"] : $httpVars["repository_id"]);
                $repo         = RepositoryService::findRepositoryByIdOrAlias($repId);
                if (!is_object($repo)) {
                    throw new PydioException("Invalid workspace id! $repId");
                }
                if (isSet($httpVars["bulk_data"])) {
                    $bulkData = json_decode(InputFilter::magicDequote($httpVars["bulk_data"]), true);
                    $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                    if (!is_array($repoOptions)) {
                        $repoOptions = array();
                    }
                    if (isSet($bulkData["delete"]) && count($bulkData["delete"])) {
                        foreach ($bulkData["delete"] as $key) {
                            if (isSet($repoOptions[$key])) unset($repoOptions[$key]);
                        }
                    }
                    if (isSet($bulkData["add"]) && count($bulkData["add"])) {
                        foreach ($bulkData["add"] as $key => $value) {
                            if (isSet($repoOptions[$key])) $this->mergeExistingParameters($value, $repoOptions[$key]);
                            $repoOptions[$key] = $value;
                        }
                    }
                    if (isSet($bulkData["edit"]) && count($bulkData["edit"])) {
                        foreach ($bulkData["edit"] as $key => $value) {
                            if (isSet($repoOptions[$key])) $this->mergeExistingParameters($value, $repoOptions[$key]);
                            $repoOptions[$key] = $value;
                        }
                    }
                } else {
                    $metaSourceId = InputFilter::sanitize(isSet($httpVars["metaId"]) ? $httpVars["metaId"] : $httpVars["plugId"]);
                    $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                    if (!is_array($repoOptions)) {
                        $repoOptions = array();
                    }
                    if(isSet($httpVars["request_body"])){
                        $options = $httpVars["request_body"];
                    }else if (isSet($httpVars["json_data"])) {
                        $options = json_decode(InputFilter::magicDequote($httpVars["json_data"]), true);
                    } else {
                        $options = array();
                        $this->parseParameters($ctx, $httpVars, $options, true);
                    }
                    if (isset($repoOptions[$metaSourceId])) {
                        $this->mergeExistingParameters($options, $repoOptions[$metaSourceId]);
                    }
                    $repoOptions[$metaSourceId] = $options;
                }
                uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                $repo->addOption("META_SOURCES", $repoOptions);
                RepositoryService::replaceRepository($repId, $repo);

                $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new UserMessage($mess["ajxp_conf.58"])));
                break;

            case "list_all_repositories_json":

                $repositories = RepositoryService::listAllRepositories();
                $repoOut = array();
                foreach ($repositories as $repoObject) {
                    $repoOut[$repoObject->getId()] = $repoObject->getDisplay();
                }
                $mess = LocaleService::getMessages();
                $responseInterface = new JsonResponse(["LEGEND" => $mess["ajxp_conf.150"], "LIST" => $repoOut]);

                break;

            default:
                break;

        }

        return $responseInterface;
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     * @throws PydioException
     */
    public function delete(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface){

        $mess = LocaleService::getMessages();
        $httpVars = $requestInterface->getParsedBody();

        $repositories = "";
        if(isSet($httpVars["repository_id"])) $repositories = $httpVars["repository_id"];
        else if(isSet($httpVars["workspaceId"])) $repositories = $httpVars["workspaceId"];
        if(!is_array($repositories)){
            $repositories = [$repositories];
        }
        $repositories = array_map(function($r){
            return InputFilter::sanitize($r, InputFilter::SANITIZE_ALPHANUM);
        }, $repositories);

        foreach($repositories as $repId){
            $repo         = RepositoryService::findRepositoryByIdOrAlias($repId);
            if(!is_object($repo)){
                $res = -1;
            }else{
                $res = RepositoryService::deleteRepository($repId);
            }
            if ($res == -1) {
                throw new PydioException($mess[427]);
            }
        }

        $message = new UserMessage($mess["ajxp_conf.59"]);
        $reload = new ReloadMessage();
        return $responseInterface->withBody(new SerializableResponseStream([$message, $reload]));

    }


    /**
     * @param ServerRequestInterface $requestInterface Full set of query parameters
     * @param string $rootPath Path to prepend to the resulting nodes
     * @param string $relativePath Specific path part for this function
     * @param string $paginationHash Number added to url#2 for pagination purpose.
     * @param string $findNodePosition Path to a given node to try to find it
     * @param string $aliasedDir Aliased path used for alternative url
     * @return NodesList A populated NodesList object, eventually recursive.
     */
    public function listNodes(ServerRequestInterface $requestInterface, $rootPath, $relativePath, $paginationHash = null, $findNodePosition = null, $aliasedDir = null)
    {
        $fullBasePath       = "/" . $rootPath . "/" . $relativePath;
        $REPOS_PER_PAGE     = 10000;
        $paginationHash     = $paginationHash === null ? 1 : $paginationHash;
        $offset             = ($paginationHash - 1) * $REPOS_PER_PAGE;
        $count              = null;
        $ctxUser            = $this->context->getUser();
        $nodesList          = new NodesList($fullBasePath);
        $v2Api              = $requestInterface->getAttribute("api") === "v2";

        // Load all repositories = normal, templates, and templates children
        $criteria = array(
            "ORDERBY"       => array("KEY" => "display", "DIR"=>"ASC"),
            "CURSOR"        => array("OFFSET" => $offset, "LIMIT" => $REPOS_PER_PAGE)
        );
        if($this->currentUserIsGroupAdmin()){
            $criteria = array_merge($criteria, array(
                "owner_user_id" => AJXP_FILTER_EMPTY,
                "groupPath"     => "regexp:/^".str_replace("/", "\/", $ctxUser->getGroupPath()).'/',
            ));
        }else{
            $criteria["parent_uuid"] = AJXP_FILTER_EMPTY;
        }
        if(isSet($requestInterface->getParsedBody()["template_children_id"])){
            $criteria["parent_uuid"] = InputFilter::sanitize($requestInterface->getParsedBody()["template_children_id"], InputFilter::SANITIZE_ALPHANUM);
        }

        $repos = RepositoryService::listRepositoriesWithCriteria($criteria, $count);
        $nodesList->initColumnsData("filelist", "list", "ajxp_conf.repositories");
        $nodesList->setPaginationData($count, $paginationHash, ceil($count / $REPOS_PER_PAGE));
        $nodesList->appendColumn("ajxp_conf.8", "ajxp_label");
        $nodesList->appendColumn("ajxp_conf.9", "accessType");
        $nodesList->appendColumn("ajxp_conf.125", "slug");

        $driverLabels = array();

        foreach ($repos as $repoIndex => $repoObject) {

            if($repoObject->getAccessType() == "ajxp_conf" || $repoObject->getAccessType() == "ajxp_shared") continue;
            if (!empty($ctxUser) && !$ctxUser->canAdministrate($repoObject))continue;
            if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;

            $icon           = "hdd_external_unmount.png";
            $accessType     = $repoObject->getAccessType();
            $accessLabel    = $this->getDriverLabel($accessType, $driverLabels);
            $label          = $repoObject->getDisplay();
            $editable       = $repoObject->isWriteable();
            if ($repoObject->isTemplate) {
                $icon = "hdd_external_mount.png";
                if ($ctxUser != null && $ctxUser->getGroupPath() != "/") {
                    $editable = false;
                }
            }

            $meta = [
                "text"          => $label,
                "repository_id" => $repoIndex,
                "accessType"	=> ($repoObject->isTemplate?"Template for ":"").$repoObject->getAccessType(),
                "accessLabel"	=> $accessLabel,
                "icon"			=> $icon,
                "owner"			=> ($repoObject->hasOwner()?$repoObject->getOwner():""),
                "openicon"		=> $icon,
                "slug"          => $repoObject->getSlug(),
                "parentname"	=> "/repositories",
                "ajxp_mime" 	=> "repository".($editable?"_editable":""),
                "is_template"   => ($repoObject->isTemplate?"true":"false")
            ];

            $nodeKey = "/data/repositories/$repoIndex";
            $this->appendBookmarkMeta($nodeKey, $meta);
            $repoNode = new AJXP_Node($v2Api ? (string)$repoIndex : $nodeKey, $meta);
            $nodesList->addBranch($repoNode);

            if ($repoObject->isTemplate) {
                // Now Load children for template repositories
                $children = RepositoryService::listRepositoriesWithCriteria(array("parent_uuid" => $repoIndex . ""), $count);
                foreach($children as $childId => $childObject){
                    if (!empty($ctxUser) && !$ctxUser->canAdministrate($childObject))continue;
                    if(is_numeric($childId)) $childId = "".$childId;
                    $meta = array(
                        "text"          => $childObject->getDisplay(),
                        "repository_id" => $childId,
                        "accessType"	=> $childObject->getAccessType(),
                        "accessLabel"	=> $this->getDriverLabel($childObject->getAccessType(), $driverLabels),
                        "icon"			=> "repo_child.png",
                        "slug"          => $childObject->getSlug(),
                        "owner"			=> ($childObject->hasOwner()?$childObject->getOwner():""),
                        "openicon"		=> "repo_child.png",
                        "parentname"	=> "/repositories",
                        "ajxp_mime" 	=> "repository_editable",
                        "template_name" => $label
                    );
                    $cNodeKey = "/data/repositories/$childId";
                    $this->appendBookmarkMeta($cNodeKey, $meta);
                    $repoNode = new AJXP_Node($v2Api ? $childId : $cNodeKey, $meta);
                    $nodesList->addBranch($repoNode);
                }
            }
        }

        return $nodesList;
    }

    /**
     * Get label for an access.* plugin
     * @param $pluginId
     * @param $labels
     * @return mixed|string
     */
    protected function getDriverLabel($pluginId, &$labels){
        if(isSet($labels[$pluginId])){
            return $labels[$pluginId];
        }
        $plugin = PluginsService::getInstance(Context::emptyContext())->getPluginById("access.".$pluginId);
        if(!is_object($plugin)) {
            $label = "access.$plugin (plugin disabled!)";
        }else{
            $label = $plugin->getManifestLabel();
        }
        $labels[$pluginId] = $label;
        return $label;
    }

    /**
     * @param $name
     * @return bool
     */
    public function repositoryExists($name)
    {
        RepositoryService::listRepositoriesWithCriteria(array("display" => $name), $count);
        return $count > 0;
    }

    /**
     * Reorder meta sources
     * @param $key1
     * @param $key2
     * @return int
     */
    public function metaSourceOrderingFunction($key1, $key2)
    {
        $a1 = explode(".", $key1);
        $t1 = array_shift($a1);
        $a2 = explode(".", $key2);
        $t2 = array_shift($a2);
        if($t1 == "index") return 1;
        if($t1 == "metastore") return -1;
        if($t2 == "index") return -1;
        if($t2 == "metastore") return 1;
        if($key1 == "meta.git" || $key1 == "meta.svn") return 1;
        if($key2 == "meta.git" || $key2 == "meta.svn") return -1;
        return strcmp($key1, $key2);
    }

    /**
     * @param ContextInterface $ctx
     * @param RepositoryInterface $repository
     * @param array $definitions
     * @param string $currentAdminBasePath
     * @return array
     */
    protected function serializeRepositoryToJSON(ContextInterface $ctx, $repository, $definitions, $currentAdminBasePath){
        $nested = [];
        $buffer = [
            "id"            => $repository->getId(),
            "securityScope" => $repository->securityScope()
        ];
        if(!$repository->isTemplate()){
            $buffer["slug"] = $repository->getSlug();
        }
        $groupPath = $repository->getGroupPath();
        if ($groupPath != null) {
            if($currentAdminBasePath != "/") {
                $groupPath = substr($repository->getGroupPath(), strlen($currentAdminBasePath));
            }
            $buffer["groupPath"]= $groupPath;
        }
        foreach ($repository as $name => $option) {
            if(strstr($name, " ")>-1) continue;
            if (in_array($name, ["driverInstance", "id", "uuid", "path", "recycle", "create", "enabled"])) continue;
            if(is_array($option)) {
                $nested[] = $option;
            } else{
                $buffer[$name] = $option;
            }
        }
        if (count($nested)) {
            $buffer["parameters"]= [];

            foreach ($nested as $option) {
                foreach ($option as $key => $optValue) {
                    if(isSet($definitions[$key]) && $definitions[$key]["type"] == "password" && !empty($optValue)){
                        $optValue = "__AJXP_VALUE_SET__";
                    }
                    $buffer["parameters"][$key] = $optValue;
                }
            }
            // Add SLUG?
            /*
            if(!empty($buffer["slug"])) {
                $buffer["PARAMETERS"]["AJXP_SLUG"] = $buffer["slug"];
            }
            if(!empty($buffer["groupPath"])) {
                $buffer["PARAMETERS"]["AJXP_GROUP_PATH_PARAMETER"] = $buffer["groupPath"];
            }
            */
        }
        if(isSet($buffer["parameters"]) && isSet($buffer["parameters"]["META_SOURCES"])){
            $buffer["features"] = $buffer["parameters"]["META_SOURCES"];
            unset($buffer["parameters"]["META_SOURCES"]);
        }
        if(!$repository->isTemplate()){
            $buffer["info"]= [];
            $users = UsersService::countUsersForRepository($ctx, $repository->getId(), false, true);
            $cursor = ["count"];
            $shares = ConfService::getConfStorageImpl()->simpleStoreList("share", $cursor, "", "serial", '', $repository->getId());
            $buffer["info"] = [
                "users" => $users,
                "shares" => count($shares)
            ];
            $rootGroup = RolesService::getRole("AJXP_GRP_/");
            if($rootGroup !== false && $rootGroup->hasMask($repository->getId())){
                $buffer["mask"]= $rootGroup->getMask($repository->getId());
            }
        }
        if ($repository->hasParent()) {
            $parent = RepositoryService::getRepositoryById($repository->getParentId());
            if (isSet($parent) && $parent->isTemplate()) {
                $parentLabel = $parent->getDisplay();
                $parentType = $parent->getAccessType();
                $buffer["TEMPLATE"] = [
                    "id"    => $repository->getParentId(),
                    "label" => $parentLabel,
                    "type"  => $parentType,
                    "DEFINED_PARAMETERS" => []
                ];
                foreach ($parent->getOptionsDefined() as $parentOptionName) {
                    $buffer["TEMPLATE"]["DEFINED_PARAMETERS"][] = $parentOptionName;
                }
            }
        }
        return $buffer;
    }

    /**
     * @param ContextInterface $ctx
     * @param RepositoryInterface $repository
     * @param array $definitions
     * @param string $currentAdminBasePath
     * @return string
     */
    protected function serializeRepositoryToXML(ContextInterface $ctx, $repository, $definitions, $currentAdminBasePath){
        $nested = [];
        $buffer = "<repository index=\"".$repository->getId()."\" securityScope=\"".$repository->securityScope()."\"";
        foreach ($repository as $name => $option) {
            if(strstr($name, " ")>-1) continue;
            if ($name == "driverInstance") continue;
            if (!is_array($option)) {
                if (is_bool($option)) {
                    $option = ($option?"true":"false");
                }
                $buffer .= " $name=\"".StringHelper::xmlEntities($option, true)."\" ";
            } else if (is_array($option)) {
                $nested[] = $option;
            }
        }
        if (count($nested)) {
            $buffer .= ">" ;
            foreach ($nested as $option) {
                foreach ($option as $key => $optValue) {
                    if (is_array($optValue) && count($optValue)) {
                        $buffer .= "<param name=\"$key\"><![CDATA[".json_encode($optValue)."]]></param>" ;
                    } else if (is_object($optValue)){
                        $buffer .= "<param name=\"$key\"><![CDATA[".json_encode($optValue)."]]></param>";
                    } else {
                        if (is_bool($optValue)) {
                            $optValue = ($optValue?"true":"false");
                        } else if(isSet($definitions[$key]) && $definitions[$key]["type"] == "password" && !empty($optValue)){
                            $optValue = "__AJXP_VALUE_SET__";
                        }

                        $optValue = StringHelper::xmlEntities($optValue, true);
                        $buffer .= "<param name=\"$key\" value=\"$optValue\"/>";
                    }
                }
            }
            // Add SLUG
            if(!$repository->isTemplate()) {
                $buffer .= "<param name=\"AJXP_SLUG\" value=\"".$repository->getSlug()."\"/>";
            }
            if ($repository->getGroupPath() != null) {
                $groupPath = $repository->getGroupPath();
                if($currentAdminBasePath != "/") $groupPath = substr($repository->getGroupPath(), strlen($currentAdminBasePath));
                $buffer .= "<param name=\"AJXP_GROUP_PATH_PARAMETER\" value=\"".$groupPath."\"/>";
            }

            $buffer .= "</repository>";
        } else {
            $buffer .= "/>";
        }
        if ($repository->hasParent()) {
            $parent = RepositoryService::getRepositoryById($repository->getParentId());
            if (isSet($parent) && $parent->isTemplate()) {
                $parentLabel = $parent->getDisplay();
                $parentType = $parent->getAccessType();
                $buffer .= "<template repository_id=\"".$repository->getParentId()."\" repository_label=\"$parentLabel\" repository_type=\"$parentType\">";
                foreach ($parent->getOptionsDefined() as $parentOptionName) {
                    $buffer .= "<option name=\"$parentOptionName\"/>";
                }
                $buffer .= "</template>";
            }
        }
        if(!$repository->isTemplate()){
            $buffer .= "<additional_info>";
            $users = UsersService::countUsersForRepository($ctx, $repository->getId(), false, true);
            $cursor = ["count"];
            $shares = ConfService::getConfStorageImpl()->simpleStoreList("share", $cursor, "", "serial", '', $repository->getId());
            $buffer .= '<users total="'.$users.'"/>';
            $buffer .= '<shares total="'.count($shares).'"/>';
            $rootGroup = RolesService::getRole("AJXP_GRP_/");
            if($rootGroup !== false && $rootGroup->hasMask($repository->getId())){
                $buffer .= "<mask><![CDATA[".json_encode($rootGroup->getMask($repository->getId()))."]]></mask>";
            }
            $buffer .= "</additional_info>";
        }
        return $buffer;
    }

    /**
     * @param PluginsService $pServ
     * @param string $format
     * @param AbstractAccessDriver $plug
     * @param RepositoryInterface $repository
     * @return string|array
     */
    protected function serializeRepositoryDriverInfos(PluginsService $pServ, $format, $plug, $repository){
        $manifest = $plug->getManifestRawContent("server_settings/param");
        $manifest = XMLFilter::resolveKeywords($manifest);
        $clientSettings = $plug->getManifestRawContent("client_settings", "xml");
        $iconClass = "";$descriptionTemplate = "";
        if($clientSettings->length){
            $iconClass = $clientSettings->item(0)->getAttribute("iconClass");
            $descriptionTemplate = $clientSettings->item(0)->getAttribute("description_template");
        }
        $metas = $pServ->getPluginsByType("metastore");
        $metas = array_merge($metas, $pServ->getPluginsByType("meta"));
        $metas = array_merge($metas, $pServ->getPluginsByType("index"));

        if($format === "xml"){
            $buffer = "<ajxpdriver name=\"".$repository->getAccessType()."\" label=\"". StringHelper::xmlEntities($plug->getManifestLabel()) ."\" 
            iconClass=\"$iconClass\" description_template=\"$descriptionTemplate\" 
            description=\"". StringHelper::xmlEntities($plug->getManifestDescription()) ."\">$manifest</ajxpdriver>";

            $buffer .= "<metasources>";
            /** @var Plugin $metaPlug */
            foreach ($metas as $metaPlug) {
                $buffer .= "<meta id=\"".$metaPlug->getId()."\" label=\"". StringHelper::xmlEntities($metaPlug->getManifestLabel()) ."\" description=\"". StringHelper::xmlEntities($metaPlug->getManifestDescription()) ."\">";
                $manifest = $metaPlug->getManifestRawContent("server_settings/param");
                $manifest = XMLFilter::resolveKeywords($manifest);
                $buffer .= $manifest;
                $buffer .= "</meta>";
            }
            $buffer .= "</metasources>";
            return $buffer;
        }else{
            $dData = [
                "name" => $repository->getAccessType(),
                "label" => $plug->getManifestLabel(),
                "description" => $plug->getManifestDescription(),
                "iconClass" => $iconClass,
                "descriptionTemplate" => $descriptionTemplate,
                "parameters" => $this->xmlServerParamsToArray($manifest)
            ];
            $metaSources = [];
            /** @var Plugin $metaPlug */
            foreach($metas as $metaPlug){
                $metaSources[$metaPlug->getId()] = [
                    "id" => $metaPlug->getId(),
                    "label" => $metaPlug->getManifestLabel(),
                    "description" => $metaPlug->getManifestDescription(),
                    "parameters" => $this->xmlServerParamsToArray(XMLFilter::resolveKeywords($metaPlug->getManifestRawContent("server_settings/param")))
                ];
            }
            $data = ["driver" => $dData, "metasources" => $metaSources];
            return $data;
        }
    }

    /**
     * @param string $xmlParamsString
     * @return array
     */
    protected function xmlServerParamsToArray($xmlParamsString){
        $doc = new \DOMDocument();
        $doc->loadXML("<parameters>$xmlParamsString</parameters>");
        $result = XMLHelper::xmlToArray($doc, ["attributePrefix" => ""]);
        if(isSet($result["parameters"]["param"])){
            return $result["parameters"]["param"];
        }else{
            return [];
        }
    }

    /**
     * Search the manifests declaring ajxpdriver as their root node. Remove ajxp_* drivers
     * @static
     * @param string $filterByTagName
     * @param string $filterByDriverName
     * @param bool $limitToEnabledPlugins
     * @return string
     */
    protected function availableDriversToXML($filterByTagName = "", $filterByDriverName="", $limitToEnabledPlugins = false)
    {
        $nodeList = PluginsService::getInstance(Context::emptyContext())->searchAllManifests("//ajxpdriver", "node", false, $limitToEnabledPlugins);
        $xmlBuffer = "";
        /** @var \DOMElement $node */
        foreach ($nodeList as $node) {
            $dName = $node->getAttribute("name");
            if($filterByDriverName != "" && $dName != $filterByDriverName) continue;
            if(strpos($dName, "ajxp_") === 0) continue;
            if ($filterByTagName == "") {
                $xmlBuffer .= $node->ownerDocument->saveXML($node);
                continue;
            }
            $q = new \DOMXPath($node->ownerDocument);
            $cNodes = $q->query("//".$filterByTagName, $node);
            $xmlBuffer .= "<ajxpdriver ";
            foreach($node->attributes as $attr) $xmlBuffer.= " $attr->name=\"$attr->value\" ";
            $xmlBuffer .=">";
            foreach ($cNodes as $child) {
                $xmlBuffer .= $child->ownerDocument->saveXML($child);
            }
            $xmlBuffer .= "</ajxpdriver>";
        }
        return $xmlBuffer;
    }


}
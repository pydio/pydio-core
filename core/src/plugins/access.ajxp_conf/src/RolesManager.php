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
use Pydio\Access\Core\Filter\AJXP_PermissionMask;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Conf\Core\AbstractUser;
use Pydio\Conf\Core\AJXP_Role;
use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\PathUtils;
use Pydio\Log\Core\Logger;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class RolesManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class RolesManager extends AbstractManager
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @return ServerRequestInterface
     */
    public function preprocessApi2Actions(ServerRequestInterface $requestInterface){
        $vars = $requestInterface->getParsedBody();
        if(isSet($vars["roleId"])) $vars["role_id"] = $vars["roleId"];
        return $requestInterface->withParsedBody($vars);

    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     * @throws \Exception
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public function rolesActions(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface){

        if($requestInterface->getAttribute("api") === "v2"){
            $requestInterface = $this->preprocessApi2Actions($requestInterface);
        }

        $action     = $requestInterface->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        $httpVars   = $requestInterface->getParsedBody();
        $mess       = LocaleService::getMessages();


        switch ($action){

            // ROLES
            case "create_role":

                $roleId = InputFilter::sanitize(InputFilter::magicDequote($httpVars["role_id"]), InputFilter::SANITIZE_HTML_STRICT);
                if (!strlen($roleId)) {
                    throw new \Exception($mess[349]);
                }
                if (RolesService::getRole($roleId) !== false) {
                    throw new \Exception($mess["ajxp_conf.65"]);
                }
                $r = new AJXP_Role($roleId);
                $user = $ctx->getUser();
                if ($user !== null && $user->getGroupPath()!== null) {
                    $r->setGroupPath($user->getGroupPath());
                }
                RolesService::updateRole($r);

                $userMessage        = new UserMessage($mess["ajxp_conf.66"]);
                $reloadMessage      = new ReloadMessage("", $httpVars["role_id"]);
                $responseInterface  = $responseInterface->withBody(new SerializableResponseStream([$userMessage, $reloadMessage]));

                break;

            case "edit_role" :

                $roleId = InputFilter::magicDequote($httpVars["role_id"]);
                $roleGroup = false;
                $userObject = null;
                $groupLabel = null;
                $currentMainUser = $ctx->getUser();

                if (strpos($roleId, "AJXP_GRP_") === 0) {
                    $groupPath = substr($roleId, strlen("AJXP_GRP_"));
                    $filteredGroupPath = (!empty($currentMainUser) ? $currentMainUser->getRealGroupPath($groupPath) : $groupPath);
                    if($filteredGroupPath == "/"){
                        $roleId = "AJXP_GRP_/";
                        $groupLabel = $mess["ajxp_conf.151"];
                        $roleGroup = true;
                    }else{
                        $groups = UsersService::listChildrenGroups(PathUtils::forwardSlashDirname($filteredGroupPath));
                        $key = "/".basename($groupPath);
                        if (!array_key_exists($key, $groups)) {
                            throw new \Exception("Cannot find group with this id!");
                        }
                        $roleId = "AJXP_GRP_".$filteredGroupPath;
                        $groupLabel = $groups[$key];
                        $roleGroup = true;
                    }
                }
                if (strpos($roleId, "AJXP_USR_") === 0) {
                    $usrId = str_replace("AJXP_USR_/", "", $roleId);
                    $userObject = UsersService::getUserById($usrId);
                    if(!empty($currentMainUser) && !$currentMainUser->canAdministrate($userObject)){
                        throw new \Exception("Cant find user!");
                    }
                    $role = $userObject->getPersonalRole();
                } else {
                    if($roleGroup){
                        $role = RolesService::getOrCreateRole($roleId, $ctx->hasUser() ? $ctx->getUser()->getGroupPath() : "/");
                    }else{
                        $role = RolesService::getRole($roleId);
                    }
                }
                if ($role === false) {
                    throw new \Exception("Cant find role! ");
                }

                $data = [
                    "ROLE" => $role->getDataArray(true)
                ];

                if (isSet($userObject)) {

                    $data["USER"] = array();
                    $data["USER"]["LOCK"] = $userObject->getLock();
                    $data["USER"]["PROFILE"] = $userObject->getProfile();
                    $data["USER"]["ROLES"] = array_keys($userObject->getRoles());
                    $rolesList = RolesService::getRolesList(array(), true);
                    $data["ALL"]["ROLES"] = array_keys($rolesList);
                    $data["ALL"]["ROLES_DETAILS"] = array();
                    foreach($rolesList as $rId => $rObj){
                        $data["ALL"]["ROLES_DETAILS"][$rId] = array("label" => $rObj->getLabel(), "sticky" => $rObj->alwaysOverrides());
                    }
                    if ($userObject instanceof AbstractUser && isSet($userObject->parentRole)) {
                        $data["PARENT_ROLE"] = $userObject->parentRole->getDataArray();
                    }

                } else if (isSet($groupPath)) {

                    $data["GROUP"] = array("PATH" => $groupPath, "LABEL" => $groupLabel);
                    if($roleId != "AJXP_GRP_/"){
                        $parentGroupRoles = array();
                        $parentPath = PathUtils::forwardSlashDirname($roleId);
                        while($parentPath != "AJXP_GRP_"){
                            $parentRole = RolesService::getRole($parentPath);
                            if($parentRole != null) {
                                array_unshift($parentGroupRoles, $parentRole);
                            }
                            $parentPath = PathUtils::forwardSlashDirname($parentPath);
                        }
                        $rootGroup = RolesService::getRole("AJXP_GRP_/");
                        if($rootGroup != null) array_unshift($parentGroupRoles, $rootGroup);
                        if(count($parentGroupRoles)){
                            $parentRole = clone array_shift($parentGroupRoles);
                            foreach($parentGroupRoles as $pgRole){
                                $parentRole = $pgRole->override($parentRole);
                            }
                            $data["PARENT_ROLE"] = $parentRole->getDataArray();
                        }
                    }

                }

                if($requestInterface->getAttribute("api") === "v2" && (empty($httpVars["load_fill_values"]) || $httpVars["load_fill_values"] !== "true")){
                    $responseInterface = new JsonResponse($data["ROLE"]);
                    break;
                }

                $allReps        = RepositoryService::listAllRepositories();
                $repos          = array();
                $repoDetailed   = array();
                $sharedRepos    = array();
                if(isSet($userObject)){
                    // Add User shared Repositories as well
                    $acls = $userObject->getMergedRole()->listAcls();
                    if(count($acls)) {
                        $sharedRepos = RepositoryService::listRepositoriesWithCriteria(array(
                            "uuid" => array_keys($acls),
                            "parent_uuid" => AJXP_FILTER_NOT_EMPTY,
                            "owner_user_id" => AJXP_FILTER_NOT_EMPTY
                        ), $count);
                        $allReps = array_merge($allReps, $sharedRepos);
                    }
                }

                // USER
                foreach ($allReps as $repositoryId => $repositoryObject) {
                    if (!empty($userObject) &&
                        (
                            !$userObject->canSee($repositoryObject) || $repositoryObject->isTemplate()
                            || ($repositoryObject->getAccessType()=="ajxp_conf" && !$userObject->isAdmin())
                            || ($repositoryObject->getUniqueUser() != null && $repositoryObject->getUniqueUser() != $userObject->getId())
                        )
                    ){
                        if(isSet($sharedRepos[$repositoryId])) unset($sharedRepos[$repositoryId]);
                        continue;
                    }else if(empty($userObject) && (
                            (!empty($currentMainUser) && !$currentMainUser->canSee($repositoryObject)) || $repositoryObject->isTemplate()
                        )){
                        if(isSet($sharedRepos[$repositoryId])) unset($sharedRepos[$repositoryId]);
                        continue;
                    }
                    $meta = array();
                    try{
                        $metaSources = $repositoryObject->getContextOption($ctx, "META_SOURCES");
                        if($metaSources !== null){
                            $meta = array_keys($metaSources);
                        }
                    }catch(\Exception $e){
                        if(isSet($sharedRepos[$repositoryId])) unset($sharedRepos[$repositoryId]);
                        Logger::error("ConfDriver", "Invalid Share", "Repository $repositoryId has no more parent. Should be deleted.");
                        continue;
                    }
                    $repoDetailed[$repositoryId] = array(
                        "label"  => $repositoryObject->getDisplay(),
                        "driver" => $repositoryObject->getAccessType(),
                        "scope"  => $repositoryObject->securityScope(),
                        "meta"   => $meta
                    );

                    if(array_key_exists($repositoryId, $sharedRepos)){
                        $sharedRepos[$repositoryId] = $repositoryObject->getDisplay();
                        $repoParentLabel = $repoParentId = $repositoryObject->getParentId();
                        $repoOwnerId = $repositoryObject->getOwner();
                        if(isSet($allReps[$repoParentId])){
                            $repoParentLabel = $allReps[$repoParentId]->getDisplay();
                        }
                        $repoOwnerLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $repoOwnerId, "core.conf", $repoOwnerId);
                        $repoDetailed[$repositoryId]["share"] = array(
                            "parent_user" => $repoOwnerId,
                            "parent_user_label" => $repoOwnerLabel,
                            "parent_repository" => $repoParentId,
                            "parent_repository_label" => $repoParentLabel
                        );
                    }else{
                        $repos[$repositoryId] = $repositoryObject->getDisplay();
                    }

                }

                // Make sure it's utf8
                $data["ALL"] = array_merge((isSet($data["ALL"]) ? $data["ALL"]: []), [
                    "PLUGINS_SCOPES"    => [
                        "GLOBAL_TYPES"      => ["conf", "auth", "authfront", "log", "mq", "gui", "sec"],
                        "GLOBAL_PLUGINS"    => ["action.avatar", "action.disclaimer", "action.scheduler", "action.skeleton", "action.updater"]
                    ],
                    "REPOSITORIES"          => $repos,
                    "SHARED_REPOSITORIES"   => $sharedRepos,
                    "REPOSITORIES_DETAILS"  => $repoDetailed,
                    "PROFILES"              => [
                        "standard|".$mess["ajxp_conf.156"],
                        "admin|".$mess["ajxp_conf.157"],
                        "shared|".$mess["ajxp_conf.158"],
                        "guest|".$mess["ajxp_conf.159"]
                    ]
                ]);

                $scope = "role";
                if($roleGroup) {
                    $scope = "group";
                    if($roleId == "AJXP_GRP_/") {
                        $scope = "role";
                    }
                }else if(isSet($userObject)) {
                    $scope = "user";
                }
                $data["SCOPE_PARAMS"] = array();
                $nodes = PluginsService::getInstance($ctx)->searchAllManifests("//param[contains(@scope,'".$scope."')]|//global_param[contains(@scope,'".$scope."')]", "node", false, true, true);
                foreach ($nodes as $node) {
                    $pId = $node->parentNode->parentNode->attributes->getNamedItem("id")->nodeValue;
                    $origName = $node->attributes->getNamedItem("name")->nodeValue;
                    if($roleId == "AJXP_GRP_/" && strpos($origName, "ROLE_") ===0 ) continue;
                    $node->attributes->getNamedItem("name")->nodeValue = "AJXP_REPO_SCOPE_ALL/".$pId."/".$origName;
                    $nArr = array();
                    foreach ($node->attributes as $attrib) {
                        $nArr[$attrib->nodeName] = XMLFilter::resolveKeywords($attrib->nodeValue);
                    }
                    $data["SCOPE_PARAMS"][] = $nArr;
                }

                $responseInterface = new JsonResponse($data);

                break;

            case "post_json_role" :

                $roleId = InputFilter::magicDequote($httpVars["role_id"]);
                $roleGroup = false;
                $currentMainUser = $ctx->getUser();
                $userObject = $usrId = $filteredGroupPath = null;
                if (strpos($roleId, "AJXP_GRP_") === 0) {
                    $groupPath = substr($roleId, strlen("AJXP_GRP_"));
                    $filteredGroupPath = (!empty($currentMainUser) ? $currentMainUser->getRealGroupPath($groupPath) : $groupPath);
                    $roleId = "AJXP_GRP_".$filteredGroupPath;
                    if($roleId != "AJXP_GRP_/"){
                        $groups = UsersService::listChildrenGroups(PathUtils::forwardSlashDirname($filteredGroupPath));
                        $key = "/".basename($groupPath);
                        if (!array_key_exists($key, $groups)) {
                            throw new \Exception("Cannot find group with this id!");
                        }
                        $groupLabel = $groups[$key];
                    }else{
                        $groupLabel = $mess["ajxp_conf.151"];
                    }
                    $roleGroup = true;
                }
                if (strpos($roleId, "AJXP_USR_") === 0) {
                    $usrId = str_replace("AJXP_USR_/", "", $roleId);
                    $userObject = UsersService::getUserById($usrId);
                    $ctxUser = $ctx->getUser();
                    if(!empty($ctxUser) && !$ctxUser->canAdministrate($userObject)){
                        throw new \Exception("Cannot post role for user ".$usrId);
                    }
                    $originalRole = $userObject->getPersonalRole();
                } else {
                    // second param = create if not exists.
                    if($roleGroup){
                        $originalRole = RolesService::getOrCreateRole($roleId, $ctx->hasUser() ? $ctx->getUser()->getGroupPath() : "/");
                    }else{
                        $originalRole = RolesService::getRole($roleId);
                    }
                }
                if ($originalRole === false) {
                    throw new \Exception("Cant find role! ");
                }

                if(isSet($httpVars["request_body"])){
                    // This is API V2 : only the role is passed as json body
                    $roleData = $httpVars["request_body"];
                    if(!isSet($roleData['PARAMETERS']) || !isSet($roleData['ACTIONS']) || !isSet($roleData['ACL'])){
                        throw new PydioException('Please post the whole role content, including PARAMETERS, ACTIONS and ACL keys at least');
                    }
                    $data = ["METADATA" => []];
                    $outputRoleOnly = true;
                }else{
                    // Other apis: a more complex
                    $jsonData = InputFilter::magicDequote($httpVars["json_data"]);
                    $data = json_decode($jsonData, true);
                    $roleData = $data["ROLE"];
                    $outputRoleOnly = false;
                }
                $binariesContext = array();
                $parseContext = $ctx;
                if (isset($userObject)) {
                    $parseContext = new Context(null, $ctx->getRepositoryId());
                    $parseContext->setUserObject($userObject);
                    $binariesContext = array("USER" => $userObject->getId());
                }
                if(isSet($data["FORMS"])){
                    $forms = $data["FORMS"];
                    foreach ($forms as $repoScope => $plugData) {
                        foreach ($plugData as $plugId => $formsData) {
                            $parsed = array();
                            OptionsHelper::parseStandardFormParameters(
                                $parseContext,
                                $formsData,
                                $parsed,
                                "ROLE_PARAM_",
                                $binariesContext,
                                AJXP_Role::$cypheredPassPrefix
                            );
                            $roleData["PARAMETERS"][$repoScope][$plugId] = $parsed;
                        }
                    }
                }else{
                    OptionsHelper::filterFormElementsFromMeta(
                        $parseContext,
                        $data["METADATA"],
                        $roleData,
                        ($userObject != null ? $usrId : null),
                        $binariesContext,
                        AJXP_Role::$cypheredPassPrefix
                    );
                }
                $existingParameters = $originalRole->listParameters(true);
                $this->mergeExistingParameters($roleData["PARAMETERS"], $existingParameters);
                if (isSet($userObject) && isSet($data["USER"]) && isSet($data["USER"]["PROFILE"])) {
                    $userObject->setAdmin(($data["USER"]["PROFILE"] == "admin"));
                    $userObject->setProfile($data["USER"]["PROFILE"]);
                }
                if (isSet($data["GROUP_LABEL"]) && isSet($groupLabel) && $groupLabel != $data["GROUP_LABEL"]) {
                    ConfService::getConfStorageImpl()->relabelGroup($filteredGroupPath, $data["GROUP_LABEL"]);
                }

                if($this->currentUserIsGroupAdmin()){
                    // FILTER DATA FOR GROUP ADMINS
                    $params = $this->getEditableParameters($ctx, true, false);
                    foreach($roleData["PARAMETERS"] as $scope => &$plugsParameters){
                        foreach($plugsParameters as $paramPlugin => &$parameters){
                            foreach($parameters as $pName => $pValue){
                                if(!isSet($params[$paramPlugin]) || !in_array($pName, $params[$paramPlugin])){
                                    unset($parameters[$pName]);
                                }
                            }
                            if(!count($parameters)){
                                unset($plugsParameters[$paramPlugin]);
                            }
                        }
                        if(!count($plugsParameters)){
                            unset($roleData["PARAMETERS"][$scope]);
                        }
                    }
                    // Remerge from parent
                    $roleData["PARAMETERS"] = $originalRole->array_merge_recursive2($originalRole->listParameters(), $roleData["PARAMETERS"]);
                    // Changing Actions is not allowed
                    $roleData["ACTIONS"] = $originalRole->listActionsStates();
                }

                if(isSet($roleData["MASKS"])){
                    foreach($roleData["MASKS"] as $repoId => $serialMask){
                        $roleData["MASKS"][$repoId] = new AJXP_PermissionMask($serialMask);
                    }
                }

                try {
                    $originalRole->bunchUpdate($roleData);
                    if (isSet($userObject)) {
                        $userObject->updatePersonalRole($originalRole);
                        $userObject->save("superuser");
                    } else {
                        RolesService::updateRole($originalRole);
                    }
                    // Reload Role
                    $savedValue = RolesService::getRole($originalRole->getId());
                    if($outputRoleOnly){
                        $output = $savedValue->getDataArray(true);
                    }else{
                        $output = array("ROLE" => $savedValue->getDataArray(true), "SUCCESS" => true);
                    }
                } catch (\Exception $e) {
                    $output = array("ERROR" => $e->getMessage());
                }

                $responseInterface = new JsonResponse($output);

                break;

            case "role_update_right" :

                if(!isSet($httpVars["role_id"]) || !isSet($httpVars["repository_id"]) || !isSet($httpVars["right"])) {
                    throw new PydioException($mess["ajxp_conf.61"]);
                }
                $rId = InputFilter::sanitize($httpVars["role_id"]);
                $role = RolesService::getRole($rId);
                if($role === false){
                    throw new PydioException($mess["ajxp_conf.61"]."($rId)");
                }
                $role->setAcl(InputFilter::sanitize($httpVars["repository_id"], InputFilter::SANITIZE_ALPHANUM), InputFilter::sanitize($httpVars["right"], InputFilter::SANITIZE_ALPHANUM));
                RolesService::updateRole($role);

                $responseInterface = $responseInterface->withBody(new SerializableResponseStream(new UserMessage($mess["ajxp_conf.46"].$httpVars["role_id"])));

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
        $roles = "";
        if(isSet($httpVars["role_id"])) $roles = $httpVars["role_id"];
        else if(isSet($httpVars["roleId"])) $roles = $httpVars["roleId"];
        if(!is_array($roles)){
            $roles = [$roles];
        }
        
        foreach($roles as $roleId){
            $roleId = InputFilter::sanitize($roleId);
            if (RolesService::getRole($roleId) === false) {
                throw new PydioException($mess["ajxp_conf.67"]);
            }
            RolesService::deleteRole($roleId);
        }

        $message = new UserMessage($mess["ajxp_conf.68"]);
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
        $nodesList = new NodesList("/$rootPath/$relativePath");
        $nodesList->initColumnsData("filelist", "list", "ajxp_conf.roles");
        $nodesList->appendColumn("ajxp_conf.76", "ajxp_label");
        $nodesList->appendColumn("ajxp_conf.114", "is_default");
        $nodesList->appendColumn("ajxp_conf.62", "rights_summary");

        if(!UsersService::usersEnabled()) {
            return $nodesList;
        }

        $mess       = LocaleService::getMessages();
        $ctxUser    = $this->context->getUser();
        $roles      = RolesService::getRolesList(array(), !$this->listSpecialRoles);
        ksort($roles);

        if(!$this->listSpecialRoles && $this->pluginName != "ajxp_admin" && !$this->currentUserIsGroupAdmin()){
            $rootGroupRole = RolesService::getOrCreateRole("AJXP_GRP_/", empty($ctxUser) ? "/" : $ctxUser->getGroupPath());
            if($rootGroupRole->getLabel() == "AJXP_GRP_/"){
                $rootGroupRole->setLabel($mess["ajxp_conf.151"]);
                RolesService::updateRole($rootGroupRole);
            }
            array_unshift($roles, $rootGroupRole);
        }

        foreach ($roles as $roleObject) {

            $r = array();
            if(!empty($ctxUser) && !$ctxUser->canAdministrate($roleObject)) {
                continue;
            }
            $count = 0;
            $repos = RepositoryService::listRepositoriesWithCriteria(array("role" => $roleObject), $count);
            foreach ($repos as $repoId => $repository) {
                if($repository->getAccessType() == "ajxp_shared") continue;
                if(!$roleObject->canRead($repoId) && !$roleObject->canWrite($repoId)) continue;
                $rs = ($roleObject->canRead($repoId) ? "r" : "");
                $rs .= ($roleObject->canWrite($repoId) ? "w" : "");
                $r[] = $repository->getDisplay()." (".$rs.")";
            }
            $rightsString = implode(", ", $r);
            $nodeKey = "/".$rootPath."/".$relativePath."/".$roleObject->getId();
            $appliesToDefault = implode(",", $roleObject->listAutoApplies());
            if($roleObject->getId() == "AJXP_GRP_/"){
                $appliesToDefault = $mess["ajxp_conf.153"];
            }
            $meta = array(
                "icon"              => "user-acl.png",
                "rights_summary"    => $rightsString,
                "is_default"        => $appliesToDefault,
                "ajxp_mime"         => "role",
                "role_id"           => $roleObject->getId(),
                "text"              => $roleObject->getLabel()
            );
            $this->appendBookmarkMeta($nodeKey, $meta);
            if($requestInterface->getAttribute("api") === "v2"){
                $nodeKey = $roleObject->getId();
                $meta["role"] = $roleObject->getDataArray(true);
            }
            $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
        }
        return $nodesList;

    }
}
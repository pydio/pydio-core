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
namespace Pydio\Access\Meta\UserGenerated;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class UserMetaManager extends AbstractMetaSource
{
    /**
     * @var IMetaStoreProvider
     */
    protected $metaStore;
    protected $fieldsAdditionalData = array();
    private $metaOptionsParsed = false;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        $this->options = $options;
        // Do not call parent
    }

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     * @throws PydioException
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);

        $store = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new PydioException("The 'meta.user' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($ctx, $accessDriver);
        
        $def = $this->getMetaDefinition();
        foreach($def as $k => &$d){
            if(isSet($this->fieldsAdditionalData[$k])) $d["data"] = $this->fieldsAdditionalData[$k];
        }
        $this->exposeConfigInManifest("meta_definitions", json_encode($def));
        if(!isSet($this->options["meta_visibility"])) $visibilities = array("visible");
        else $visibilities = explode(",", $this->options["meta_visibility"]);

        $selection = $this->getXPath()->query('registry_contributions/client_configs/component_config[@className="FilesList"]/columns');
        $contrib = $selection->item(0);
        $even = false;
        $searchables = []; $searchablesRenderers = []; $searchablesReactRenderers = [];
        $index = 0;

        foreach ($def as $key=> $data) {
            $label = $data["label"];
            $fieldType = $data["type"];

            if (isSet($visibilities[$index])) {
                $lastVisibility = $visibilities[$index];
            }
            $index ++;
            $col = $this->manifestDoc->createElement("additional_column");
            $col->setAttribute("messageString", $label);
            $col->setAttribute("attributeName", $key);
            $col->setAttribute("sortType", "String");
            if(isSet($lastVisibility)) $col->setAttribute("defaultVisibilty", $lastVisibility);
            switch ($fieldType) {
                case "stars_rate":
                    $col->setAttribute("modifier", "MetaCellRenderer.prototype.starsRateFilter");
                    $col->setAttribute("reactModifier", "ReactMeta.Renderer.renderStars");
                    $col->setAttribute("sortType", "CellSorterValue");
                    $searchables[$key] = $label;
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelStars";
                    $searchablesReactRenderers[$key] = "ReactMeta.Renderer.formPanelStars";
                    break;
                case "css_label":
                    $col->setAttribute("modifier", "MetaCellRenderer.prototype.cssLabelsFilter");
                    $col->setAttribute("reactModifier", "ReactMeta.Renderer.renderCSSLabel");
                    $col->setAttribute("sortType", "CellSorterValue");
                    $searchables[$key] = $label;
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelCssLabels";
                    $searchablesReactRenderers[$key] = "ReactMeta.Renderer.formPanelCssLabels";
                    break;
                case "textarea":
                    $searchables[$key] = $label;
                    break;
                case "string":
                    $searchables[$key] = $label;
                    break;
                case "choice":
                    $searchables[$key] = $label;
                    $col->setAttribute("modifier", "MetaCellRenderer.prototype.selectorsFilter");
                    $col->setAttribute("reactModifier", "ReactMeta.Renderer.renderSelector");
                    $col->setAttribute("sortType", "CellSorterValue");
                    $col->setAttribute("metaAdditional", $this->fieldsAdditionalData[$key]);
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelSelectorFilter";
                    $searchablesReactRenderers[$key] = "ReactMeta.Renderer.formPanelSelectorFilter";
                    break;
                case "tags":
                    $searchables[$key] = $label;
                    $col->setAttribute("reactModifier", "ReactMeta.Renderer.renderTagsCloud");
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelTags";
                    //$searchablesReactRenderers[$key] = "ReactMeta.Renderer.formPanelTags";
                    break;
                default:
                    break;
            }
            $contrib->appendChild($col);
        }

        $selection = $this->getXPath()->query('registry_contributions/client_configs/template_part[@ajxpClass="SearchEngine"]');
        foreach ($selection as $tag) {
            $v = $tag->attributes->getNamedItem("ajxpOptions")->nodeValue;
            if(!empty($v)) $vDat = json_decode($v, true);
            else $vDat = [];
            if(count($searchables)){
                $vDat['metaColumns'] = $searchables;
            }
            if(count($searchablesRenderers)){
                $vDat['metaColumnsRenderers'] = $searchablesRenderers;
                $vDat['reactColumnsRenderers'] = $searchablesReactRenderers;
            }
            $tag->setAttribute("ajxpOptions", json_encode($vDat));
        }

        parent::init($ctx, $this->options);

    }

    /**
     * @return array
     */
    protected function getMetaDefinition()
    {
        if (!$this->metaOptionsParsed) {
            if (!isSet($this->options["meta_types"]) && isSet($this->options["meta_fields"])) {
                // Get type from name
                $val = $this->options["meta_fields"];
                if($val == "stars_rate") $this->options["meta_types"].="stars_rate";
                else if($val == "css_label") $this->options["meta_types"].="css_label";
                else if(substr($val, 0,5) == "area_") $this->options["meta_types"].="textarea";
                else $this->options["meta_types"].="string";
            }
            if(!empty($this->options["meta_additional"])){
                $this->fieldsAdditionalData[$this->options["meta_fields"]] = $this->options["meta_additional"];
            }
            foreach ($this->options as $key => $val) {
                $matches = array();
                if (preg_match('/^meta_fields_(.*)$/', $key, $matches) != 0) {
                    $repIndex = $matches[1];
                    $this->options["meta_fields"].=",".$val;
                    $this->options["meta_labels"].=",".$this->options["meta_labels_".$repIndex];
                    if (!empty($this->options["meta_additional_".$repIndex])) {
                        $this->fieldsAdditionalData[$val] = $this->options["meta_additional_".$repIndex];
                    }
                    if (isSet($this->options["meta_types_".$repIndex])) {
                        $this->options["meta_types"].=",".$this->options["meta_types_".$repIndex];
                    } else {
                        // Get type from name
                        if($val == "stars_rate") $this->options["meta_types"].=","."stars_rate";
                        else if($val == "css_label") $this->options["meta_types"].=","."css_label";
                        else if(substr($val,0,5) == "area_") $this->options["meta_types"].=","."textarea";
                        else $this->options["meta_types"].=","."string";
                    }
                    if (isSet($this->options["meta_visibility_".$repIndex]) && isSet($this->options["meta_visibility"])) {
                        $this->options["meta_visibility"].=",".$this->options["meta_visibility_".$repIndex];
                    }
                }
            }
            $this->metaOptionsParsed = true;
        }

        $fields = $this->options["meta_fields"];
        $arrF = explode(",", $fields);
        $labels = $this->options["meta_labels"];
        $arrL = explode(",", $labels);
        $arrT = explode(",", $this->options["meta_types"]);

        $result = array();
        foreach ($arrF as $index => $value) {
            //make sure value does not contain spaces or things like that
            $value = StringHelper::slugify($value);
            if (isSet($arrL[$index])) {
                $result[$value] = array("label" => $arrL[$index], "type" => $arrT[$index]);
            } else {
                $result[$value] = array("label" => $value, "type" => $arrT[$index]);
            }
        }
        return $result;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws \Exception
     */
    public function editMeta(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $httpVars = $requestInterface->getParsedBody();
        if ($ctx->getRepository()->getDriverInstance($ctx) instanceof \Pydio\Access\Driver\StreamProvider\FS\DemoAccessDriver) {
            throw new \Exception("Write actions are disabled in demo mode!");
        }
        $user = $ctx->getUser();

        if (!UsersService::usersEnabled() && $user!=null && !$user->canWrite($ctx->getRepositoryId())) {
            throw new \Exception("You have no right on this action.");
        }
        $selection = UserSelection::fromContext($ctx, $httpVars);

        $nodes = $selection->buildNodes();
        $nodesDiffs = new \Pydio\Access\Core\Model\NodesDiff();
        $def = $this->getMetaDefinition();
        foreach($nodes as $ajxpNode){

            $newValues = array();
            if(!is_writable($ajxpNode->getUrl())){
                throw new \Exception("You are not allowed to perform this action");
            }
            Controller::applyHook("node.before_change", array(&$ajxpNode));
            foreach ($def as $key => $data) {
                if (isSet($httpVars[$key])) {
                    $newValues[$key] = InputFilter::decodeSecureMagic($httpVars[$key]);
                    if($data["type"] == "tags"){
                        $this->updateTags($ctx, InputFilter::decodeSecureMagic($httpVars[$key]));
                    }
                } else {
                    if (!isset($original)) {
                        $original = $ajxpNode->retrieveMetadata("users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
                    }
                    if (isSet($original) && isset($original[$key])) {
                        $newValues[$key] = $original[$key];
                    }
                }
            }
            $ajxpNode->setMetadata("users_meta", $newValues, false, AJXP_METADATA_SCOPE_GLOBAL);
            Controller::applyHook("node.meta_change", array($ajxpNode));
            $ajxpNode->loadNodeInfo(true, false, "all");
            $nodesDiffs->update($ajxpNode);

        }
        $respStream = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($respStream);
        $respStream->addChunk($nodesDiffs);
    }

    /**
     *
     * @param AJXP_Node $ajxpNode
     * @param bool $contextNode
     * @param bool $details
     * @return void
     */
    public function extractMeta(&$ajxpNode, $contextNode = false, $details = false)
    {
        $metadata = $ajxpNode->retrieveMetadata("users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
        if(empty($metadata)) $metadata = array();
        $ajxpNode->mergeMetadata($metadata);

    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param Boolean $copy
     */
    public function updateMetaLocation($oldNode, $newNode = null, $copy = false)
    {
        $defs = $this->getMetaDefinition();
        $updateField = $createField = null;
        foreach($defs as $f => $data){
            if($data["type"] == "updater") $updateField = $f;
            else if($data["type"] == "creator") $createField = $f;
        }
        $valuesUpdate = (isSet($updateField) || isSet($createField));
        $currentUser = null;
        if($valuesUpdate){
            $refNode = ($oldNode !== null ? $oldNode : $newNode);
            $currentUser = $refNode->getUserId();
        }

        if($oldNode == null && !$valuesUpdate) return;
        if(!$copy && !$valuesUpdate && $this->metaStore->inherentMetaMove()) return;

        if($oldNode == null){
            $oldMeta = $this->metaStore->retrieveMetadata($newNode, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
        }else{
            $oldMeta = $this->metaStore->retrieveMetadata($oldNode, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
        }
        if($valuesUpdate){
            if(isSet($updateField))$oldMeta[$updateField] = $currentUser;
            if(isSet($createField) && $oldNode == null) $oldMeta[$createField] = $currentUser;
        }
        if (!count($oldMeta)) {
            return;
        }
        // If it's a move or a delete, delete old data
        if ($oldNode != null && !$copy) {
            $this->metaStore->removeMetadata($oldNode, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
        }
        // If copy or move, copy data.
        if ($newNode != null) {
            $this->metaStore->setMetadata($newNode, "users_meta", $oldMeta, false, AJXP_METADATA_SCOPE_GLOBAL);
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface|\Zend\Diactoros\Response\JsonResponse
     */
    public function listTags(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface){

        $tags = $this->loadTags($requestInterface->getAttribute("ctx"));
        if(empty($tags)) $tags = array();
        $responseInterface = new \Zend\Diactoros\Response\JsonResponse($tags);
        return $responseInterface;

    }

    /**
     * @param ContextInterface $ctx
     * @return array
     */
    protected function loadTags(ContextInterface $ctx){

        $store = ConfService::getConfStorageImpl();
        if(!($store instanceof \Pydio\Conf\Sql\SqlConfDriver)) return array();
        $data = array();
        $store->simpleStoreGet("meta_user_tags", $ctx->getRepositoryId(), "serial", $data);
        return $data;

    }

    /**
     * @param ContextInterface $ctx
     * @param $tagString
     * @throws \Exception
     */
    protected function updateTags(ContextInterface $ctx, $tagString){

        $store = ConfService::getConfStorageImpl();
        if(!($store instanceof \Pydio\Conf\Sql\SqlConfDriver)) return;
        $tags = $this->loadTags($ctx);
        $tags = array_merge($tags, array_map("trim", explode(",", $tagString)));
        $tags = array_unique($tags);
        $store->simpleStoreSet("meta_user_tags", $ctx->getRepositoryId(), array_values($tags), "serial");

    }

}

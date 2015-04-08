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
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class UserMetaManager extends AJXP_AbstractMetaSource
{
    /**
     * @var MetaStoreProvider
     */
    protected $metaStore;
    protected $fieldsAdditionalData = array();
    private $metaOptionsParsed = false;

    public function init($options)
    {
        $this->options = $options;
        // Do nothing
    }

    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);

        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new Exception("The 'meta.user' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($accessDriver);

        //$messages = ConfService::getMessages();
        $def = $this->getMetaDefinition();
        foreach($def as $k => &$d){
            if(isSet($this->fieldsAdditionalData[$k])) $d["data"] = $this->fieldsAdditionalData[$k];
        }
        $this->exposeConfigInManifest("meta_definitions", json_encode($def));
        if(!isSet($this->options["meta_visibility"])) $visibilities = array("visible");
        else $visibilities = explode(",", $this->options["meta_visibility"]);
        $editButton = '';
        $u = AuthService::getLoggedUser();
        if($u != null && $u->canWrite($this->accessDriver->repository->getId())){
            $editButton = '<span class="icon-edit" data-ajxpAction="edit_user_meta" title="AJXP_MESSAGE[meta.user.1]"></span><span class="user_meta_change" style="display: none;" data-ajxpAction="edit_user_meta" title="AJXP_MESSAGE[meta.user.1]">AJXP_MESSAGE[457]</span>';
        }
        $cdataHead = '<div>
                        <div class="panelHeader infoPanelGroup" colspan="2">'.$editButton.'AJXP_MESSAGE[meta.user.1]</div>
                     ';
        $cdataFoot = '</div>';
        $cdataParts = "";

        $selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="FilesList"]/columns');
        $contrib = $selection->item(0);
        $even = false;
        $searchables = array();
        $searchablesRenderers = array();
        $index = 0;
        $fieldType = "text";
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
                    $col->setAttribute("sortType", "CellSorterValue");
                    $searchables[$key] = $label;
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelStars";
                    break;
                case "css_label":
                    $col->setAttribute("modifier", "MetaCellRenderer.prototype.cssLabelsFilter");
                    $col->setAttribute("sortType", "CellSorterValue");
                    $searchables[$key] = $label;
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelCssLabels";
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
                    $col->setAttribute("sortType", "CellSorterValue");
                    $col->setAttribute("metaAdditional", $this->fieldsAdditionalData[$key]);
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelSelectorFilter";
                    break;
                case "tags":
                    $searchables[$key] = $label;
                    $searchablesRenderers[$key] = "MetaCellRenderer.prototype.formPanelTags";
                    break;
                default:
                    break;
            }
            $contrib->appendChild($col);
            $trClass = ($even?" class=\"even infoPanelRow\"":" class=\"infoPanelRow\"");
            $even = !$even;
            $cdataParts .= '<div'.$trClass.'><div class="infoPanelLabel">'.$label.'</div><div class="infoPanelValue" data-metaType="'.$fieldType.'" id="ip_'.$key.'">#{'.$key.'}</div></div>';
        }

        $selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="InfoPanel"]/infoPanelExtension');
        $contrib = $selection->item(0);
        $contrib->setAttribute("attributes", implode(",", array_keys($def)));
        if (!empty($this->fieldsAdditionalData)) {
            $contrib->setAttribute("metaAdditional", json_encode($this->fieldsAdditionalData));
        }
        $contrib->setAttribute("modifier", "MetaCellRenderer.prototype.infoPanelModifier");

        $htmlSel = $this->xPath->query('html', $contrib);
        $html = $htmlSel->item(0);
        $cdata = $this->manifestDoc->createCDATASection($cdataHead . $cdataParts . $cdataFoot);
        $html->appendChild($cdata);

        $selection = $this->xPath->query('registry_contributions/client_configs/template_part[@ajxpClass="SearchEngine"]');
        foreach ($selection as $tag) {
            $v = $tag->attributes->getNamedItem("ajxpOptions")->nodeValue;
            $metaV = count($searchables)? '"metaColumns":'.json_encode($searchables): "";
            if (count($searchablesRenderers)) {
                $metaV .= ',"metaColumnsRenderers":'.json_encode($searchablesRenderers);
            }
            if (!empty($v) && trim($v) != "{}") {
                $v = str_replace("}", ", ".$metaV."}", $v);
            } else {
                $v = "{".$metaV."}";
            }
            $tag->setAttribute("ajxpOptions", $v);
        }

        parent::init($this->options);

    }

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
            if (isSet($arrL[$index])) {
                $result[$value] = array("label" => $arrL[$index], "type" => $arrT[$index]);
            } else {
                $result[$value] = array("label" => $value, "type" => $arrT[$index]);
            }
        }
        return $result;
    }

    public function editMeta($actionName, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$actionName])) return;
        if (is_a($this->accessDriver, "demoAccessDriver")) {
            throw new Exception("Write actions are disabled in demo mode!");
        }
        $repo = $this->accessDriver->repository;
        $user = AuthService::getLoggedUser();
        if (!AuthService::usersEnabled() && $user!=null && !$user->canWrite($repo->getId())) {
            throw new Exception("You have no right on this action.");
        }
        $selection = new UserSelection();
        $selection->initFromHttpVars($httpVars);
        $currentFile = $selection->getUniqueFile();

        $nodes = $selection->buildNodes($this->accessDriver);
        $nodesDiffs = array();
        $def = $this->getMetaDefinition();
        foreach($nodes as $ajxpNode){

            $newValues = array();
            //$ajxpNode->setDriver($this->accessDriver);
            AJXP_Controller::applyHook("node.before_change", array(&$ajxpNode));
            foreach ($def as $key => $data) {
                if (isSet($httpVars[$key])) {
                    $newValues[$key] = AJXP_Utils::decodeSecureMagic($httpVars[$key]);
                    if($data["type"] == "tags"){
                        $this->updateTags(AJXP_Utils::decodeSecureMagic($httpVars[$key]));
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
            AJXP_Controller::applyHook("node.meta_change", array($ajxpNode));

            $nodesDiffs[$ajxpNode->getPath()] = $ajxpNode;

        }
        /*
        $urlBase = $this->accessDriver->getResourceUrl($currentFile);
        $ajxpNode = new AJXP_Node($urlBase);

        $newValues = array();
        $def = $this->getMetaDefinition();
        $ajxpNode->setDriver($this->accessDriver);
        AJXP_Controller::applyHook("node.before_change", array(&$ajxpNode));
        foreach ($def as $key => $data) {
            if (isSet($httpVars[$key])) {
                $newValues[$key] = AJXP_Utils::decodeSecureMagic($httpVars[$key]);
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
        AJXP_Controller::applyHook("node.meta_change", array($ajxpNode));
        */
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::writeNodesDiff(array("UPDATE" => $nodesDiffs), true);
        AJXP_XMLWriter::close();
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
        if (count($metadata)) {
            // @todo : Should be UTF8-IZED at output only !!??
            // array_map(array("SystemTextEncoding", "toUTF8"), $metadata);
        }
        $ajxpNode->mergeMetadata($metadata);

    }

    /**
     *
     * @param AJXP_Node $oldNode
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
            $currentUser = AuthService::getLoggedUser()->getId();
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

    public function listTags($actionName, &$httpVars, &$fileVars){

        HTMLWriter::charsetHeader("application/json");
        $tags = $this->loadTags();
        if(empty($tags)) $tags = array();
        echo json_encode($tags);

    }

    protected function loadTags(){
        $store = ConfService::getConfStorageImpl();
        if(!is_a($store, "sqlConfDriver")) return array();
        $data = array();
        $store->simpleStoreGet("meta_user_tags", ConfService::getRepository()->getId(), "serial", $data);
        return $data;
    }

    protected function updateTags($tagString){
        $store = ConfService::getConfStorageImpl();
        if(!is_a($store, "sqlConfDriver")) return;
        $tags = $this->loadTags();
        $tags = array_merge($tags, array_map("trim", explode(",", $tagString)));
        $tags = array_unique($tags);
        $store->simpleStoreSet("meta_user_tags", ConfService::getRepository()->getId(), array_values($tags), "serial");
    }

}

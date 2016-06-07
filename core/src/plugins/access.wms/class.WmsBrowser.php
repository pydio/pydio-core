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
namespace Pydio\Access\Driver\DataProvider;

use DOMDocument;
use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access a WMS Server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class WmsBrowser extends AbstractAccessDriver
{
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        parent::accessPreprocess($requestInterface);

        if($requestInterface->getAttribute("action")){
            return ;
        }

        $ctx = $requestInterface->getAttribute("ctx");
        $host = $ctx->getRepository()->getContextOption($ctx, "HOST");
        $doc = new DOMDocument();
        $doc->load($host . "?request=GetCapabilities");
        $xPath = new DOMXPath($doc);

        $httpVars = $requestInterface->getParsedBody();
        $dir = $httpVars["dir"];

        $x = new SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);
        $nodesList = new NodesList();
        $nodesList->initColumnsData("filelist");
        $nodesList->appendColumn("wms.1", "ajxp_label");
        $nodesList->appendColumn("wms.6", "srs");
        $nodesList->appendColumn("wms.4", "style");
        $nodesList->appendColumn("wms.5", "keyword");

        $layers = $xPath->query("Capability/Layer/Layer");
        // Detect "levels"
        $levels = array();
        $leafs = array();
        $styleLevels = $prefixLevels = false;
        foreach ($layers as $layer) {
            $name = $xPath->evaluate("Name", $layer)->item(0)->nodeValue;
            $stylesList = $xPath->query("Style/Name", $layer);
            if (strstr($name, ":")!==false) {
                $exp = explode(":", $name);
                if(!isSet($levels[$exp[0]]))$levels[$exp[0]] = array();
                $levels[$exp[0]][] = $layer;
                $prefixLevels = true;
            } else if ($stylesList->length > 1) {
                if(!isSet($levels[$name])) $levels[$name] = array();
                foreach ($stylesList as $style) {
                    $levels[$name][$style->nodeValue] = $layer;
                }
                $styleLevels = true;
            } else {
                $leafs[] = $layer;
            }
        }
        if ($dir == "/" || $dir == "") {
            $this->listLevels($nodesList, $levels);
            $this->listLayers($host, $nodesList, $leafs, $xPath);
        } else if (isSet($levels[basename($dir)])) {
            $this->listLayers($host, $nodesList, $levels[basename($dir)], $xPath, ($styleLevels?array($this,"replaceStyle"):null));
        }

        $x->addChunk($nodesList);

    }

    /**
     * @param NodesList $NodesList
     * @param $levels
     */
    public function listLevels(&$NodesList, $levels)
    {
        foreach ($levels as $key => $layers) {
            $node = new AJXP_Node("/$key", array(
                "icon"			=> "folder.png",
                "openicon"		=> "openfolder.png",
                "parentname"	=> "/",
                "srs"			=> "-",
                "keywords"		=> "-",
                "style"			=> "-"
            ));
            $node->setLeaf(false);
            $NodesList->addBranch($node);
        }

    }

    public function replaceStyle($key, $metaData)
    {
        if(!is_string($key)) return $metaData ;
        $metaData["name"] = $metaData["name"]."___".$key;
        $metaData["title"] = $metaData["title"]." (".$key.")";
        $metaData["style"] = $key;
        return $metaData;
    }

    /**
     * @param string $host
     * @param NodesList $NodesList
     * @param array $nodeList
     * @param DOMXPath $xPath
     * @param callable|null $replaceCallback
     * @throws \Exception
     */
    public function listLayers($host, &$NodesList, $nodeList, $xPath, $replaceCallback = null)
    {
        foreach ($nodeList as  $key => $node) {
            $name = $xPath->evaluate("Name", $node)->item(0)->nodeValue;
            $title =$xPath->evaluate("Title", $node)->item(0)->nodeValue;
            $srs =$xPath->evaluate("SRS", $node)->item(0)->nodeValue;
            $metaData = array(
                "icon"			=> "wms_images/mimes/ICON_SIZE/domtreeviewer.png",
                "parentname"	=> "/",
                "name"			=> $name,
                "title"			=> $title,
                "ajxp_mime" 	=> "wms_layer",
                "srs"			=> $srs,
                "wms_url"		=> $host
            );
            $style = $xPath->query("Style/Name", $node)->item(0)->nodeValue;
            $metaData["style"] = $style;
            $keys = array();
            $keywordList = $xPath->query("KeywordList/Keyword", $node);
            if ($keywordList->length) {
                foreach ($keywordList as $keyword) {
                    $keys[] = $keyword->nodeValue;
                }
            }
            $metaData["keywords"] = implode(",",$keys);
            $metaData["queryable"] = ($node->attributes->item(0)->value == "1"?"True":"False");
            $bBoxAttributes = array();
            try {
                $bBoxAttributes = $xPath->query("LatLonBoundingBox", $node)->item(0)->attributes;
                $attributes = $xPath->query("BoundingBox", $node)->item(0)->attributes;
                if (isSet($attributes)) {
                    $bBoxAttributes = $attributes;
                }
            } catch (\Exception $e) {}
            foreach ($bBoxAttributes as $domAttr) {
                $metaData["bbox_".$domAttr->name] = $domAttr->value;
            }

            if ($replaceCallback != null) {
                $metaData = call_user_func($replaceCallback, $key, $metaData);
            }

            $newNode = new AJXP_Node("/".$metaData["name"], $metaData);
            $newNode->setLeaf(true);
            $NodesList->addBranch($newNode);
        }
    }


    /**
     * @param ContextInterface $ctx
     */
    protected function initRepository(ContextInterface $ctx)
    {
        // TODO: Implement initRepository() method.
    }
}

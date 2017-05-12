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
namespace Pydio\Core\Utils;

use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Static methods to help handling XML
 * @package Pydio\Core\Utils
 */
class XMLHelper
{
    /**
     * @var bool|string
     */
    private static $headerSent = false;

    /**
     * Simple XML element build from associative array. Can pass specific $children for nested elements.
     * @param string $tagName
     * @param array $attributes
     * @param string $xmlChildren
     * @return string
     */
    public static function toXmlElement($tagName, $attributes, $xmlChildren = "")
    {
        $buffer = "<$tagName ";
        foreach ($attributes as $attName => $attValue) {
            $buffer .= "$attName=\"" . StringHelper::xmlEntities($attValue) . "\" ";
        }
        if (!strlen($xmlChildren)) {
            $buffer .= "/>";
        } else {
            $buffer .= ">" . $xmlChildren . "</$tagName>";
        }
        return $buffer;
    }

    /**
     * Create plain PHP associative array from XML.
     *
     * Example usage:
     *   $xmlNode = simplexml_load_file('example.xml');
     *   $arrayData = xmlToArray($xmlNode);
     *   echo json_encode($arrayData);
     *
     * @param \DOMNode $domXml The dom node to load
     * @param array $options Associative array of options
     * @return array
     * @link http://outlandishideas.co.uk/blog/2012/08/xml-to-json/ More info
     * @author Tamlyn Rhodes <http://tamlyn.org>
     * @license http://creativecommons.org/publicdomain/mark/1.0/ Public Domain
     */
    public static function xmlToArray($domXml, $options = array())
    {
        $xml = simplexml_import_dom($domXml);
        $defaults = array(
            'namespaceSeparator' => ':',//you may want this to be something other than a colon
            'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
            'alwaysArray' => array(),   //array of xml tag names which should always become arrays
            'autoArray' => true,        //only create arrays for tags which appear more than once
            'textContent' => '$',       //key used for the text content of elements
            'autoText' => true,         //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,       //optional search and replace on tag and attribute names
            'keyReplace' => false       //replace values for above search values (as passed to str_replace())
        );
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace

        //get attributes from all namespaces
        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }

        //get child nodes from all namespaces
        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = self::xmlToArray($childXml, $options);
                list($childTagName, $childProperties) = each($childArray);

                //replace characters in tag name
                if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                //add namespace prefix, if any
                if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                            ? array($childProperties) : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                }
            }
        }

        //get text content of node
        $textContentArray = array();
        $plainText = trim((string)$xml);
        if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

        //stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

        //return node as array
        return array(
            $xml->getName() => $propertiesArray
        );
    }

    /**
     * Wrap xml inside a <tree>...</tree> document, including <?xml> declaration.
     * @param $content
     * @param string $docNode
     * @param array $attributes
     * @return string
     */
    public static function wrapDocument($content, $docNode = "tree", $attributes = array())
    {

        if (self::$headerSent !== false && self::$headerSent == $docNode) {
            return $content;
        }
        //header('Content-Type: text/xml; charset=UTF-8');
        //header('Cache-Control: no-cache');
        $buffer = '<?xml version="1.0" encoding="UTF-8"?>';
        $attString = "";
        if (count($attributes)) {
            foreach ($attributes as $name => $value) {
                $attString .= "$name=\"$value\" ";
            }
        }
        self::$headerSent = $docNode;
        $buffer .= "<$docNode $attString>";
        $buffer .= $content;
        $buffer .= "</$docNode>";
        return $buffer;

    }
}
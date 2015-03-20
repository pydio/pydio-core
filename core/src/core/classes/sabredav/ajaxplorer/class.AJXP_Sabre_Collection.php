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
 * @package Pydio
 * @subpackage SabreDav
 */
class AJXP_Sabre_Collection extends AJXP_Sabre_Node implements Sabre\DAV\ICollection
{

    protected $children;

    /**
     * Creates a new file in the directory
     *
     * Data will either be supplied as a stream resource, or in certain cases
     * as a string. Keep in mind that you may have to support either.
     *
     * After succesful creation of the file, you may choose to return the ETag
     * of the new file here.
     *
     * The returned ETag must be surrounded by double-quotes (The quotes should
     * be part of the actual string).
     *
     * If you cannot accurately determine the ETag, you should not return it.
     * If you don't store the file exactly as-is (you're transforming it
     * somehow) you should also not return an ETag.
     *
     * This means that if a subsequent GET to this new file does not exactly
     * return the same contents of what was submitted here, you are strongly
     * recommended to omit the ETag.
     *
     * @param string $name Name of the file
     * @param resource|string $data Initial payload
     * @return null|string
     */
    public function createFile($name, $data = null)
    {
        try {
            $name = ltrim($name, "/");
            AJXP_Logger::debug("CREATE FILE $name");

            AJXP_Controller::findActionAndApply("mkfile", array(
                "dir" => $this->path,
                "filename" => $name
            ), array());

            if ( $data != null && is_file($this->getUrl()."/".$name)) {

                $p = $this->path."/".$name;
                $this->getAccessDriver()->nodeWillChange($p, intval($_SERVER["CONTENT_LENGTH"]));
                //AJXP_Logger::debug("Should now copy stream or string in ".$this->getUrl()."/".$name);
                if (is_resource($data)) {
                    $stream = fopen($this->getUrl()."/".$name, "w");
                    stream_copy_to_stream($data, $stream);
                    fclose($stream);
                } else if (is_string($data)) {
                    file_put_contents($data, $this->getUrl()."/".$name);
                }

                $toto = null;
                $this->getAccessDriver()->nodeChanged($toto, $p);

            }
            $node = new AJXP_Sabre_NodeLeaf($this->path."/".$name, $this->repository, $this->getAccessDriver());
            if (isSet($this->children)) {
                $this->children = null;
            }
            return $node->getETag();

        } catch (Exception $e) {
            AJXP_Logger::debug("Error ".$e->getMessage(), $e->getTraceAsString());
            return null;
        }


    }

    /**
     * Creates a new subdirectory
     *
     * @param string $name
     * @return void
     */
    public function createDirectory($name)
    {
        if (isSet($this->children)) {
            $this->children = null;
        }

        AJXP_Controller::findActionAndApply("mkdir", array(
            "dir" => $this->path,
            "dirname" => $name
        ), array());

    }

    /**
     * Returns a specific child node, referenced by its name
     *
     * @param string $name
     * @throws Sabre\DAV\Exception\NotFound
     * @return Sabre\DAV\INode
     */
    public function getChild($name)
    {
        foreach ($this->getChildren() as $child) {

            if ($child->getName()==$name) return $child;

        }
        throw new Sabre\DAV\Exception\NotFound('File not found: ' . $name);

    }

    /**
     * Returns an array with all the child nodes
     *
     * @return Sabre\DAV\INode[]
     */
    public function getChildren()
    {
        if (isSet($this->children)) {
            return $this->children;
        }


        $contents = array();
        $errors = array();

        $nodes = scandir($this->getUrl());

        foreach ($nodes as $file) {
            if ($file == "." || $file == "..") {
                continue;
            }
            // This function will perform the is_dir() call and update $isDir variable.
            if (!$this->getAccessDriver()->filterNodeName($this->getUrl(), $file, $isLeaf, array("d"=>true, "f"=>true, "z"=>true))){
                continue;
            }
            if ( !$isLeaf ) {
                // Add collection without any children
                $contents[] = new AJXP_Sabre_Collection($this->path."/".$file, $this->repository, $this->getAccessDriver());
            } else {
                // Add files without content
                $contents[] = new AJXP_Sabre_NodeLeaf($this->path."/".$file, $this->repository, $this->getAccessDriver());
            }
        }
        $this->children = $contents;

        $ajxpNode = new AJXP_Node($this->getUrl());
        AJXP_Controller::applyHook("node.read", array(&$ajxpNode));

        return $contents;

    }

    /**
     * Checks if a child-node with the specified name exists
     *
     * @param string $name
     * @return bool
     */
    public function childExists($name)
    {
        foreach ($this->getChildren() as $child) {

            if ($child->getName()==$name) return true;

        }
        return false;

    }
}

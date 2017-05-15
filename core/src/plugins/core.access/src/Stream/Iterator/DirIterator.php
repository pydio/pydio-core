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

namespace Pydio\Access\Core\Stream\Iterator;

/**
 * Class DirIterator
 * @package Pydio\Access\Core\Stream\Iterator
 */
class DirIterator implements \Iterator {

    private $position = 0;
    private $array = array();

    /**
     * Fake Directory Iterator based on file names and stats
     *
     * @param array $array
     */
    public function __construct($array = array()) {
        $this->position = 0;
        $this->array = $array;
    }

    /**
     * Rewind
     */
    function rewind() {
        $this->position = 0;
    }

    /**
     * Current value
     *
     * @return array value
     */
    function current() {
        return $this->array[$this->position];
    }

    /**
     * Current key
     *
     * @return int index
     */
    function key() {
        return $this->position;
    }

    /**
     * Move to next position
     */
    function next() {
        ++$this->position;
    }

    /**
     * Check validity
     */
    function valid() {
        return isset($this->array[$this->position]);
    }
}

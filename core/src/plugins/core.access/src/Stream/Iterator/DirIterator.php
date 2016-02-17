<?php

namespace CoreAccess\Stream\Iterator;

class DirIterator implements \Iterator {

    private $position = 0;
    private $array = array();

    public function __construct($array = array()) {
        $this->position = 0;
        $this->array = $array;
    }

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->array[$this->position];
    }

    function key() {
        return $this->position;
    }

    function next() {
        ++$this->position;
    }

    function valid() {
        return isset($this->array[$this->position]);
    }
}

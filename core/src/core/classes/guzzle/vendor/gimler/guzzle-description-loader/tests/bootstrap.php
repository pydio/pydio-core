<?php

error_reporting(E_ALL | E_STRICT);

// Ensure that composer has installed all dependencies
if (!file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'composer.lock')) {
    die("Dependencies must be installed using composer:\n\ncomposer.phar install\n\n"
        . "See https://github.com/composer/composer/blob/master/README.md for help with installing composer\n");
}

// Include the composer autoloader
$loader = require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

define('FIXTURES_PATH', __DIR__.'/fixtures');
<?php
/**************************************************/
/*	ADVANCED : DO NOT CHANGE THESE VARIABLES BELOW
/**************************************************/
define("AJXP_VERSION", "2.7.1");
define("AJXP_VERSION_DATE", "2010/04/04");

$installPath = realpath(dirname(__FILE__)."/../..");
define("INSTALL_PATH", $installPath);
define("USERS_DIR", $installPath."/server/users");
define("SERVER_ACCESS", "content.php");
define("ADMIN_ACCESS", "admin.php");
define("IMAGES_FOLDER", "client/images");
define("CLIENT_RESOURCES_FOLDER", "client");
define("SERVER_RESOURCES_FOLDER", "server/classes");
define("DOCS_FOLDER", "client/doc");
define("TESTS_RESULT_FILE", $installPath."/server/conf/diag_result.php");

define("OLD_USERS_DIR", $installPath."/bookmarks");
define("INITIAL_ADMIN_PASSWORD", "admin");

$logger = AJXP_Logger::getInstance();
$logger->initStorage(INSTALL_PATH."/server/logs/");
?>
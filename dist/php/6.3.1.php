<?php

// FORCE bootstrap_repositories copy
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php", AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php");
}

// Add new repo to root role
$rootRole = AuthService::getRole("AJXP_GRP_/");
$rootRole->setAcl("inbox", "r");
AuthService::updateRole($rootRole);
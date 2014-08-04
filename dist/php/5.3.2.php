<?php
// FORCE bootstrap_repositories copy, to enable ajxp_home workspace
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php", AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php");
}

echo "The bootstrap_repositories files was replaced by the new version, the .pre-update version is kept.";

$root_role = AuthService::getRole("ROOT_ROLE");
if($root_role !== false){
    $root_role->setParameterValue("core.conf", "DEFAULT_START_REPOSITORY", "ajxp_home");
    AuthService::updateRole($root_role);
}
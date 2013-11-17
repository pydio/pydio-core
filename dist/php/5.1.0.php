<?php
// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php", AJXP_INSTALL_PATH."/conf/bootstrap_context.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_context.php");
}

// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php", AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php");
}

echo "The bootstrap_context and bootstrap_repositories files were replaced by the new version, the .pre-update version is kept.";

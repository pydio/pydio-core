<?php

// FORCE extensions.conf copy, otherwise icons don't display correctly
if (is_file(AJXP_INSTALL_PATH."/conf/extensions.conf.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/extensions.conf.php", AJXP_INSTALL_PATH."/conf/extensions.conf.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/extensions.conf.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/extensions.conf.php");
}


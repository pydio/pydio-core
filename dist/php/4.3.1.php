<?php
// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php", AJXP_INSTALL_PATH."/conf/bootstrap_context.php.orig");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_context.php");
}

echo "The bootstrap_context was replaced by the new version, the .orig version is kept.";

// Clear i18n cache
$i18nFiles = glob(dirname(AJXP_PLUGINS_MESSAGES_FILE)."/i18n/*.ser");
if (is_array($i18nFiles)) {
    foreach ($i18nFiles as $file) {
        @unlink($file);
    }
}
echo "Force clearing i18n cache";

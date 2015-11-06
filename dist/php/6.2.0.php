<?php

// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php", AJXP_INSTALL_PATH."/conf/bootstrap_context.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_context.php");
}

// RE-ENABLE NEWLY DISABLED DRIVERS TO AVOID DISAPPEARING FEATURES
$disabledPlugins = array(
    "access.demo", "access.imap", "access.jsapi", "access.mysql", "access.sftp", "access.sft_psl", "access.smb", "access.webdav",
    "auth.basic_http", "auth.custom_db", "auth.ftp", "auth.radius", "auth.remote_ajxp", "auth.serial", "auth.smb",
    "conf.serial", "index.elasticsearch", "log.syslog", "meta.svn", "metastore.xattr"
);
$skipReenable = array("access.demo", "access.jsapi", "access.mysql", "auth.remote_ajxp", "meta.svn");
$enabled = array();
$skipped = array();
$confStorage = ConfService::getConfStorageImpl();
foreach($disabledPlugins as $plugin){

    $plugObject = AJXP_PluginsService::findPluginById($plugin);
    if(is_a($plugObject, "AJXP_Plugin")){

        if($plugObject->isEnabled()) continue;

        if(in_array($plugin, $skipReenable)){
            $skipped[]= $plugin;
            continue;
        }
        list($type, $name) = explode(".", $plugin);

        $options = $confStorage->loadPluginConfig($type, $name);
        $options["AJXP_PLUGIN_ENABLED"] = true;
        $confStorage->savePluginConfig($plugin, $options);
        $enabled[] = $plugin;

    }

}

echo "To improve performances, many plugins were disabled by default in the new version.<br>";
echo "The following ones were automatically re-enabled to avoid conflicts with your setup : ".implode(", ", $enabled). "<br><br>";
echo "Warning, the following ones were not re-enabled, so please make sure to switch them on manually if you use them : ".implode(", ", $skipped). "<br><br>";
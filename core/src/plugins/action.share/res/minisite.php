<!DOCTYPE html>
<html xmlns:ajxp>
    <head>
        <title>AJXP_APPLICATION_TITLE</title>
        <base href="AJXP_PATH_TO_ROOT"/>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
        <link rel="icon" type="image/x-png" href="plugins/gui.ajax/res/build/themes/AJXP_THEME/images/html-folder.png">
        <link rel="stylesheet" type="text/css" href="plugins/gui.ajax/res/build/pydio.AJXP_THEME.min.css?v=AJXP_CURRENT_VERSION">
        <!--[if IE 8]>
        <script src="plugins/gui.ajax/res/js/vendor/es6/es5-shim.min.js"></script>
        <script src="plugins/gui.ajax/res/js/vendor/es6/es5-sham.min.js"></script>
        <![endif]-->
        <!--[if IE 7]>
        <script src="plugins/gui.ajax/res/js/vendor/es6/json3.min.js"></script>
        <![endif]-->
        <link rel="stylesheet" href="plugins/action.share/res/minisite.css"/>
        <style type="text/css">

        </style>
        <script language="javascript" type="text/javascript" src="plugins/gui.ajax/res/build/pydio.boot.min.js"></script>
        <script type="text/javascript">
            var pydio, MessageHash={};
            var startParameters = {
                "BOOTER_URL":"AJXP_PUBLIC_BASEURI/?get_action=get_boot_conf&goto=AJXP_START_REPOSITORY&minisite_session=AJXP_LINK_HASH",
                "EXT_REP":"\/",
                "MAIN_ELEMENT":"AJXP_TEMPLATE_NAME",
                "SERVER_PREFIX_URI": "",
                "PRESET_LOGIN":"AJXP_PRELOGED_USER",
                "HASH_LOAD_ERROR":"AJXP_HASH_LOAD_ERROR",
                "PASSWORD_AUTH_ONLY":true,
                "SERVER_PERMANENT_PARAMS":{"minisite_session":"AJXP_LINK_HASH"}
            };
            if(startParameters["PRESET_LOGIN"] == "ajxp_legacy_minisite"){
                delete startParameters["PRESET_LOGIN"];
                startParameters["PASSWORD_AUTH_ONLY"] = false;
            }
            /*
            document.observe("ajaxplorer:before_gui_load", function(e){
               document.documentElement.className += " ajxp_theme_AJXP_THEME";
           });
           */
            if(startParameters['HASH_LOAD_ERROR']){
                docReady(function(){
                    document.getElementById(startParameters['MAIN_ELEMENT']).innerHTML = '<div class="hash_load_error">'+startParameters['HASH_LOAD_ERROR']+'</div>';
                });
            }else{
                window.useReactPydioUI = true;
                window.pydioBootstrap = new PydioBootstrap(startParameters);
                window.ajxpMinisite = true;
                docReady(function(){
                    var cookieEnabled=(navigator.cookieEnabled)? true : false
                       if (typeof navigator.cookieEnabled=="undefined" && !cookieEnabled) {
                           document.cookie="testcookie";
                           cookieEnabled=(document.cookie.indexOf("testcookie")!=-1)? true : false;
                       }
                       if (!cookieEnabled) {
                           alert("AJXP_MESSAGE[share_center.76]");
                       }
                });
            }
        </script>
        <noscript><h2>AJXP_MESSAGE[share_center.77]</h2></noscript>
    </head>

    <body style="overflow: hidden;" class="react-mui-context AJXP_PRELOGED_USER">
        <div id="AJXP_TEMPLATE_NAME" class="vertical_fit vertical_layout"></div>
    </body>
</html>

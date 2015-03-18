<!DOCTYPE html>
<html xmlns:ajxp>
    <head>
        <title>AJXP_APPLICATION_TITLE</title>
        <base href="AJXP_PATH_TO_ROOT"/>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
        <link rel="icon" type="image/x-png" href="plugins/gui.ajax/res/themes/AJXP_THEME/images/html-folder.png">
        <link rel="stylesheet" type="text/css" href="plugins/gui.ajax/res/themes/AJXP_THEME/css/allz.css">
        <link rel="stylesheet" href="plugins/gui.ajax/res/themes/AJXP_THEME/css/font-awesome.css"/>
        <link rel="stylesheet" href="plugins/gui.ajax/res/themes/AJXP_THEME/css/animate-custom.css"/>
        <style type="text/css">
            #widget_title{
                font-size: 30px;
                font-weight: normal;
                padding: 10px 0 0 5px;
                margin-right: 5px;
                color: rgb(111,123,136);
                line-height: 25px;
            }

            .hash_load_error{
                text-align: center;
                color: #dddddd;
                margin-top: 20%;
                font-size: 3em;
            }

            #widget_title div.repository_title{
                font-size: 30px;
            }

            #widget_title div.repository_description{
                font-size: 12px;
            }

            #open_with_unique_button{
                display: none;
            }

            #ajxp_embed_template #cpane_header,
            #ajxp_dropbox_template #cpane_header
            {
                background: rgb(54, 60, 68);
                padding: 6px 11px 10px;
            }

            #ajxp_embed_template #breadcrumb,
            #ajxp_dropbox_template #breadcrumb
            {
                color: white;
                margin-top: 2px;
            }

            #ajxp_embed_template #display_toolbar,
            #ajxp_dropbox_template #display_toolbar
            {
                width: 430px;
                white-space: nowrap;
                font-size: 12px;
            }

            #ajxp_dropbox_template #inline_toolbar span.actionbar_button_label,
            #ajxp_embed_template #inline_toolbar span.actionbar_button_label,
            #ajxp_embed_template #minisite_toolbar span.actionbar_button_label,
            #ajxp_dropbox_template #minisite_toolbar span.actionbar_button_label,
            #ajxp_film_strip #minisite_toolbar span.actionbar_button_label,
            #ajxp_film_strip #inline_toolbar span.actionbar_button_label
            {
                display: inline-block;
                margin-left: 2px;
                margin-right: 6px;
            }

            #ajxp_dropbox_template .widget_logo {
                top: inherit;
                bottom: 19px;
                right: 12px;
                height: 95px;
            }

            #ajxp_dropbox_template .droparea{
                background-position: 50% 50%;
                background-size: 220px;
            }
            #ajxp_dropbox_template .thumbnail_selectable_cell{
                background-color: transparent;
            }
            #ajxp_dropbox_template #browser{
                background-color: rgb(54, 60, 68);
                padding: 15px;
            }
            #ajxp_dropbox_template #content_pane{
                border: 5px dotted rgb(233, 233, 233) !important;
                margin: 0 5px 30px;
                padding: 10px;
                border-radius: 10px;
            }

            #breadcrumb span.icon-refresh.ajxp-goto-refresh {
                margin-left: 5px;
                opacity: 0.2;
            }

            .widget_logo {
                background-repeat: no-repeat;
                margin-right: 5px;
                position: absolute;
                top: 0;
                right: 0;
                height: 70px;
                width: 180px;
                z-index: 50;
                padding:0 !important;
            }

            #ajxp_embed_template .widget_logo
            {
                top: inherit;
                bottom: 0;
                left: inherit;
                right: 0;
                height: 95px;
            }

            #ajxp_shared_folder{
                width:100%;
                text-align:left;
                /* THESE ONE ARE IMPORTANT */
                overflow:hidden;
                position:relative;
            }
            #ajxp_shared_folder .widget_logo {
                height: 43px;
                padding-top: 0 !important;
            }
            .action_bar{
                background-color: #ffffff;
                height: 41px !important;
            }
            .action_bar > .toolbarGroup{
                height: auto;
            }
            div.separator{
                border-left-width: 0;
            }
            #display_toolbar{
                margin-top: 3px;
                margin-right: 3px;
            }
            div.detailed div.FL-inlineToolbar{
                margin-top: -2px;
            }
            div.detailed div.FL-inlineToolbar div.toolbarGroup {
                margin-left: -8px;
                min-width: 0;
            }
            .ajxpNodeProvider span.actionbar_button_label {
                display: none;
            }
            .ajxpNodeProvider.detailed span.actionbar_button_label {
                display: inline-block;
            }
            tr div.FL-inlineToolbar {
                margin-left: 15px;
            }
            span.list_selectable_span div#inline_toolbar a
            {
                margin-top: 0;
                padding: 3px 5px;
                position: relative;
                top: -2px;
            }
            div.ajxpNodeProvider:not(.detailed) div#inline_toolbar a
            {
                padding: 3px 5px;
            }
            div.ajxpNodeProvider:not(.detailed) .toolbarGroup {
                position: absolute;
                top: 1px;
                right: 1px;
            }
            #element_overlay{
                top:0 !important; left:0 !important;
            }
            body.ajxp_preloged_user a#logout_button{
                display: none;
            }
            .thumbnail_selectable_cell.detailed div.thumbLabel{
                padding-top: 19px;
            }
            .thumbnail_selectable_cell.detailed:nth-child(odd) {
                background-color: #fafafa;
            }
            @media only screen and (max-width: 680px){
                #ajxp_embed_template div.toolbarGroup span.ajxp_icon_span {
                    padding: inherit !important;
                }
            }
        </style>
        <script language="javascript" type="text/javascript" src="plugins/gui.ajax/res/js/ajaxplorer_boot.js"></script>
        <script type="text/javascript">
            var ajaxplorer, MessageHash={};
            var startParameters = {
                "BOOTER_URL":"index_shared.php?get_action=get_boot_conf&goto=AJXP_START_REPOSITORY&minisite_session=AJXP_LINK_HASH",
                "EXT_REP":"\/",
                "MAIN_ELEMENT":"AJXP_TEMPLATE_NAME",
                "SERVER_PREFIX_URI": "",
                "PRESET_LOGIN":"AJXP_PRELOGED_USER",
                "HASH_LOAD_ERROR":"AJXP_HASH_LOAD_ERROR",
                "PASSWORD_AUTH_ONLY":true,
                "SERVER_PERMANENT_PARAMS":"minisite_session=AJXP_LINK_HASH"
            };
            if(startParameters["PRESET_LOGIN"] == "ajxp_legacy_minisite"){
                delete startParameters["PRESET_LOGIN"];
                startParameters["PASSWORD_AUTH_ONLY"] = false;
            }
            document.observe("ajaxplorer:before_gui_load", function(e){
               ajaxplorer.currentThemeUsesIconFonts = true;
               document.documentElement.className += " ajxp_theme_AJXP_THEME";
           });
            if(startParameters['HASH_LOAD_ERROR']){
                document.observe("dom:loaded", function(){
                    $(startParameters['MAIN_ELEMENT']).update('<div class="hash_load_error">'+startParameters['HASH_LOAD_ERROR']+'</div>');
                });
            }else{
                window.ajxpBootstrap = new AjxpBootstrap(startParameters);
                window.ajxpMinisite = true;
                /*
                window.onbeforeunload = function(){
                    if(ajaxplorer && !Prototype.Browser.Gecko) ajaxplorer.actionBar.fireAction("logout");
                }
                */
                document.observe("dom:loaded", function(){
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

    <body style="overflow: hidden;" class="AJXP_PRELOGED_USER">
        <div id="AJXP_TEMPLATE_NAME" ajxpClass="AjxpPane" ajxpOptions='{"fit":"height", "fitParent":"window"}'></div>
    </body>
</html>

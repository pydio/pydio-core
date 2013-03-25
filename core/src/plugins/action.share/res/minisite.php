<html xmlns:ajxp>
	<head>
        <base href="/ajaxplorer/plugins/gui.ajax/"/>
		<title>AjaXplorer</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<link rel="icon" type="image/x-png" href="res/themes/vision/images/html-folder.png">
		<link rel="stylesheet" type="text/css" href="res/themes/vision/css/allz.css">
        <link rel="stylesheet" href="res/themes/vision/css/font-awesome.css"/>
        <link rel="stylesheet" href="res/themes/vision/css/media.css"/>
		<style type="text/css">
			.widget_title{
				font-family: "HelveticaNeue-Light", "Helvetica Neue Light", "Helvetica Neue", Helvetica, Arial, "Lucida Grande", sans-serif;
				font-size: 20px;
				font-weight: bold;
				padding: 5px;
			}
			#ajxp_shared_folder{
				width:600px;
				height:90%;
				text-align:left;
				/* THESE ONE ARE IMPORTANT */
				overflow:hidden;
				position:relative;
			}
            .action_bar{
                background-color: #ffffff;
            }
            .action_bar > .toolbarGroup{
                height: 40px;
            }
            div.separator{
                border-left-width: 0;
            }
		</style>
		<script language="javascript" type="text/javascript" src="res/js/ajaxplorer_boot.js"></script>
		<script type="text/javascript">
			var ajaxplorer, MessageHash={};
			var startParameters = {
				"BOOTER_URL":"../../index.php?get_action=get_boot_conf&goto=AJXP_START_REPOSITORY/",
				"EXT_REP":"\/", 
				"MAIN_ELEMENT":"ajxp_shared_folder",
				"SERVER_PREFIX_URI": "../../"
			};
            document.observe("ajaxplorer:before_gui_load", function(e){
               ajaxplorer.currentThemeUsesIconFonts = true;
               document.documentElement.className += " ajxp_theme_vision";
           });
			window.ajxpBootstrap = new AjxpBootstrap(startParameters);
		</script>
	</head>

	<body marginheight="0" marginwidth="0" leftmargin="0" topmargin="0">
	<div align="center">
		<div class="widget_title">AjaXplorer Shared Folder</div>
		<div id="ajxp_shared_folder" ajxpClass="AjxpPane" ajxpOptions='{"fit":"height", "fitParent":"window", "fitMarginBottom":40}'></div>
	</div>		
	</body>
</html>
<?php
/*
Plugin Name: AjaXplorer
Plugin URI: http://www.ajaxplorer.info/
Description: AjaXplorer file manager for Wordpress
Author: Charles du Jeu
Version: 2.3.8
Author URI: http://www.ajaxplorer.info
*/ 

add_action('admin_menu', 'ajxp_menu');
add_action('wp_logout', 'logout_ajxp_user');

function ajxp_menu(){
	add_submenu_page('edit.php', 'AjaXplorer', 'Ajaxplorer File Management', 8, __FILE__, 'ajxp_content');
}

function logout_ajxp_user(){	
	if(isSet($_SESSION["AJXP_USER"])){	
		unset($_SESSION["AJXP_USER"]);
	}
}

session_start();
function ajxp_content(){	
	if(isSet($_SESSION["AJXP_USER"])){	
		unset($_SESSION["AJXP_USER"]);
	}
	echo('<script src="../wp-content/plugins/ajaxplorer/client/js/lib/prototype/prototype.js"></script>');
	echo('<iframe id="ajxp_iframe" src="" width="100%" height="100" frameborder="0"></iframe>');
	echo("<script>\n");
	echo("function resizeIframe(){\n");
	echo(" var margin=5;if(Prototype.Browser.IE){margin=7;}\n");
	echo("   $('ajxp_iframe').setStyle({height:(parseInt(document.viewport.getHeight()) - ($('wphead').getHeight()+$('adminmenu').getHeight()+$('submenu').getHeight()))+margin+ 'px'});\n");
	echo("}\n");
	echo("\n");
	echo("Event.observe(window, 'load', function(){\n");
	echo("	 resizeIframe();\n");
	echo("   $('ajxp_iframe').src='../wp-content/plugins/ajaxplorer/';\n");
	echo("});\n");
	echo("Event.observe(window, 'resize', resizeIframe);\n");
	echo("</script>\n");
	echo("<style>body{overflow:hidden;} html{overflow:hidden;}</style>\n");
	
}

?>
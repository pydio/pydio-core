<?php
/*
Plugin Name: Ajaxplorer
Plugin URI: http://www.ajaxplorer.info/wp_ajaxplorer
Description: This plugin allow to associate directly AjaXplorer users to wordpress ones (using WP as the master). Warning, it will not work until you open the "Settings > AjaXplorer" panel (here on the left) to edit your AjaXplorer installation path. Tested with WP 2.8 & AjaXplorer 2.5.4
Version: 0.2
Author: Charles du Jeu
Author URI: http://www.ajaxplorer.info/
*/

define("AJXP_WP_GLUE_PATH", "plugins/auth.remote/glueCode.php");
global $options;
$options = get_option("wp_ajxp_options");

// AJXP FUNCTIONS
function AjxpAuthenticate($username, $password = "")
{
	global $plugInAction, $login, $result, $options;	
	if(!isSet($options) || !isset($options["ajxp_install_path"])) return ;
	$plugInAction = "login";
	$login = array("name"=>$username, "password"=>$password);  
   	include($options["ajxp_install_path"]."/".AJXP_WP_GLUE_PATH);
}

function AjxpLogout(){
	global $plugInAction, $options;
	if(!isSet($options) || !isset($options["ajxp_install_path"])) return ;
	$plugInAction = "logout";
   	include($options["ajxp_install_path"]."/".AJXP_WP_GLUE_PATH);
}

function createUser($userId){
	global $plugInAction, $user, $options;
	if(!isSet($options) || !isset($options["ajxp_install_path"])) return ;
	$plugInAction = "addUser";
	$userData = get_userdata($userId);
	$user = array("name" => $userData->user_login, "password" => $userData->user_pass);
	include($options["ajxp_install_path"]."/".AJXP_WP_GLUE_PATH);
}

function deleteUser($userId){
	global $plugInAction, $userName, $options;
	if(!isSet($options) || !isset($options["ajxp_install_path"])) return ;
	$plugInAction = "delUser";
	$userData = get_userdata($userId);
	$userName = $userData->user_login;
	include($options["ajxp_install_path"]."/".AJXP_WP_GLUE_PATH);
}

// Tell wordpress that your plugin hooked the authenticate action
add_action('wp_authenticate', AjxpAuthenticate, 1);
add_action('wp_logout', AjxpLogout, 1);
add_action('user_register', createUser, 1, 1);
add_action('delete_user', deleteUser, 1, 1);


// SETTINGS FUNCTIONS
function setting_menu() {
  add_options_page('AjaXplorer Options', 'AjaXplorer', '1', 'wp_ajxp_options', 'ajxp_options');
}

function ajxp_options() {
	echo '
	<div>
	<h2>AjaXplorer Plugin Options</h2>
	Options related to your AjaXplorer installation.
	<form action="options.php" method="post">';
	settings_fields('wp_ajxp_options');
	do_settings_sections('plugin');
	echo '<br><br><input name="Submit" class="button-primary" type="submit" value="';
	esc_attr_e("Save Changes");
	echo'" ></form></div>';	
}

function plugin_admin_init(){
	register_setting( 'wp_ajxp_options', 'wp_ajxp_options', 'plugin_options_validate' );
	add_settings_section('plugin_main', 'AjaXplorer Installation', 'install_section_text', 'plugin');
	add_settings_field('plugin_text_string', 'AjaXplorer Path', 'plugin_setting_string', 'plugin', 'plugin_main');
	add_settings_section('plugin_repo', 'Creating AjaXplorer Repositories', 'repo_section_text', 'plugin');
}

function install_section_text() {
	echo '<p>Installation path. Enter here the full path to your installation on the server, i.e. the root folder containing ajaxplorer index.php file. Do not include slash at the end. May look like <i>"/var/html/www/ajaxplorer"</i> or <i>"C:/Documents/web/AjaXplorer"</i></p>';
}

function repo_section_text(){
	
	$installPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(__FILE__)))));
	
	echo '<p>Now that your Wordpress users can access AjaXplorer, you have to create repositories in AjaXplorer and let them access it. This is not automated at the moment, so you have to log in as "admin" and create them manually from within AjaXplorer. Set the repositories default rights at least at "r" (read), so that the users can indeed access the repositories.</p>';

	echo '<p>Repository creation will ask you to enter the path to your repository. Here are some wordpress-related paths you may want to explore using AjaXplorer : 
	<ul>
		<li>. <b>'.$installPath.'/wp-content/themes</b> : The wordpress themes contents<li>
		<li>. <b>'.$installPath.'/wp-content/plugins</b> : The wordpress plugins<li>
		<li>. <b>'.$installPath.'/'.get_option('upload_path').'</b> : The media library<li>
	</ul>
	Of course, repositories are not limited to these values, you can browse whatever part of you server.
	</p>';

}

function plugin_setting_string() {
	$options = get_option('wp_ajxp_options');
	echo "<input id='plugin_text_string' name='wp_ajxp_options[ajxp_install_path]' size='70' type='text' value='{$options['ajxp_install_path']}' />";
}

function plugin_options_validate($input) {
	
	$newinput['ajxp_install_path'] = trim($input['ajxp_install_path']);
	$install = $newinput['ajxp_install_path'];
	if(substr($install, strlen($install)-1) == "/"){
		$newinput['ajxp_install_path'] = substr($install, 0, strlen($install)-1);
	}
	if(!is_dir($newinput['ajxp_install_path'])){
		$newinput["ajxp_install_path"] = "";
	}
	return $newinput;
}


add_action('admin_init', 'plugin_admin_init');
add_action('admin_menu', setting_menu);


?>
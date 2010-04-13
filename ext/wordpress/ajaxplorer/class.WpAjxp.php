<?php

/*
Plugin Name: Ajaxplorer
Plugin URI: http://www.ajaxplorer.info/wp_ajaxplorer
Description: This plugin allow to associate directly AjaXplorer users to wordpress ones (using WP as the master). Warning, it will not work until you open the "Settings > AjaXplorer" panel (here on the left) to edit your AjaXplorer installation path. Tested with WP 2.8 & AjaXplorer 2.5.4
Version: 0.3
Author: Charles du Jeu
Author URI: http://www.ajaxplorer.info/
*/

class Ajxp {
    var $options;
    var $secret;
    var $glueCode;
    var $glueCodeFound;
    var $autoCreate;
 
    function Ajxp() {
        $this->__construct();
    }
 
    function __construct() {
		$this->options = get_option("wp_ajxp_options");
		$this->secret = (isSet($this->options["ajxp_secret_key"])?$this->options["ajxp_secret_key"]:null);
		$this->glueCode = $this->options["ajxp_install_path"]."/plugins/auth.remote/glueCode.php";
		$this->glueCodeFound = @is_file($this->glueCode);
		$this->autoCreate = ($this->options["ajxp_auto_create"] == 'yes' ? true:false);
    }
 
    function init() {
		// Tell wordpress that your plugin hooked the authenticate action
		add_action('wp_authenticate', array(&$this, 'authenticate'), 1);
		add_action('wp_logout', array(&$this, 'logout'), 1);
		add_action('user_register', array(&$this, 'createUser'), 1, 1);
		add_action('set_user_role', array(&$this, 'updateUserRole'), 1, 1);
		add_action('delete_user', array(&$this, 'deleteUser'), 1, 1);
		
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('admin_menu', array(&$this, 'setting_menu'));
    }

	// AJXP FUNCTIONS	
	function authenticate($username, $password = "")
	{
		if(!$this->glueCodeFound) return ;
		global $plugInAction, $login, $result, $secret, $autoCreate;	
		$secret = $this->secret;
		$plugInAction = "login";
		$autoCreate = $this->autoCreate;
		$login = array("name"=>$username, "password"=>$password);  
	   	include($this->glueCode);
	   	// Update default rights (this could go in the trunk...)
	   	if($result == 1){
		   	$userObject = AuthService::getLoggedUser();
		   	if($userObject->isAdmin()){
		   		AuthService::updateAdminRights($userObject);
		   	}else{
				foreach (ConfService::getRepositoriesList() as $repositoryId => $repoObject)
				{			
					if($repoObject->getDefaultRight() != ""){
						$userObject->setRight($repositoryId, $repoObject->getDefaultRight());
					}
				}
		   	}
			$userObject->save();		   	
	   	}	   	
	}
	
	function logout(){
		global $plugInAction;
		if(!$this->glueCodeFound) return ;
		global $plugInAction, $secret;	
		$secret = $this->secret;
		$plugInAction = "logout";
	   	include($this->glueCode);
	}
	
	function updateUserRole($userId, $userRole){
		$this->createUser($userId, false);
	}
	
	function createUser($userId, $isNew=true){
		if(!$this->glueCodeFound) return ;
		global $plugInAction, $secret, $user;	
		$secret = $this->secret;
		$plugInAction = ($isNew?"addUser":"updateUser");
		$userData = get_userdata($userId);
		$user = array("name" => $userData->user_login, "password" => $userData->user_pass, "right" => ($userData->user_level == 10?'admin':''));		
		include($this->glueCode);
	}

	function deleteUser($userId){
		if(!$this->glueCodeFound) return ;
		global $plugInAction, $secret, $userName;	
		$secret = $this->secret;
		$plugInAction = "delUser";
		$userData = get_userdata($userId);
		$userName = $userData->user_login;
		include($this->glueCode);
	}
	
	// SETTINGS FUNCTIONS
	function setting_menu() {
	  add_options_page(__("AjaXplorer Options","ajxp"), __("AjaXplorer","ajxp"), '1', 'wp_ajxp_options', array(&$this, 'ajxp_options'));
	}
	
	function ajxp_options() {
		echo '
		<div>
		<h2>'.__("AjaXplorer Options","ajxp").'</h2>
		'.__("Options related to your AjaXplorer installation.","ajxp").'
		<form action="options.php" method="post">';
		settings_fields('wp_ajxp_options');
		do_settings_sections('plugin');
		echo '<br><br><input name="'.__("Submit").'" class="button-primary" type="submit" value="';
		esc_attr_e("Save Changes");
		echo'" ></form></div>';	
	}
	
	function admin_init(){
		register_setting( 'wp_ajxp_options', 'wp_ajxp_options', array(&$this,'plugin_options_validate') );
		add_settings_section('plugin_main', __("AjaXplorer Installation","ajxp"), array(&$this,'install_section_text'), 'plugin');
		add_settings_field('plugin_text_string', __("AjaXplorer Path","ajxp"), array(&$this,'plugin_setting_string'), 'plugin', 'plugin_main');
		add_settings_field('plugin_secret_string', __("Secret Key (must be the same as the AUTH_DRIVER 'SECRET' option in your configuration.","ajxp"), array(&$this,'plugin_secret_string'), 'plugin', 'plugin_main');
		add_settings_field('plugin_autocreate_string', __("Auto Create (Create Ajxp users when they login)","ajxp"), array(&$this,'plugin_autocreate_string'), 'plugin', 'plugin_main');
		add_settings_section('plugin_repo', __("Creating AjaXplorer Repositories","ajxp"), array(&$this,'repo_section_text'), 'plugin');
	}
	
	function guess_ajpx_path() {
		//get WP abs path
		$wp_abspath = ABSPATH;
		$url = dirname($wp_abspath);
		return $url.'/ajaxplorer';

	}
	
	function install_section_text() {
		echo"<p>";
		printf(__("Installation path. Enter here the full path to your installation on the server, i.e. the root folder containing ajaxplorer index.php file. Do not include slash at the end. May look like %s","ajxp"),'<em>'.$this->guess_ajpx_path().'</em>');
		echo"</p>";
	}

	function repo_section_text(){
		
		$installPath = str_replace("\\", "/", dirname(dirname(dirname(dirname(__FILE__)))));
		
		echo '<p>';
		_e("Now that your Wordpress users can access AjaXplorer, you have to create repositories in AjaXplorer and let them access it. This is not automated at the moment, so you have to log in as 'admin' and create them manually from within AjaXplorer. Set the repositories default rights at least at 'r' (read), so that the users can indeed access the repositories.","ajxp");
		echo '</p><p>';

		_e("Repository creation will ask you to enter the path to your repository. Here are some wordpress-related paths you may want to explore using AjaXplorer :","ajxp");
		echo'
		<ul>
			<li>. <b>'.$installPath.'/wp-content/themes</b> : '.__("The wordpress themes contents","ajxp").'<li>
			<li>. <b>'.$installPath.'/wp-content/plugins</b> : '.__("The wordpress plugins","ajxp").'<li>
			<li>. <b>'.$installPath.'/'.get_option('upload_path').'</b> : '.__("The media library","ajxp").'<li>
		</ul>';
		
		_e("Of course, repositories are not limited to these values, you can browse whatever part of you server","ajxp");
		
		echo'</p>';

	}
	
	function plugin_setting_string() {
		echo "<input id='plugin_text_string' name='wp_ajxp_options[ajxp_install_path]' size='70' type='text' value='{$this->options['ajxp_install_path']}' />";
	}

	function plugin_secret_string() {
		echo "<input id='plugin_secret_string' name='wp_ajxp_options[ajxp_secret_key]' size='70' type='text' value='{$this->options['ajxp_secret_key']}' />";
	}
	
	function plugin_autocreate_string(){
		$value = $this->options["ajxp_auto_create"];
		$yes = ($value == 'yes'?" checked":"");
		$no = ($value == 'no' ?" checked":"");
		echo "<input type='radio' id='ajxp_auto_create1' value='yes' name='wp_ajxp_options[ajxp_auto_create]'$yes> Yes";
		echo "<input type='radio' id='ajxp_auto_create2' value='no' name='wp_ajxp_options[ajxp_auto_create]'$no> No";
	}
	
	function plugin_options_validate($input) {
		
		$newinput = array();
		$newinput['ajxp_install_path'] = trim($input['ajxp_install_path']);
		$newinput['ajxp_secret_key'] = trim($input['ajxp_secret_key']);
		$install = $newinput['ajxp_install_path'];
		if(substr($install, strlen($install)-1) == "/"){
			$newinput['ajxp_install_path'] = substr($install, 0, strlen($install)-1);
		}
		if(!is_dir($newinput['ajxp_install_path'])){
			;
			//TO FIX : that notice do not work
			add_action('admin_notices', create_function('', 'echo \'<div id="message" class="error fade"><p><strong>'.sprintf(__("The directory %s do not exists","ajxp"),'<em>'.$newinput['ajxp_install_path'].'</em>').'</strong></p></div>\';'));
			$newinput["ajxp_install_path"] = "";
		}			
		$newinput['ajxp_auto_create'] = $input['ajxp_auto_create'];
		return $newinput;
	}
 

}
 
$ajxp_plugin = new Ajxp();
$ajxp_plugin->init();
?>
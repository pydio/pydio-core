<?php

/*
Plugin Name: AjaxplorerPublisher
Plugin URI: http://pyd.io/
Description: This plugin is providing a set of shortcodes to directly publish the content of an AjaXplorer workspace / folder inside a wordpress article.
Version: 1.0
Author: Charles du Jeu
Author URI: http://ajaxplorer.info/
*/

/**
 *
 *
 * @param AjxpPluginsLoader $loader
 */
if (!function_exists('ajxpParseWPQueryVar')) {

    function ajxpParseWPQueryVar($loader)
    {
        if (get_query_var("nodename") != "") {
            $v = get_query_var("nodename");
            $loader->downloadFile($v);
        }
    }

}


add_filter( 'rewrite_rules_array','my_insert_rewrite_rules' );
add_action( 'register_activation_hook','my_flush_rules' );

// flush_rules() if our rules are not yet included
function my_flush_rules()
{
    $rules = get_option( 'rewrite_rules' );

    if ( ! isset( $rules['(ajxp_download)/(.*)$'] ) ) {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
}

// Adding a new rule
function my_insert_rewrite_rules( $rules )
{
    $newrules = array();
    $newrules['(ajxp_download)/(.*)$'] = 'index.php?pagename=ajxp_download&nodename=$matches[2]';
    return $newrules + $rules;
}


add_filter( 'query_vars','my_insert_query_vars' );
// Adding the id var so that WP recognizes it
function my_insert_query_vars( $vars )
{
    array_push($vars, 'nodename');
    return $vars;
}


add_shortcode('ajxp_list_folder', 'ajxp_list_folder');
function ajxp_list_folder($atts)
{
    if (!isSet($atts["repository_alias"])) {
        $atts["repository_alias"] = "night-builds";
    }
    if (!isSet($atts["folder_path"])) {
        $atts["folder_path"] = "/";
    }
    if (!isSet($atts["limit"])) {
        $atts["limit"] = 0;
    }
    if (!isSet($atts["sort"])) {
        $atts["sort"] = "name";
    }
    if (!isSet($atts["filter"])) {
        $atts["filter"] = "";
    }
    $meta = null;
    if (isSet($atts["display_meta"])) {
        $meta = explode(",", $atts["display_meta"]);
    }

    return AjaXplorerPublisher::getInstance()->listFolder(
        $atts["repository_alias"],
        $atts["folder_path"],
        $atts["sort"],
        $atts["limit"],
        $atts["filter"],
        $meta
    );

}

/*
 *
 * Ajoute un template pour les plugins
 *
 */
function ajxp_get_ajxp_plugin_template($single_template)
{
    AjaXplorerPublisher::getInstance();
    return $single_template;
}

add_filter( "page_template", "ajxp_get_ajxp_plugin_template" ) ;


define("AJXP_EXEC", true);

class AjaXplorerPublisher
{
    public $pluginService;
    private static $instance;

    public $glueCodePath;
    public $authLogin;
    public $authPwd;
    public $dlPage;

    public function __construct()
    {
        $data = parse_ini_file(dirname(__FILE__).'/config.properties');
        $this->glueCodePath = $data['glueCodePath'];
        $this->authLogin = $data['authLogin'];
        $this->authPwd = $data['authPwd'];
        $this->dlPage = $data['dlPage'];

        include($this->glueCodePath);
        $this->pluginService = $pServ;
        ajxpParseWPQueryVar($this);
    }

    public static function getInstance()
    {
        if (!isSet(self::$instance)) {
            self::$instance = new AjaXplorerPublisher();
        }
        return self::$instance;
    }

    public function listFolder($repositoryAlias, $folderPath, $sort='name', $limit = 0, $filter = "", $meta = null)
    {
        error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
        ob_start();
        ob_start();
        $alreadyInstanciated = true ;
        if (AuthService::getLoggedUser() == null) {
            AuthService::logUser($this->authLogin, $this->authPwd, true);
            $alreadyInstanciated = false;
        }
        $nbRep = ConfService::getRepositoryByAlias($repositoryAlias);
        if (!is_object($nbRep)) {
            return '<b>Cannot find workspace with alias '.$repositoryAlias.'</b>';
        }
        ConfService::switchRootDir($nbRep->getId());
        ConfService::getConfStorageImpl();
        ConfService::loadRepositoryDriver();
        if (!$alreadyInstanciated) {
            AJXP_PluginsService::getInstance()->initActivePlugins();
        }

        if (isSet($_GET["ajxp_folder"])) {
            $parts = explode("/", trim($_GET["ajxp_folder"], "/"));
            $alias = array_shift($parts);
            if ($alias == $repositoryAlias) {
                $currentSubfolder = implode("/", $parts);
                $folderPath .= "/".$currentSubfolder;
                array_pop($parts);
                $upPath = $alias."/".implode("/", $parts);
            }
        }

        AJXP_Controller::findActionAndApply("ls", array("dir"=>$folderPath), array());
        $xml = ob_get_contents();
        ob_end_clean();

        $doc = DOMDocument::loadXML($xml);
        $items = array();
        $folders = array();
        foreach ($doc->documentElement->childNodes as $node) {
            $mTime = intval($node->attributes->getNamedItem("ajxp_modiftime")->nodeValue);
            $isFile = $node->attributes->getNamedItem("is_file")->nodeValue;
            $name = $node->attributes->getNamedItem("text")->nodeValue;
            $additionalMeta = array();
            if (isSet($meta)) {
                foreach ($meta as $mname) {
                    $test = $node->attributes->getNamedItem($mname);
                    if($test == null) continue;
                    $mvalue = $test->nodeValue;
                    $additionalMeta[] = '<span class="ajxp_meta"><span class="ajxp_meta_name">'.$mname.'</span><span class="ajxp_meta_value">'.$mvalue.'</span></span>';
                }
            }
            if($filter != "" && preg_match($filter, $name) == 1) continue;
            $date = date("Y-m-d H:i", $mTime);

            if(isSet($currentSubfolder)) $nodeName = '/'.$repositoryAlias."/".$currentSubfolder."/".urlencode($name);
            else $nodeName = '/'.$repositoryAlias."/".urlencode($name);
            if ($sort == 'name') {
                $sorter = $name;
                $sort_dir = 'asc';
            } else if ($sort == 'date' || $sort == 'date_desc') {
                $sorter = $mTime;
                $sort_dir = ($sort == 'date_desc' ? 'desc' : 'asc');
            }
            if ($isFile == "true") {
                $items[$sorter] = "<li><a href='".$this->dlPage.$nodeName."'>".$name."</a> $date</li>";
            } else {
                $items[$sorter] = "<li><a href='?ajxp_folder=$nodeName'>".$name."</a></li>";
            }
        }
        if($sort_dir == 'asc') ksort($items);
        else krsort($items);

        if (isSet($upPath)) {
            $items = array_merge(array("<li><a href='?ajxp_folder=".$upPath."'>..</a></li>"), $items);
        }
        if ($limit > 0 && count($items) > $limit) {
            $items = array_slice($items, 0, $limit, true);
            $items[] = "<li><a href='".$this->dlPage."'>More...</a> (direct listing)</li>";
        }
        $c = "<ul class='ajxp_files_list'>" . implode("", array_values($items)). "</ul>";
        return $c;
    }


    public function downloadFile($nodeName)
    {
        $nodeName = urldecode($nodeName);

        //ob_start();
        $alreadyInstanciated = true ;
        if (AuthService::getLoggedUser() == null) {
            AuthService::logUser($this->authLogin, $this->authPwd, true);
            $alreadyInstanciated = false;
        }
        $parts = explode("/", trim($nodeName, "/"));
        $repoAlias = array_shift($parts);
        $fileName = implode("/", $parts);
        $nbRep = ConfService::getRepositoryByAlias($repoAlias);
        $defaultRepoId = $nbRep->getId();
        ConfService::switchRootDir($defaultRepoId);
        ConfService::getConfStorageImpl();
        ConfService::loadRepositoryDriver();
        if (!$alreadyInstanciated) {
            AJXP_PluginsService::getInstance()->initActivePlugins();
        }
        //ob_end_clean();
        AJXP_Controller::findActionAndApply("download", array("file"=>"/".$fileName), array());
        exit();

    }


}

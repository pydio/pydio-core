<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 03/08/12
 * Time: 17:32
 * To change this template use File | Settings | File Templates.
 */
class AJXP_Sabre_RootCollection extends Sabre_DAV_SimpleCollection
{

    function getChildren(){

        $this->children = array();
        $u = AuthService::getLoggedUser();
        if($u != null){
            $repos = ConfService::getRepositoriesList();
            foreach($repos as $repository){
                $accessType = $repository->getAccessType();
                $driver = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
                if($u->canSwitchTo($repository->getUniqueId()) && is_a($driver, "AjxpWebdavProvider")){
                    $this->children[$repository->getSlug()] = new Sabre_DAV_SimpleCollection($repository->getSlug());
                }

            }
        }
        return $this->children;
    }

    function childExists($name){
        $c = $this->getChildren();
        return array_key_exists($name, $c);
    }

    function getChild($name){
        $c = $this->getChildren();
        return $c[$name];
    }

}

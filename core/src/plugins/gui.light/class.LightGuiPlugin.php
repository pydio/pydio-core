<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Charles
 * Date: 01/09/11
 * Time: 14:40
 * To change this template use File | Settings | File Templates.
 */
 
class LightGuiPlugin extends AJXP_Plugin {

    public function performChecks(){
        if(!isSet($_COOKIE["AJXP_GUI"]) || $_COOKIE["AJXP_GUI"] != "light") throw new Exception("no");
    }

}

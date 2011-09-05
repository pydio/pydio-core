<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Charles
 * Date: 01/09/11
 * Time: 14:40
 * To change this template use File | Settings | File Templates.
 */
 
class IOSGuiPlugin extends AJXP_Plugin {

    public function performChecks(){
        if(!AJXP_Utils::userAgentIsMobile()) throw new Exception("no");
        if (!(AJXP_Utils::userAgentIsIOS() && !isSet($_GET["skipIOS"]) && !isSet($_COOKIE["SKIP_IOS"]))){
            throw new Exception("no");
        }
    }

}

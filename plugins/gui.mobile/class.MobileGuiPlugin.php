<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Charles
 * Date: 01/09/11
 * Time: 14:40
 * To change this template use File | Settings | File Templates.
 */
 
class MobileGuiPlugin extends AJXP_Plugin {

    public function performChecks(){
        if(!AJXP_Utils::userAgentIsMobile()) throw new Exception("no");
    }

}

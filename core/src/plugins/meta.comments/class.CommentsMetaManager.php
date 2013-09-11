<?php
/**
 * Created by JetBrains PhpStorm.
 * User: admin
 * Date: 11/09/13
 * Time: 11:14
 * To change this template use File | Settings | File Templates.
 */

define("AJXP_META_SPACE_COMMENTS", "AJXP_META_SPACE_COMMENTS");

class CommentsMetaManager extends AJXP_Plugin
{
    public function initMeta($accessDriver)
    {

    }
        /**
     * @param AJXP_Node $ajxpNode
     */
    public function mergeMeta($ajxpNode)
    {
        $ajxpNode->mergeMetadata(array("ajxp_has_comments_feed" => "true"));
        if ($ajxpNode->retrieveMetadata(AJXP_META_SPACE_COMMENTS, false) != null) {
        }

    }

    /**
     * @param String $actionName
     * @param Array $httpVars
     * @param Array $fileVars
     */
    public function switchActions($actionName, $httpVars, $fileVars)
    {
    }

}

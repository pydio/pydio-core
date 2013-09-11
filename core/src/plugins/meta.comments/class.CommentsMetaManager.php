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
    /**
     * @var AbstractAccessDriver
     */
    private $accessDriver;
    /**
     * @var MetaStoreProvider
     */
    private $metaStore;

    public function initMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new Exception("The 'meta.comments' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
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
     *
     * @param AJXP_Node $oldFile
     * @param AJXP_Node $newFile
     * @param Boolean $copy
     */
    public function moveMeta($oldFile, $newFile = null, $copy = false)
    {
        if($oldFile == null) return;

        if(!$copy && $this->metaStore->inherentMetaMove()) return;

        $oldMeta = $this->metaStore->retrieveMetadata($oldFile, AJXP_META_SPACE_COMMENTS);
        if (!count($oldMeta)) {
            return;
        }
        // If it's a move or a delete, delete old data
        if (!$copy) {
            $this->metaStore->removeMetadata($oldFile, AJXP_META_SPACE_COMMENTS);
        }
        // If copy or move, copy data.
        if ($newFile != null) {
            $this->metaStore->setMetadata($newFile, AJXP_META_SPACE_COMMENTS, $oldMeta);
        }
    }


    /**
     * @param String $actionName
     * @param Array $httpVars
     * @param Array $fileVars
     */
    public function switchActions($actionName, $httpVars, $fileVars)
    {
        $userSelection = new UserSelection();
        $userSelection->initFromHttpVars($httpVars);
        $uniqNode = $userSelection->getUniqueNode($this->accessDriver);
        $existingFeed = $uniqNode->retrieveMetadata(AJXP_META_SPACE_COMMENTS, false);
        if ($existingFeed == null) {
            $existingFeed = array();
        }
        $mess = ConfService::getMessages();
        switch ($actionName) {

            case "post_comment":

                $uId = AuthService::getLoggedUser()->getId();
                $com = array(
                    "date"      => time(),
                    "author"    => $uId,
                    "content"   => $httpVars["content"]
                );
                $existingFeed[] = $com;
                $uniqNode->removeMetadata(AJXP_META_SPACE_COMMENTS, false);
                $uniqNode->setMetadata(AJXP_META_SPACE_COMMENTS, $existingFeed, false);
                HTMLWriter::charsetHeader("application/json");
                $com["hdate"] = AJXP_Utils::relativeDate($com["date"], $mess);
                echo json_encode($com);

                break;

            case "load_comments_feed":

                HTMLWriter::charsetHeader("application/json");
                foreach ($existingFeed as &$item) {
                    $item["hdate"] = AJXP_Utils::relativeDate($item["date"], $mess);
                }
                echo json_encode($existingFeed);

                break;

            case "delete_comment":

                $data = json_decode($httpVars["comment_data"], true);
                $reFeed = array();
                if($data["author"] != AuthService::getLoggedUser()->getId()) break;
                foreach ($existingFeed as $fElement) {
                    if ($fElement["date"] == $data["date"] && $fElement["author"] == $data["author"] && $fElement["content"] == $data["content"]) {
                        continue;
                    }
                    $fElement["hdate"] = AJXP_Utils::relativeDate($fElement["date"], $mess);
                    $reFeed[] = $fElement;
                }
                $uniqNode->removeMetadata(AJXP_META_SPACE_COMMENTS, false);
                $uniqNode->setMetadata(AJXP_META_SPACE_COMMENTS, $reFeed, false);
                HTMLWriter::charsetHeader("application/json");
                echo json_encode($reFeed);

                break;

            default:
                break;
        }

    }

}

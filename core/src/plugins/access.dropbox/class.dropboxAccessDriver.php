<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 *
 */
namespace Pydio\Access\Driver\StreamProvider\Dropbox;

use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver;
use Pydio\Core\Exception\PydioUserAlertException;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access a dropbox account
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class dropboxAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioUserAlertException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        $this->urlBase =$contextInterface->getUrlBase();

        if (!Utils::searchIncludePath('HTTP/OAuth/Consumer.php')) {
            $this->logError("Dropbox", "The PEAR HTTP_OAuth package must be installed!");
            return;
        }

        $consumerKey = $this->repository->getContextOption($contextInterface, "CONSUMER_KEY");
        $consumerSecret = $this->repository->getContextOption($contextInterface, "CONSUMER_SECRET");
        $oauth = new \Dropbox_OAuth_PEAR($consumerKey, $consumerSecret);

        // TOKENS IN SESSION?
        if(!empty($_SESSION["OAUTH_DROPBOX_TOKENS"])) return;

        // TOKENS IN FILE ?
        $tokens = $this->getTokens($contextInterface);
        if (!empty($tokens)) {
            $_SESSION["OAUTH_DROPBOX_TOKENS"] = $tokens;
            return;
        }

        // OAUTH NEGOCIATION
        if (isset($_SESSION['DROPBOX_NEGOCIATION_STATE'])) {
            $state = $_SESSION['DROPBOX_NEGOCIATION_STATE'];
        } else {
            $state = 1;
        }
        switch ($state) {

            case 1 :
                $tokens = $oauth->getRequestToken();
                //print_r($tokens);

                // Note that if you want the user to automatically redirect back, you can
                // add the 'callback' argument to getAuthorizeUrl.
                //echo "Step 2: You must now redirect the user to:\n";
                $_SESSION['DROPBOX_NEGOCIATION_STATE'] = 2;
                $_SESSION['oauth_tokens'] = $tokens;
                throw new PydioUserAlertException("Please go to <a style=\"text-decoration:underline;\" target=\"_blank\" href=\"".$oauth->getAuthorizeUrl()."\">".$oauth->getAuthorizeUrl()."</a> to authorize the access to your dropbox. Then try again to switch to this workspace.");

            case 2 :
                $oauth->setToken($_SESSION['oauth_tokens']);
                try{
                    $tokens = $oauth->getAccessToken();
                }catch(\Exception $oauthEx){
                    throw new PydioUserAlertException($oauthEx->getMessage() . ". Please go to <a style=\"text-decoration:underline;\" target=\"_blank\" href=\"".$oauth->getAuthorizeUrl()."\">".$oauth->getAuthorizeUrl()."</a> to authorize the access to your dropbox. Then try again to switch to this workspace.");
                }
                $_SESSION['DROPBOX_NEGOCIATION_STATE'] = 3;
                $_SESSION['OAUTH_DROPBOX_TOKENS'] = $tokens;
                $this->setTokens($contextInterface, $tokens);
                return;
        }

        throw new \Exception("Impossible to find the dropbox tokens for accessing this workspace");

    }

    public function performChecks()
    {
        if (!Utils::searchIncludePath('HTTP/OAuth/Consumer.php')) {
            throw new \Exception("The PEAR HTTP_OAuth package must be installed!");
        }
    }

    public function isWriteable($dir, $type = "dir")
    {
        return true;
    }

    public function getTokens(ContextInterface $ctx)
    {
        $repo = $ctx->getRepository();
        if($repo->getContextOption($ctx, "DROPBOX_OAUTH_TOKENS") !== null && is_array($repo->getContextOption($ctx, "DROPBOX_OAUTH_TOKENS"))){
            return $repo->getContextOption($ctx, "DROPBOX_OAUTH_TOKENS");
        }
        $repositoryId = $repo->getId();
        if(UsersService::usersEnabled()) {
            $u = $ctx->getUser();
            $userId = $u->getId();
        }else {
            $userId = "shared";
        }
        return Utils::loadSerialFile(AJXP_DATA_PATH."/plugins/access.dropbox/".$repositoryId."_".$userId."_tokens");
    }

    public function setTokens(ContextInterface $ctx, $oauth_tokens)
    {
        $repositoryId = $ctx->getUser()->getId();
        if(UsersService::usersEnabled()) $userId = $ctx->getUser()->getId();
        else $userId = "shared";
        Utils::saveSerialFile(AJXP_DATA_PATH."/plugins/access.dropbox/".$repositoryId."_".$userId."_tokens", $oauth_tokens, true);
    }

    public function makeSharedRepositoryOptions(ContextInterface $ctx, $httpVars)
    {
        $newOptions = parent::makeSharedRepositoryOptions($ctx, $httpVars);
        $newOptions["DROPBOX_OAUTH_TOKENS"] = $this->getTokens($ctx);
        return $newOptions;
    }


}

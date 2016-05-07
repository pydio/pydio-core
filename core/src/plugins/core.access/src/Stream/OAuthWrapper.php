<?php
/*
 * Copyright 2007-2016 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

defined('AJXP_EXEC') or die('Access not allowed');

use Pydio\Access\Core\AJXP_SchemeTranslatorWrapper;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\CacheService;
use CommerceGuys\Guzzle\Oauth2\GrantType\AuthorizationCode;
use CommerceGuys\Guzzle\Oauth2\AccessToken;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client as GuzzleClient;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Exception\PydioUserAlertException;
use Exception;
use GuzzleHttp\Exception\RequestException;

class OAuthWrapper extends AJXP_SchemeTranslatorWrapper
{
    /**
     * @param $url
     * @return bool|void
     * @throws \Pydio\Core\Exception\PydioUserAlertException
     * @throws Exception
     */
    public static function applyInitPathHook($url) {

        if (!class_exists('CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber')) {
            throw new Exception('Oauth plugin not loaded - go to ' . AJXP_BIN_FOLDER . '/guzzle from the command line and run \'composer update\' to install');
        }

        // Repository information
        $urlParts = Utils::safeParseUrl($url);
        $repository = ConfService::getRepositoryById($urlParts["host"]);

        if ($repository == null) {
            throw new Exception("Cannot find repository");
        }

        $repositoryId = $repository->getId();

        if(AuthService::usersEnabled()) {
            $u = AuthService::getLoggedUser();
            $userId = $u->getId();
            if($u->getResolveAsParent()){
                $userId = $u->getParent();
            }
        } else {
            $userId = 'shared';
        }

        // User information

        // Repository params
        $clientId     = $repository->getOption('CLIENT_ID');
        $clientSecret = $repository->getOption('CLIENT_SECRET');
        $scope        = $repository->getOption('SCOPE');
        $authUrl      = $repository->getOption('AUTH_URL');
        $tokenUrl     = $repository->getOption('TOKEN_URL');
        $redirectUrl  = $repository->getOption('REDIRECT_URL');

        $authUrl .= '?client_id=' . $clientId .
                    '&scope=' . $scope .
                    '&redirect_uri=' . urlencode($redirectUrl) .
                    '&response_type=code';

        // Retrieving context
        $repoData = self::actualRepositoryWrapperData($urlParts["host"]);
        $repoProtocol = $repoData['protocol'];

        $default = stream_context_get_options(stream_context_get_default());

        // Retrieving subscriber
        $oauth2 = $default[$repoProtocol]['oauth2_subscriber'];

        if (!empty($oauth2)) {
            // Authentication already made for this request - move on
            return true;
        }

        // Retrieving tokens
        $tokensKey = self::getTokenKey($repositoryId, $userId);
        $tokens = self::getTokens($tokensKey);

        $accessToken = $tokens[0];
        $refreshToken = $tokens[1];

        // OAuth 2 Tokens
        $oauth2Client = new GuzzleClient(['base_url' => $tokenUrl]);

        $config = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => $scope,
            'redirect_uri'  => $redirectUrl,
            'token_url'     => '',
            'auth_location' => 'body',
        ];

        // Setting up the subscriber
        if (empty($refreshToken) || !empty($_SESSION['oauth_code'])) {
            // Authorization code
            $config['code'] = $_SESSION['oauth_code'];

            $accessToken = new AuthorizationCode($oauth2Client, $config);
            $refreshToken = new RefreshToken($oauth2Client, $config);

            $oauth2 = new Oauth2Subscriber($accessToken, $refreshToken);

            unset($_SESSION['oauth_code']);
        } else {
            // Refresh Token
            $config['refresh_token'] = $refreshToken;

            $oauth2 = new Oauth2Subscriber(null, new RefreshToken($oauth2Client, $config));

            $oauth2->setAccessToken($accessToken);
            $oauth2->setRefreshToken($refreshToken);
        }

        // Retrieving access token and checking access
        try {
            $accessToken = $oauth2->getAccessToken();
            $refreshToken = $oauth2->getRefreshToken();
        } catch (\Exception $e) {
            throw new PydioUserAlertException("Please go to <a style=\"text-decoration:underline;\" href=\"" . $authUrl . "\">" . $authUrl . "</a> to authorize the access to your onedrive. Then try again to switch to this workspace");
        }

        // Saving tokens for later use
        self::setTokens($tokensKey, $accessToken->getToken(), $refreshToken->getToken());

        // Saving subscriber in context
        $default[$repoProtocol]['oauth2_subscriber'] = $oauth2;

        // Retrieving client
        $client = $default[$repoProtocol]['client'];
        $httpClient = $client->getHttpClient();
        $httpClient->getEmitter()->attach($oauth2);

        stream_context_set_default($default);

        return true;
    }

    /**
     * @return string key
     */
    private static function getTokenKey($repositoryId, $userId) {
        return 'OAUTH_ONEDRIVE_' . $repositoryId . '_' . $userId . '_TOKENS';
    }
    /**
     * @return array
     */
    private static function getTokens($key)
    {
        // TOKENS IN SESSION?
        if (!empty($_SESSION[$key])) return $_SESSION[$key];

        // TOKENS IN CACHE?
        if ($tokens = CacheService::fetch(AJXP_CACHE_SERVICE_NS_SHARED, $key)) return $tokens;

        // TOKENS IN FILE ?
        return Utils::loadSerialFile(AJXP_DATA_PATH . '/plugins/access.onedrive/' . $key);
    }

    /**
     * @param $oauth_tokens
     * @return bool
     * @throws Exception
     */
    private function setTokens($key, $accessToken, $refreshToken)
    {
        $value = [$accessToken, $refreshToken];

        // Save in file
        Utils::saveSerialFile(AJXP_DATA_PATH . '/plugins/access.onedrive/' . $key, $value, true);

        // Save in cache
        CacheService::save(AJXP_CACHE_SERVICE_NS_SHARED, $key, $value);

        // Save in session
        $_SESSION["OAUTH_ONEDRIVE_TOKENS"] = $value;

        return true;
    }

}
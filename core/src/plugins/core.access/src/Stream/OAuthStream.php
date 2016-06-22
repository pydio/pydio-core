<?php
/*
 * Copyright 2007-2016 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

use CommerceGuys\Guzzle\Oauth2\GrantType\AuthorizationCode;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Exception\PydioUserAlertException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\CacheService;
use Pydio\Core\Utils\Utils;

class OAuthStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var ContextInterface Context */
    private $context;

    public function __construct(
        StreamInterface $stream,
        AJXP_Node $node
    ) {
        $this->context = $node->getContext();

        // Context Variables
        $repository = $this->context->getRepository();
        $user = $this->context->getUser();

        // Repository options
        $options = $this->getOptions($this->context);

        $authUrl = $options["authUrl"] . $this->getAuthURI($options);

        // Retrieving tokens
        $tokens = $this->getTokens();

        $accessToken = $tokens[0];
        $refreshToken = $tokens[1];

        // OAuth 2 Tokens
        $oauth2Client = new Client(['base_url' => $options["tokenUrl"]]);

        // Mandatory config
        $config = [
            'client_id'     => $options["clientId"],
            'client_secret' => $options["clientSecret"],
            'redirect_uri'  => $options["redirectUrl"],
            'token_url'     => '',
            'auth_location' => 'body',
        ];

        // Non-mandatory
        if (!empty($options["scope"])) {
            $config['scope'] = $options["scope"];
        }

        $code = Stream::getContextOption($this->context, "oauth_code");
        // Setting up the subscriber
        if (isset($code)) {
            // Authorization code
            $config['code'] = $code;

            $accessToken = new AuthorizationCode($oauth2Client, $config);
            $refreshToken = new RefreshToken($oauth2Client, $config);

            $oauth2 = new Oauth2Subscriber($accessToken, $refreshToken);
        } else if (!empty($accessToken)) {
            if (empty($refreshToken)) {
                // Using access token
                $oauth2 = new Oauth2Subscriber(null, null);
                $oauth2->setAccessToken($accessToken);
            } else {
                // Refresh Token
                $config['refresh_token'] = $refreshToken;

                $oauth2 = new Oauth2Subscriber(null, new RefreshToken($oauth2Client, $config));

                $oauth2->setAccessToken($accessToken);
                $oauth2->setRefreshToken($refreshToken);
            }
        }

        if (empty($oauth2)) {
            throw new PydioUserAlertException("Please go to <a style=\"text-decoration:underline;\" href=\"" . $authUrl . "\">" . $authUrl . "</a> to authorize the access to your onedrive. Then try again to switch to this workspace");
        }

        // Retrieving access token and checking access
        try {
            $accessToken = $oauth2->getAccessToken();
            $refreshToken = $oauth2->getRefreshToken();
        } catch (\Exception $e) {
            throw new PydioUserAlertException("Please go to <a style=\"text-decoration:underline;\" href=\"" . $authUrl . "\">" . $authUrl . "</a> to authorize the access to your onedrive. Then try again to switch to this workspace");
        }

        // Saving tokens for later use
        $accessToken = $accessToken->getToken();
        if (isset($refreshToken)) {
            $refreshToken = $refreshToken->getToken();
        }
        $this->setTokens($accessToken, $refreshToken);

        Stream::addContextOption($this->context, [
            "auth" => "oauth2",
            "subscribers" => [$oauth2]
        ]);

        $resource = PydioStreamWrapper::getResource($stream);
        $this->stream = new Stream($resource, $node);
    }

    public function getOptions(ContextInterface $ctx) {
        $repository = $ctx->getRepository();

        return [
            "clientId"     => $repository->getContextOption($ctx, 'CLIENT_ID'),
            "clientSecret" => $repository->getContextOption($ctx, 'CLIENT_SECRET'),
            "scope"        => $repository->getContextOption($ctx, 'SCOPE'),
            "authUrl"      => $repository->getContextOption($ctx, 'AUTH_URL'),
            "tokenUrl"     => $repository->getContextOption($ctx, 'TOKEN_URL'),
            "redirectUrl"  => $repository->getContextOption($ctx, 'REDIRECT_URL')
        ];
    }

    public function getAuthURI(array $options) {
        $uri = '?client_id=%s' .
            '&scope=%s' .
            '&redirect_uri=%s' .
            '&response_type=code';

        return sprintf($uri,
            $options["clientId"],
            $options["scope"],
            urlencode($options["redirectUrl"])
        );
    }

    /**
     * @return string key
     */
    private function getTokenKey() {
        return 'OAUTH_' . $this->context->getStringIdentifier() . '_TOKENS';
    }
    /**
     * @return array
     */
    private function getTokens()
    {
        $key = $this->getTokenKey();

        // TOKENS IN SESSION?
        if (!empty($_SESSION[$key])) return $_SESSION[$key];

        // TOKENS IN CACHE?
        if ($tokens = CacheService::fetch(AJXP_CACHE_SERVICE_NS_SHARED, $key)) return $tokens;

        // TOKENS IN FILE ?
        return Utils::loadSerialFile(AJXP_CACHE_DIR . '/' . $key);
    }

    /**
     * @param $accessToken
     * @param $refreshToken
     * @return bool
     */
    private function setTokens($accessToken, $refreshToken)
    {
        $key = $this->getTokenKey();

        $value = [$accessToken, $refreshToken];

        // Save in file
        Utils::saveSerialFile(AJXP_CACHE_DIR . '/' . $key, $value, true);

        // Save in cache
        CacheService::save(AJXP_CACHE_SERVICE_NS_SHARED, $key, $value);

        // Save in session
        $_SESSION[$key] = $value;

        return true;
    }

    public function getContents() {
        return $this->stream->getContents();
    }
}
<?php
/*
 * Copyright 2007-2016 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\Stream\StreamDecoratorTrait;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Stream\Exception\OAuthException;
use Pydio\Access\Core\Stream\Utils\AuthorizationCode;
use Pydio\Core\Exception\PydioUserAlertException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\CacheService;
use Pydio\Core\Utils\FileHelper;


/**
 * Class OAuthStream
 * @package Pydio\Access\Core\Stream
 */
class OAuthStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var ContextInterface Context */
    private $context;

    /**
     * OAuthStream constructor.
     * @param StreamInterface $stream
     * @param mixed $base
     * @throws PydioUserAlertException
     * @throws \Exception
     */
    public function __construct(
        StreamInterface $stream,
        $base
    ) {
        /** @var AJXP_Node $node */
        $node = null;

        $context = null;

        if ($base instanceof AJXP_Node) {
            $node = $base;
            $context = $node->getContext();
        } elseif ($base instanceof ContextInterface) {
            $context = $base;
        } else {
            return;
        }

        $this->context = $context;

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
            throw new OAuthException("You will be redirected to your account for authentication", $authUrl);
        }

        // Retrieving access token and checking access
        try {
            $accessToken = $oauth2->getAccessToken();
            $refreshToken = $oauth2->getRefreshToken();
        } catch (\Exception $e) {
            throw new OAuthException("You will be redirected to your account for authentication", $authUrl);
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

        $this->stream = $stream;
    }

    /**
     * @param ContextInterface $ctx
     * @return array
     */
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

    /**
     * @param array $options
     * @return string
     */
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
        return FileHelper::loadSerialFile(AJXP_CACHE_DIR . '/' . $key);
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
        FileHelper::saveSerialFile(AJXP_CACHE_DIR . '/' . $key, $value, true);

        // Save in cache
        CacheService::save(AJXP_CACHE_SERVICE_NS_SHARED, $key, $value);

        // Save in session
        $_SESSION[$key] = $value;

        return true;
    }

    /**
     * @return \GuzzleHttp\Ring\Future\FutureInterface|mixed|null|string
     */
    public function getContents() {
        return $this->stream->getContents();
    }
}
